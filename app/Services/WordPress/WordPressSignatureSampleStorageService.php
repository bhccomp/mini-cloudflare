<?php

namespace App\Services\WordPress;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class WordPressSignatureSampleStorageService
{
    public const BASE_DIRECTORY = 'wordpress-signature-samples';

    public function duplicateSampleForSha256(?string $sha256, ?int $ignoreId = null): ?WordPressSignatureSample
    {
        $normalized = is_string($sha256) ? trim(strtolower($sha256)) : '';

        if ($normalized === '') {
            return null;
        }

        return WordPressSignatureSample::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('sha256', $normalized)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{
     *   duplicate_content_groups:int,
     *   deleted_duplicate_samples:int,
     *   updated_signature_sources:int,
     *   renamed_samples:int,
     *   moved_files:int,
     *   remaining_duplicate_content_groups:int,
     *   remaining_duplicate_name_groups:int
     * }
     */
    public function deduplicateAndRenameSamples(): array
    {
        $duplicateContentGroups = 0;
        $deletedDuplicateSamples = 0;
        $updatedSignatureSources = 0;
        $renamedSamples = 0;

        $duplicateGroups = WordPressSignatureSample::query()
            ->whereNotNull('sha256')
            ->where('sha256', '!=', '')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WordPressSignatureSample $sample): string => (string) $sample->sha256)
            ->filter(static fn (Collection $group): bool => $group->count() > 1);

        foreach ($duplicateGroups as $group) {
            $duplicateContentGroups++;
            $canonical = $this->selectCanonicalSample($group);

            foreach ($group as $sample) {
                if ($sample->id === $canonical->id) {
                    continue;
                }

                $updatedSignatureSources += $this->updateSignatureSourceSampleName($sample->name, $canonical->name);

                $filePath = trim((string) $sample->file_path);
                $sample->delete();
                $deletedDuplicateSamples++;

                if ($filePath !== '' && Storage::disk('local')->exists($filePath) && ! $this->sampleUsesFilePath($filePath)) {
                    Storage::disk('local')->delete($filePath);
                }
            }
        }

        $duplicateNameGroups = WordPressSignatureSample::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WordPressSignatureSample $sample): string => trim((string) $sample->name))
            ->filter(static fn (Collection $group, string $name): bool => $name !== '' && $group->count() > 1);

        foreach ($duplicateNameGroups as $group) {
            $keeper = $group->sortBy('id')->first();

            foreach ($group->sortBy('id') as $sample) {
                if (! $sample instanceof WordPressSignatureSample || ! $keeper instanceof WordPressSignatureSample) {
                    continue;
                }

                if ($sample->id === $keeper->id) {
                    continue;
                }

                $newName = $this->uniqueSampleName($sample);

                if ($newName === $sample->name) {
                    continue;
                }

                $oldName = (string) $sample->name;
                $sample->name = $newName;
                $sample->save();
                $renamedSamples++;

                $updatedSignatureSources += $this->updateSignatureSourceSampleName($oldName, $newName);
                $this->renameSignatureIfSafe($oldName, $newName);
            }
        }

        $syncResult = $this->scanAndSync(true);

        return [
            'duplicate_content_groups' => $duplicateContentGroups,
            'deleted_duplicate_samples' => $deletedDuplicateSamples,
            'updated_signature_sources' => $updatedSignatureSources,
            'renamed_samples' => $renamedSamples,
            'moved_files' => (int) ($syncResult['legacy_files_moved'] ?? 0) + (int) ($syncResult['missing_files_created'] ?? 0),
            'remaining_duplicate_content_groups' => WordPressSignatureSample::query()
                ->select('sha256')
                ->whereNotNull('sha256')
                ->where('sha256', '!=', '')
                ->groupBy('sha256')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count(),
            'remaining_duplicate_name_groups' => WordPressSignatureSample::query()
                ->select('name')
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->groupBy('name')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count(),
        ];
    }

    public function normalizeSamplePath(array $data): array
    {
        $filePath = isset($data['file_path']) && is_string($data['file_path']) ? trim($data['file_path']) : '';

        if ($filePath === '' || ! Storage::disk('local')->exists($filePath)) {
            return $data;
        }

        Storage::disk('local')->makeDirectory(self::BASE_DIRECTORY);

        $targetPath = $this->uniquePath(
            self::BASE_DIRECTORY,
            $this->preferredFilename($data, $filePath),
            $filePath,
        );

        if ($targetPath !== $filePath) {
            Storage::disk('local')->move($filePath, $targetPath);
        }

        $data['file_path'] = $targetPath;
        $data['original_filename'] = $data['original_filename'] ?? basename($targetPath);

        return $data;
    }

    /**
     * @return array{scanned_database_rows:int,missing_files_created:int,legacy_files_moved:int,tracked_files:int,untracked_files:int,missing_files:int,untracked_paths:array<int,string>,missing_paths:array<int,string>}
     */
    public function scanAndSync(bool $writeChanges = false): array
    {
        Storage::disk('local')->makeDirectory(self::BASE_DIRECTORY);

        $trackedPaths = [];
        $missingFilesCreated = 0;
        $legacyFilesMoved = 0;
        $missingPaths = [];

        foreach (WordPressSignatureSample::query()->orderBy('id')->get() as $sample) {
            $updated = false;
            $filePath = is_string($sample->file_path) ? trim($sample->file_path) : '';
            $preferredFilename = $this->preferredFilename($sample->toArray(), $filePath);

            if ($filePath !== '' && Storage::disk('local')->exists($filePath)) {
                $targetPath = $this->uniquePath(self::BASE_DIRECTORY, $preferredFilename, $filePath);

                if ($targetPath !== $filePath) {
                    if ($writeChanges) {
                        Storage::disk('local')->move($filePath, $targetPath);
                        $sample->file_path = $targetPath;
                        $updated = true;
                    }

                    $filePath = $targetPath;
                    $legacyFilesMoved++;
                }
            } elseif ((string) ($sample->content ?? '') !== '') {
                $targetPath = $this->uniquePath(self::BASE_DIRECTORY, $preferredFilename);

                if ($writeChanges) {
                    Storage::disk('local')->put($targetPath, (string) $sample->content);
                    $sample->file_path = $targetPath;
                    $updated = true;
                }

                $filePath = $targetPath;
                $missingFilesCreated++;
            } else {
                $missingPaths[] = sprintf('sample:%d:%s', $sample->id, $sample->name);
            }

            if ($updated) {
                $sample->save();
            }

            if ($filePath !== '') {
                $trackedPaths[] = $filePath;
            }
        }

        $trackedPaths = array_values(array_unique($trackedPaths));
        $filesystemPaths = $this->libraryFiles();
        $untrackedPaths = array_values(array_diff($filesystemPaths, $trackedPaths));

        return [
            'scanned_database_rows' => WordPressSignatureSample::query()->count(),
            'missing_files_created' => $writeChanges ? $missingFilesCreated : 0,
            'legacy_files_moved' => $writeChanges ? $legacyFilesMoved : 0,
            'tracked_files' => count($trackedPaths),
            'untracked_files' => count($untrackedPaths),
            'missing_files' => count($missingPaths),
            'untracked_paths' => array_slice($untrackedPaths, 0, 50),
            'missing_paths' => array_slice($missingPaths, 0, 50),
        ];
    }

    /**
     * @return array{imported:int,skipped:int,errors:array<int,string>}
     */
    public function importArchive(string $archivePath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is not available on this server.');
        }

        $fullArchivePath = Storage::disk('local')->path($archivePath);
        $zip = new ZipArchive();

        if ($zip->open($fullArchivePath) !== true) {
            throw new RuntimeException('Unable to open the uploaded ZIP archive.');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = (string) $zip->getNameIndex($index);

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $normalizedRelativePath = $this->sanitizeArchiveRelativePath($entryName);

                if ($normalizedRelativePath === null) {
                    $skipped++;
                    continue;
                }

                $content = $zip->getFromIndex($index);

                if (! is_string($content) || $content === '') {
                    $skipped++;
                    continue;
                }

                $sha256 = hash('sha256', $content);

                if ($this->duplicateSampleForSha256($sha256) instanceof WordPressSignatureSample) {
                    $skipped++;
                    continue;
                }

                $sampleName = pathinfo(basename($normalizedRelativePath), PATHINFO_FILENAME);
                $originalFilename = basename($normalizedRelativePath);
                $storedPath = $this->storeImportedContent($content, [
                    'name' => $sampleName,
                    'original_filename' => $originalFilename,
                ]);

                $sample = new WordPressSignatureSample([
                    'name' => $sampleName,
                    'sample_type' => 'malware',
                    'original_filename' => $originalFilename,
                    'file_path' => $storedPath,
                    'content' => $content,
                ]);

                $prepared = app(WordPressSignatureLabService::class)->prepareSampleData($sample->toArray());

                if ($this->duplicateSampleForSha256($prepared['sha256'] ?? null) instanceof WordPressSignatureSample) {
                    Storage::disk('local')->delete($storedPath);
                    $skipped++;
                    continue;
                }

                WordPressSignatureSample::query()->create($prepared);
                $imported++;
            }
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        } finally {
            $zip->close();
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function libraryFiles(): array
    {
        return array_values(array_filter(
            Storage::disk('local')->allFiles(self::BASE_DIRECTORY),
            function (string $path): bool {
                $relative = Str::after($path, self::BASE_DIRECTORY . '/');

                return $relative !== '' && ! str_contains($relative, '/');
            }
        ));
    }

    private function preferredFilename(array $data, string $fallbackPath = ''): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        $originalFilename = trim((string) ($data['original_filename'] ?? ''));
        $fallbackFilename = $fallbackPath !== '' ? basename($fallbackPath) : '';

        $basename = $name !== ''
            ? $this->sanitizeStem($name)
            : $this->sanitizeStem(pathinfo($originalFilename !== '' ? $originalFilename : $fallbackFilename, PATHINFO_FILENAME));

        $basename = $basename !== '' ? $basename : 'sample';
        $basename = substr($basename, 0, 160);

        $extension = strtolower((string) pathinfo($originalFilename !== '' ? $originalFilename : $fallbackFilename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        return $extension !== '' ? $basename . '.' . $extension : $basename;
    }

    private function sanitizeStem(string $value): string
    {
        return substr((preg_replace('/[^A-Za-z0-9._-]/', '-', trim($value)) ?: ''), 0, 180);
    }

    private function uniquePath(string $directory, string $filename, string $currentPath = ''): string
    {
        $filename = trim($filename, '/');
        $path = $directory . '/' . $filename;

        if ($currentPath !== '' && $path === $currentPath) {
            return $path;
        }

        if (! Storage::disk('local')->exists($path)) {
            return $path;
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        do {
            $suffix = '-' . Str::lower(Str::random(6));
            $candidate = $directory . '/' . $name . $suffix . ($extension !== '' ? '.' . $extension : '');
        } while ($candidate !== $currentPath && Storage::disk('local')->exists($candidate));

        return $candidate;
    }

    private function storeImportedContent(string $content, array $data): string
    {
        Storage::disk('local')->makeDirectory(self::BASE_DIRECTORY);
        $path = $this->uniquePath(self::BASE_DIRECTORY, $this->preferredFilename($data));
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function sanitizeArchiveRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));

        if ($segments === []) {
            return null;
        }

        foreach ($segments as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return implode('/', Arr::map($segments, function (string $segment): string {
            return preg_replace('/[^A-Za-z0-9._-]/', '-', $segment) ?: 'file';
        }));
    }

    private function selectCanonicalSample(Collection $group): WordPressSignatureSample
    {
        $preferred = $group
            ->sortBy('id')
            ->first(fn (WordPressSignatureSample $sample): bool => $this->signatureExistsForSampleName((string) $sample->name));

        return $preferred instanceof WordPressSignatureSample
            ? $preferred
            : $group->sortBy('id')->first();
    }

    private function signatureExistsForSampleName(string $name): bool
    {
        $name = trim($name);

        return $name !== '' && WordPressMalwareSignature::query()->where('name', $name)->exists();
    }

    private function updateSignatureSourceSampleName(string $oldName, string $newName): int
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if ($oldName === '' || $newName === '' || $oldName === $newName) {
            return 0;
        }

        $updated = 0;

        foreach (WordPressMalwareSignature::query()->where('notes', 'like', '%Suggested from sample:%')->get() as $signature) {
            $notes = (string) ($signature->notes ?? '');
            $count = 0;
            $newNotes = preg_replace_callback(
                '/^Suggested from sample:\s*(.+)$/mi',
                static function (array $matches) use ($oldName, $newName, &$count): string {
                    $current = trim((string) ($matches[1] ?? ''));

                    if ($current !== $oldName) {
                        return (string) $matches[0];
                    }

                    $count++;

                    return 'Suggested from sample: ' . $newName;
                },
                $notes,
                1
            );

            if ($count > 0 && is_string($newNotes) && $newNotes !== $notes) {
                $signature->notes = $newNotes;
                $signature->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function renameSignatureIfSafe(string $oldName, string $newName): void
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if ($oldName === '' || $newName === '' || $oldName === $newName) {
            return;
        }

        if (WordPressMalwareSignature::query()->where('name', $newName)->exists()) {
            return;
        }

        $signature = WordPressMalwareSignature::query()->where('name', $oldName)->first();

        if (! $signature instanceof WordPressMalwareSignature) {
            return;
        }

        $signature->name = $newName;
        $signature->save();
    }

    private function uniqueSampleName(WordPressSignatureSample $sample): string
    {
        $baseName = trim((string) $sample->name);
        $baseName = $baseName !== '' ? $this->sanitizeStem($baseName) : 'sample';
        $shaSuffix = substr((string) $sample->sha256, 0, 12);
        $baseName = substr($baseName, 0, 180);
        $candidate = substr($baseName, 0, max(1, 240 - strlen($shaSuffix !== '' ? '-' . $shaSuffix : '-' . (string) $sample->id)))
            . ($shaSuffix !== '' ? '-' . $shaSuffix : '-' . $sample->id);

        while (WordPressSignatureSample::query()
            ->where('name', $candidate)
            ->whereKeyNot($sample->id)
            ->exists()) {
            $candidate .= '-' . Str::lower(Str::random(4));
        }

        return $candidate;
    }

    private function sampleUsesFilePath(string $filePath): bool
    {
        return WordPressSignatureSample::query()->where('file_path', $filePath)->exists();
    }
}
