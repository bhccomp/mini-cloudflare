<?php

namespace App\Services\WordPress;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WordPressSignatureLabService
{
    private const SAMPLE_DIRECTORY = WordPressSignatureSampleStorageService::BASE_DIRECTORY;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareSampleData(array $data): array
    {
        $content = isset($data['content']) && is_string($data['content']) ? trim($data['content']) : '';
        $filePath = isset($data['file_path']) && is_string($data['file_path']) ? $data['file_path'] : null;

        if ($filePath !== null && $filePath !== '' && Storage::disk('local')->exists($filePath)) {
            $content = (string) Storage::disk('local')->get($filePath);
            $data['size_bytes'] = (int) Storage::disk('local')->size($filePath);

            if ((! isset($data['original_filename']) || ! is_string($data['original_filename']) || trim($data['original_filename']) === '')
                && basename($filePath) !== '') {
                $data['original_filename'] = basename($filePath);
            }
        } else {
            $data['size_bytes'] = strlen($content);
        }

        $name = isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
            ? $data['name']
            : (isset($data['original_filename']) && is_string($data['original_filename']) && $data['original_filename'] !== ''
                ? $data['original_filename']
                : 'Sample ' . Str::upper(Str::random(6)));

        $data['name'] = $name;
        $data['content'] = $content;
        $data['sha256'] = $content !== '' ? hash('sha256', $content) : null;
        $data['language'] = $this->detectLanguage($name, $content);
        $data['signals'] = $this->extractSignals($content);

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public function extractSignals(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $signalPatterns = [
            '/eval\s*\(/i' => 'eval() execution',
            '/base64_decode\s*\(/i' => 'base64 decode helper',
            '/gzinflate\s*\(/i' => 'compressed payload helper',
            '/\bnew\s+Function\s*\(/i' => 'dynamic JavaScript compilation',
            '/\b(?:system|shell_exec|passthru|proc_open|popen)\s*\(/i' => 'system command execution',
            '/php:\/\/input/i' => 'raw request body access',
            '/\b(?:FilesMan|auth_pass)\b/i' => 'known webshell marker',
            '/(?:move_uploaded_file|file_put_contents|chmod|rename|touch)\s*\(/i' => 'file manager behavior',
            '/\batob\s*\(/i' => 'JavaScript base64 decode',
            '/(?:document\.write|String\.fromCharCode)\s*\(/i' => 'obfuscated JavaScript output',
        ];

        $signals = [];

        foreach ($signalPatterns as $pattern => $label) {
            if (@preg_match($pattern, $content) === 1) {
                $signals[] = $label;
            }
        }

        return array_slice(array_values(array_unique($signals)), 0, 12);
    }

    /**
     * @return array<string, mixed>
     */
    public function testSignature(WordPressMalwareSignature $signature): array
    {
        $samples = WordPressSignatureSample::query()->orderBy('id')->get();
        $sampleMatches = [];
        $wpMatches = [];
        $summary = [
            'sample_count' => $samples->count(),
            'malware_hits' => 0,
            'clean_hits' => 0,
            'false_positive_hits' => 0,
            'wordpress_core_hits' => 0,
        ];

        foreach ($samples as $sample) {
            $content = $this->sampleContent($sample);

            if ($content === '' || @preg_match($signature->pattern, '') === false) {
                continue;
            }

            $matched = @preg_match($signature->pattern, $content) === 1;

            if (! $matched) {
                continue;
            }

            if ($sample->sample_type === 'malware') {
                $summary['malware_hits']++;
            } elseif ($sample->sample_type === 'clean') {
                $summary['clean_hits']++;
            } else {
                $summary['false_positive_hits']++;
            }

            $sampleMatches[] = [
                'sample' => $sample->name,
                'type' => $sample->sample_type,
                'family' => $sample->family,
                'original_filename' => $sample->original_filename,
                'file_path' => $sample->file_path,
                'source' => 'sample_library',
            ];
        }

        $hasBlockingCleanHit = $summary['clean_hits'] > 0
            || $summary['false_positive_hits'] > 0
            || $summary['wordpress_core_hits'] > 0;

        $risk = $hasBlockingCleanHit
            ? 'high'
            : ($summary['malware_hits'] > 0 ? 'low' : 'unknown');

        $result = [
            'summary' => $summary,
            'risk' => $risk,
            'production_ready' => ! $hasBlockingCleanHit && $summary['malware_hits'] > 0,
            'outcome' => $hasBlockingCleanHit ? 'failed' : ($summary['malware_hits'] > 0 ? 'pass' : 'ineffective'),
            'matched_samples' => $sampleMatches,
            'matched_wordpress_files' => $wpMatches,
            'matched_files' => array_merge($sampleMatches, $wpMatches),
            'tested_at' => now()->toIso8601String(),
        ];

        $history = is_array($signature->test_history) ? $signature->test_history : [];
        array_unshift($history, $result);
        $history = array_slice($history, 0, 25);

        $signature->forceFill([
            'last_tested_at' => now(),
            'last_test_result' => $result,
            'test_history' => $history,
            'status' => $this->statusAfterTest($signature, $result),
        ])->save();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function productionManifest(): array
    {
        $fallback = config('firephage-wordpress-signatures', []);
        $approved = WordPressMalwareSignature::query()
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();
        $exactHashes = [];

        $highConfidence = [];
        $heuristics = [];

        foreach ($approved as $signature) {
            if ($signature->signature_type === 'high_confidence') {
                $highConfidence[$signature->pattern] = $signature->label;
                continue;
            }

            $heuristics[$signature->pattern] = [
                'label' => $signature->label,
                'score' => max(1, (int) ($signature->score ?? 1)),
            ];
        }

        foreach (WordPressSignatureSample::query()
            ->where('sample_type', 'malware')
            ->whereNotNull('sha256')
            ->where('sha256', '!=', '')
            ->orderBy('id')
            ->get(['name', 'sha256']) as $sample) {
            $exactHashes[strtolower((string) $sample->sha256)] = (string) $sample->name;
        }

        return [
            'version' => now()->format('Y.m.d.His'),
            'high_confidence_hashes' => array_replace($fallback['high_confidence_hashes'] ?? [], $exactHashes),
            'high_confidence_patterns' => array_replace($fallback['high_confidence_patterns'] ?? [], $highConfidence),
            'heuristic_patterns' => array_replace($fallback['heuristic_patterns'] ?? [], $heuristics),
        ];
    }

    private function detectLanguage(string $name, string $content): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match ($extension) {
            'js' => 'javascript',
            'php', 'phtml', 'php5', 'php7', 'php8', 'inc' => 'php',
            default => str_contains($content, '<?php') ? 'php' : (str_contains($content, 'function(') || str_contains($content, 'const ') ? 'javascript' : 'text'),
        };
    }

    private function sampleContent(WordPressSignatureSample $sample): string
    {
        $filePath = is_string($sample->file_path) ? trim($sample->file_path) : '';

        if ($filePath !== '' && Storage::disk('local')->exists($filePath)) {
            return (string) Storage::disk('local')->get($filePath);
        }

        return (string) ($sample->content ?? '');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function statusAfterTest(WordPressMalwareSignature $signature, array $result): string
    {
        $currentStatus = (string) ($signature->status ?? 'draft');
        $outcome = (string) ($result['outcome'] ?? 'pass');

        if ($outcome === 'failed') {
            return 'failed';
        }

        if ($outcome === 'ineffective') {
            return 'ineffective';
        }

        return match ($currentStatus) {
            'approved', 'disabled', 'archived' => $currentStatus,
            default => 'draft',
        };
    }
}
