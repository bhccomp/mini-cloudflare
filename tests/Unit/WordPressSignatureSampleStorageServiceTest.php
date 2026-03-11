<?php

namespace Tests\Unit;

use App\Models\WordPressSignatureSample;
use App\Services\WordPress\WordPressSignatureSampleStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressSignatureSampleStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_and_sync_backfills_database_only_samples_and_renames_files_into_flat_library(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('wordpress-signature-samples/legacy.php', '<?php echo "legacy";');

        WordPressSignatureSample::query()->create([
            'name' => 'ajaxshell_file_manager_behavior',
            'sample_type' => 'malware',
            'file_path' => 'wordpress-signature-samples/legacy.php',
            'content' => '<?php echo "legacy";',
        ]);

        WordPressSignatureSample::query()->create([
            'name' => 'repo_eval_base64_direct',
            'sample_type' => 'malware',
            'original_filename' => 'dropper.php',
            'content' => '<?php echo "dropper";',
        ]);

        $result = app(WordPressSignatureSampleStorageService::class)->scanAndSync(true);

        $this->assertSame(1, $result['missing_files_created']);
        $this->assertSame(1, $result['legacy_files_moved']);
        $this->assertSame(0, $result['untracked_files']);

        $samples = WordPressSignatureSample::query()->orderBy('id')->get();

        $this->assertSame('wordpress-signature-samples/ajaxshell_file_manager_behavior.php', $samples[0]->file_path);
        $this->assertSame('wordpress-signature-samples/repo_eval_base64_direct.php', $samples[1]->file_path);
        Storage::disk('local')->assertExists('wordpress-signature-samples/ajaxshell_file_manager_behavior.php');
        Storage::disk('local')->assertExists('wordpress-signature-samples/repo_eval_base64_direct.php');
    }

    public function test_zip_import_creates_flat_sample_rows_in_root_library(): void
    {
        Storage::fake('local');

        $zipPath = Storage::disk('local')->path('wordpress-signature-samples/tmp/plugin.zip');
        Storage::disk('local')->makeDirectory('wordpress-signature-samples/tmp');

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('one/backdoor.php', '<?php echo "one";');
        $zip->addFromString('two/dropper.js', 'alert("two");');
        $zip->close();

        $result = app(WordPressSignatureSampleStorageService::class)->importArchive(
            'wordpress-signature-samples/tmp/plugin.zip',
        );

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, WordPressSignatureSample::query()->count());
        Storage::disk('local')->assertExists('wordpress-signature-samples/backdoor.php');
        Storage::disk('local')->assertExists('wordpress-signature-samples/dropper.js');
    }
}
