<?php

namespace Tests\Unit;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use App\Services\WordPress\WordPressSignatureLabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressSignatureLabServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_test_set_records_exact_sample_matches_without_wordpress_corpus_scanning(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put(
            'wordpress-signature-samples/sample-webshell.php',
            '<?php eval(base64_decode($payload));'
        );

        WordPressSignatureSample::query()->create([
            'name' => 'sample-webshell',
            'sample_type' => 'malware',
            'file_path' => 'wordpress-signature-samples/sample-webshell.php',
            'content' => '',
        ]);

        $signature = WordPressMalwareSignature::query()->create([
            'name' => 'sample-webshell',
            'signature_type' => 'high_confidence',
            'pattern' => '/eval\s*\(\s*base64_decode\s*\(/i',
            'label' => 'Eval base64 loader',
            'status' => 'draft',
            'source' => 'manual',
        ]);

        $result = app(WordPressSignatureLabService::class)->testSignature($signature);

        $this->assertSame(1, $result['summary']['malware_hits']);
        $this->assertSame(0, $result['summary']['wordpress_core_hits']);
        $this->assertTrue($result['production_ready']);
        $this->assertCount(1, $result['matched_files']);
        $this->assertSame('wordpress-signature-samples/sample-webshell.php', $result['matched_samples'][0]['file_path']);
        $this->assertSame([], $result['matched_wordpress_files']);

        $signature->refresh();

        $this->assertIsArray($signature->last_test_result);
        $this->assertSame(0, $signature->last_test_result['summary']['wordpress_core_hits']);
        $this->assertIsArray($signature->test_history);
        $this->assertCount(1, $signature->test_history);
        $this->assertSame(0, $signature->test_history[0]['summary']['wordpress_core_hits']);
    }
}
