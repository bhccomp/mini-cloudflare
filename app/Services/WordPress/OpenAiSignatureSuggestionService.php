<?php

namespace App\Services\WordPress;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiSignatureSuggestionService
{
    /**
     * @return array{name: string, family: string, sample_type: string, notes: string}
     */
    public function suggestSampleDetails(WordPressSignatureSample $sample): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = (string) config('services.openai.signature_model', 'gpt-4o-mini');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $content = trim((string) ($sample->content ?? ''));

        if ($content === '') {
            throw new RuntimeException('This sample does not contain any text content to analyze.');
        }

        $signals = is_array($sample->signals) ? implode(', ', $sample->signals) : '';
        $trimmedContent = mb_substr($content, 0, 12000);

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You classify WordPress-related suspicious files for a security analyst. Return exactly one JSON object. Be concise and conservative.',
                    ],
                    [
                        'role' => 'user',
                        'content' => <<<PROMPT
Analyze this WordPress-related file sample and suggest metadata.

Return strict JSON with these keys:
- name
- family
- sample_type ("malware", "clean", or "false_positive")
- notes

Rules:
- Use short practical naming.
- family should be a compact family/category like staged-loader, webshell, remote-admin, injected-js, suspicious-dropper.
- notes should be 1 or 2 short sentences.
- Keep false positives low.
- No markdown.

Sample metadata:
- current name: {$sample->name}
- original filename: {$sample->original_filename}
- detected signals: {$signals}

Sample content:
{$trimmedContent}
PROMPT,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI did not return a successful response.');
        }

        $payload = $response->json();
        $contentText = (string) data_get($payload, 'choices.0.message.content', '');
        $decoded = json_decode($contentText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid JSON suggestion.');
        }

        $sampleType = in_array(($decoded['sample_type'] ?? ''), ['malware', 'clean', 'false_positive'], true)
            ? $decoded['sample_type']
            : 'malware';

        return [
            'name' => trim((string) ($decoded['name'] ?? $sample->name)),
            'family' => trim((string) ($decoded['family'] ?? '')),
            'sample_type' => $sampleType,
            'notes' => trim((string) ($decoded['notes'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestForSample(WordPressSignatureSample $sample): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = (string) config('services.openai.signature_model', 'gpt-4o-mini');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $content = trim((string) ($sample->content ?? ''));

        if ($content === '') {
            throw new RuntimeException('This sample does not contain any text content to analyze.');
        }

        $signals = is_array($sample->signals) ? implode(', ', $sample->signals) : '';
        $prompt = $this->buildPrompt($sample, $signals, $content);

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a malware-signature assistant for a WordPress security product. Return exactly one JSON object only. Do not wrap in markdown. Prefer maintainable signatures and conservative false-positive behavior. Never approve a signature, only suggest a draft.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI did not return a successful response.');
        }

        $payload = $response->json();
        $contentText = (string) data_get($payload, 'choices.0.message.content', '');
        $decoded = json_decode($contentText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid JSON suggestion.');
        }

        $pattern = isset($decoded['pattern']) && is_string($decoded['pattern']) ? trim($decoded['pattern']) : '';
        $label = isset($decoded['label']) && is_string($decoded['label']) ? trim($decoded['label']) : '';
        $type = isset($decoded['signature_type']) && in_array($decoded['signature_type'], ['high_confidence', 'heuristic'], true)
            ? $decoded['signature_type']
            : 'heuristic';
        $score = max(1, min(10, (int) ($decoded['score'] ?? 1)));
        $name = isset($decoded['name']) && is_string($decoded['name']) && trim($decoded['name']) !== ''
            ? trim($decoded['name'])
            : ('AI suggestion for ' . $sample->name);
        $reasoning = isset($decoded['reasoning']) && is_string($decoded['reasoning']) ? trim($decoded['reasoning']) : '';
        $risk = isset($decoded['false_positive_risk']) && is_string($decoded['false_positive_risk']) ? trim($decoded['false_positive_risk']) : 'unknown';

        if ($pattern === '' || $label === '' || @preg_match($pattern, '') === false) {
            throw new RuntimeException('OpenAI returned a malformed regex suggestion.');
        }

        $existingSignature = WordPressMalwareSignature::query()
            ->where('pattern', $pattern)
            ->orWhere(function ($query) use ($label, $type): void {
                $query->where('label', $label)->where('signature_type', $type);
            })
            ->first();

        if ($existingSignature) {
            throw new RuntimeException(sprintf(
                'A similar signature already exists: %s (%s).',
                $existingSignature->name,
                $existingSignature->status
            ));
        }

        $signature = WordPressMalwareSignature::query()->create([
            'name' => $name,
            'signature_type' => $type,
            'pattern' => $pattern,
            'label' => $label,
            'score' => $type === 'heuristic' ? $score : null,
            'status' => 'draft',
            'source' => 'ai',
            'notes' => trim("Suggested from sample: {$sample->name}\nFalse-positive risk: {$risk}\n\n{$reasoning}"),
        ]);

        return [
            'signature' => $signature,
            'risk' => $risk,
        ];
    }

    /**
     * @return array{signature: WordPressMalwareSignature, risk: string}
     */
    public function reviseSignature(WordPressMalwareSignature $signature): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = (string) config('services.openai.signature_model', 'gpt-4o-mini');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $testResult = is_array($signature->last_test_result) ? $signature->last_test_result : null;

        if (! $testResult) {
            throw new RuntimeException('Run Test Set before asking for an AI revision.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You revise malware-signature drafts for a WordPress security product. Return exactly one JSON object only. Never approve signatures. Revise conservatively and reduce false positives.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildRevisionPrompt($signature, $testResult),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI did not return a successful response.');
        }

        $payload = $response->json();
        $contentText = (string) data_get($payload, 'choices.0.message.content', '');
        $decoded = json_decode($contentText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid revision suggestion.');
        }

        $pattern = isset($decoded['pattern']) && is_string($decoded['pattern']) ? trim($decoded['pattern']) : '';
        $label = isset($decoded['label']) && is_string($decoded['label']) ? trim($decoded['label']) : '';
        $type = isset($decoded['signature_type']) && in_array($decoded['signature_type'], ['high_confidence', 'heuristic'], true)
            ? $decoded['signature_type']
            : $signature->signature_type;
        $score = max(1, min(10, (int) ($decoded['score'] ?? ($signature->score ?? 1))));
        $name = isset($decoded['name']) && is_string($decoded['name']) && trim($decoded['name']) !== ''
            ? trim($decoded['name'])
            : $signature->name;
        $reasoning = isset($decoded['reasoning']) && is_string($decoded['reasoning']) ? trim($decoded['reasoning']) : '';
        $risk = isset($decoded['false_positive_risk']) && is_string($decoded['false_positive_risk']) ? trim($decoded['false_positive_risk']) : 'unknown';

        if ($pattern === '' || $label === '' || @preg_match($pattern, '') === false) {
            throw new RuntimeException('OpenAI returned a malformed revised regex.');
        }

        $existingSignature = WordPressMalwareSignature::query()
            ->whereKeyNot($signature->getKey())
            ->where('pattern', $pattern)
            ->orWhere(function ($query) use ($signature, $label, $type): void {
                $query->whereKeyNot($signature->getKey())
                    ->where('label', $label)
                    ->where('signature_type', $type);
            })
            ->first();

        if ($existingSignature) {
            throw new RuntimeException(sprintf(
                'A similar signature already exists: %s (%s).',
                $existingSignature->name,
                $existingSignature->status
            ));
        }

        $signature->forceFill([
            'name' => $name,
            'signature_type' => $type,
            'pattern' => $pattern,
            'label' => $label,
            'score' => $type === 'heuristic' ? $score : null,
            'status' => 'draft',
            'source' => 'ai',
            'notes' => trim(($signature->notes ? $signature->notes . "\n\n" : '') . "AI revision\nFalse-positive risk: {$risk}\n\n{$reasoning}"),
        ])->save();

        return [
            'signature' => $signature->fresh(),
            'risk' => $risk,
        ];
    }

    private function buildPrompt(WordPressSignatureSample $sample, string $signals, string $content): string
    {
        $trimmedContent = mb_substr($content, 0, 12000);
        $engineContext = $this->engineContext();
        $existingSignatures = $this->existingSignatureContext();

        return <<<PROMPT
Analyze this WordPress-related file sample and suggest one regex signature draft.

Return strict JSON with these keys:
- name
- signature_type ("high_confidence" or "heuristic")
- label
- pattern
- score
- false_positive_risk
- reasoning

Rules:
- Use a PHP-compatible regex pattern including delimiters and modifiers.
- Prefer a conservative signature.
- If the file looks more like a suspicious admin/loader than generic malware, say so in reasoning but still provide the best draft pattern.
- High confidence signatures should target very specific loader/webshell markers.
- Heuristic signatures should be broader and use a score from 1 to 10.
- Match the FirePhage scanner engine described below. Do not invent signature types outside that engine.
- Prefer complementing the existing engine instead of duplicating a pattern that already exists.
- Keep WordPress false positives low. Avoid broad signatures that could match normal plugins or themes.
- Many malware samples are multiline. Do not assume `.*` crosses newlines.
- If you need to span lines, prefer `[\s\S]` with a bounded range like `{0,800}` or use the `s` modifier deliberately.
- Before returning, sanity-check that your proposed regex would match the supplied sample content, not just a hypothetical variant.
- Prefer specific bounded multiline patterns over loose greedy matches.
- No markdown.

FirePhage scanner engine context:
{$engineContext}

Existing database signatures to avoid duplicating:
{$existingSignatures}

Sample metadata:
- name: {$sample->name}
- type: {$sample->sample_type}
- family: {$sample->family}
- language: {$sample->language}
- detected signals: {$signals}

Sample content:
{$trimmedContent}
PROMPT;
    }

    private function engineContext(): string
    {
        $manifest = config('firephage-wordpress-signatures', []);
        $highConfidence = array_slice(array_keys($manifest['high_confidence_patterns'] ?? []), 0, 12);
        $heuristics = array_slice($manifest['heuristic_patterns'] ?? [], 0, 16);

        $heuristicLines = [];

        foreach ($heuristics as $pattern => $config) {
            if (! is_string($pattern) || ! is_array($config)) {
                continue;
            }

            $heuristicLines[] = sprintf(
                '- %s | label: %s | score: %d',
                $pattern,
                (string) ($config['label'] ?? ''),
                (int) ($config['score'] ?? 0),
            );
        }

        return implode("\n", [
            '- The scanner has two signature types only: high_confidence and heuristic.',
            '- high_confidence is for very specific malware or webshell indicators that should stand on their own.',
            '- heuristic signatures are weighted and combined with other signals in the plugin scanner.',
            '- heuristic scores are usually low and additive, commonly 1 to 3.',
            '- Existing high_confidence patterns:',
            ...array_map(static fn (string $pattern): string => '- ' . $pattern, $highConfidence),
            '- Existing heuristic patterns:',
            ...$heuristicLines,
        ]);
    }

    private function existingSignatureContext(): string
    {
        $signatures = WordPressMalwareSignature::query()
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get(['name', 'signature_type', 'label', 'status']);

        if ($signatures->isEmpty()) {
            return '- No database signatures exist yet.';
        }

        return $signatures
            ->map(static fn (WordPressMalwareSignature $signature): string => sprintf(
                '- %s | %s | %s | %s',
                $signature->name,
                $signature->signature_type,
                $signature->label,
                $signature->status
            ))
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $testResult
     */
    private function buildRevisionPrompt(WordPressMalwareSignature $signature, array $testResult): string
    {
        $engineContext = $this->engineContext();
        $existingSignatures = $this->existingSignatureContext();
        $sampleContext = $this->sampleContext();
        $summary = $testResult['summary'] ?? [];
        $matchedSamples = $testResult['matched_samples'] ?? [];

        return <<<PROMPT
Revise this WordPress malware-signature draft based on its latest test-set result.

Return strict JSON with these keys:
- name
- signature_type ("high_confidence" or "heuristic")
- label
- pattern
- score
- false_positive_risk
- reasoning

Rules:
- Use a PHP-compatible regex pattern including delimiters and modifiers.
- Stay within the FirePhage scanner engine described below.
- Prefer improving coverage without creating WordPress false positives.
- Avoid duplicating existing signatures.
- Many malware samples are multiline. Do not assume `.*` crosses newlines.
- If you need to span lines, prefer `[\s\S]` with a bounded range like `{0,800}` or use the `s` modifier deliberately.
- If the previous signature failed to match malware hits, explicitly fix the likely reason instead of making only cosmetic changes.
- Before returning, sanity-check that your revised regex would match the relevant sample style represented in the stored sample library and test feedback.
- No markdown.

Current signature:
- name: {$signature->name}
- type: {$signature->signature_type}
- label: {$signature->label}
- score: {$signature->score}
- pattern: {$signature->pattern}
- notes: {$signature->notes}

Latest test result:
- malware_hits: {$summary['malware_hits']}
- clean_hits: {$summary['clean_hits']}
- false_positive_hits: {$summary['false_positive_hits']}
- risk: {$testResult['risk']}
- matched_samples: {$this->json($matchedSamples)}

FirePhage scanner engine context:
{$engineContext}

Existing database signatures to avoid duplicating:
{$existingSignatures}

Recent sample library context:
{$sampleContext}
PROMPT;
    }

    private function sampleContext(): string
    {
        $samples = WordPressSignatureSample::query()
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get(['name', 'sample_type', 'family', 'language', 'signals']);

        if ($samples->isEmpty()) {
            return '- No stored samples yet.';
        }

        return $samples
            ->map(static fn (WordPressSignatureSample $sample): string => sprintf(
                '- %s | %s | %s | %s | signals: %s',
                $sample->name,
                $sample->sample_type,
                $sample->family ?: 'n/a',
                $sample->language ?: 'n/a',
                is_array($sample->signals) ? implode(', ', $sample->signals) : 'none'
            ))
            ->implode("\n");
    }

    /**
     * @param  mixed  $value
     */
    private function json($value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '[]';
    }
}
