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

    private function buildPrompt(WordPressSignatureSample $sample, string $signals, string $content): string
    {
        $trimmedContent = mb_substr($content, 0, 12000);
        $engineContext = $this->engineContext();

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
- No markdown.

FirePhage scanner engine context:
{$engineContext}

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
}
