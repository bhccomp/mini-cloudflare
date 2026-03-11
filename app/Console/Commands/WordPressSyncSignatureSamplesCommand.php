<?php

namespace App\Console\Commands;

use App\Services\WordPress\WordPressSignatureSampleStorageService;
use Illuminate\Console\Command;

class WordPressSyncSignatureSamplesCommand extends Command
{
    protected $signature = 'wordpress:sync-signature-samples {--write : Create missing files and move legacy files into the managed directories}';

    protected $description = 'Scan or sync the WordPress signature sample library between the database and filesystem.';

    public function handle(WordPressSignatureSampleStorageService $service): int
    {
        $result = $service->scanAndSync((bool) $this->option('write'));

        $this->info(sprintf('Scanned database rows: %d', (int) ($result['scanned_database_rows'] ?? 0)));
        $this->info(sprintf('Tracked files: %d', (int) ($result['tracked_files'] ?? 0)));
        $this->info(sprintf('Missing files: %d', (int) ($result['missing_files'] ?? 0)));
        $this->info(sprintf('Untracked files: %d', (int) ($result['untracked_files'] ?? 0)));

        if ($this->option('write')) {
            $this->info(sprintf('Missing files created: %d', (int) ($result['missing_files_created'] ?? 0)));
            $this->info(sprintf('Legacy files moved: %d', (int) ($result['legacy_files_moved'] ?? 0)));
        }

        return self::SUCCESS;
    }
}
