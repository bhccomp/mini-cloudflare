<?php

namespace App\Services\WordPress;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiSignatureSuggestionService
{
    private const SAMPLE_CONTENT_LIMIT = 12000;

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
        $trimmedContent = mb_substr($content, 0, self::SAMPLE_CONTENT_LIMIT);

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

        $decoded = $this->requestJsonSuggestion($apiKey, $model, 'You are a malware-signature assistant for a WordPress security product. Return exactly one JSON object only. Do not wrap in markdown. Prefer maintainable signatures and conservative false-positive behavior. Never approve a signature, only suggest a draft.', $prompt);

        $candidate = $this->normalizeSignatureCandidate($decoded, 'AI suggestion for ' . $sample->name, 'OpenAI returned a malformed regex suggestion.');

        $validation = $this->validatePatternAgainstContents($candidate['pattern'], [$content]);

        if (! $validation['matches_any']) {
            $candidate = $this->correctCandidateForSample(
                apiKey: $apiKey,
                model: $model,
                sample: $sample,
                previousSuggestion: $decoded,
                content: $content,
                defaultName: 'AI suggestion for ' . $sample->name,
                failureReason: sprintf('The suggested regex did not match the originating sample content. %s', $validation['reason'])
            );
        }

        $existingSignature = WordPressMalwareSignature::query()
            ->where('pattern', $candidate['pattern'])
            ->orWhere(function ($query) use ($candidate): void {
                $query->where('label', $candidate['label'])->where('signature_type', $candidate['signature_type']);
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
            'name' => $candidate['name'],
            'signature_type' => $candidate['signature_type'],
            'pattern' => $candidate['pattern'],
            'label' => $candidate['label'],
            'score' => $candidate['signature_type'] === 'heuristic' ? $candidate['score'] : null,
            'status' => 'draft',
            'source' => 'ai',
            'notes' => trim("Suggested from sample: {$sample->name}\nFalse-positive risk: {$candidate['false_positive_risk']}\n\n{$candidate['reasoning']}"),
        ]);

        return [
            'signature' => $signature,
            'risk' => $candidate['false_positive_risk'],
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

        $decoded = $this->requestJsonSuggestion($apiKey, $model, 'You revise malware-signature drafts for a WordPress security product. Return exactly one JSON object only. Never approve signatures. Revise conservatively and reduce false positives.', $this->buildRevisionPrompt($signature, $testResult));
        $candidate = $this->normalizeSignatureCandidate($decoded, $signature->name, 'OpenAI returned a malformed revised regex.', $signature->signature_type, (int) ($signature->score ?? 1));

        $malwareSamples = WordPressSignatureSample::query()
            ->where('sample_type', 'malware')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['name', 'family', 'content']);

        $validation = $this->validatePatternAgainstSamples($candidate['pattern'], $malwareSamples->all());
        $currentSummary = $this->testSummaryFromResult($testResult);
        $sourceSampleName = $this->extractSourceSampleName($signature);
        $mustNarrow = $this->revisionMustNarrow($signature, $currentSummary);

        if (
            (($testResult['summary']['malware_hits'] ?? 0) === 0 && ! $validation['matches_any'])
            || ($mustNarrow && ! $this->isNarrowerRevision($validation, $currentSummary, $sourceSampleName))
        ) {
            $candidate = $this->correctCandidateForRevision(
                apiKey: $apiKey,
                model: $model,
                signature: $signature,
                previousSuggestion: $decoded,
                testResult: $testResult,
                malwareSamples: $malwareSamples->all(),
                currentSummary: $currentSummary,
                sourceSampleName: $sourceSampleName,
                failureReason: (($testResult['summary']['malware_hits'] ?? 0) === 0 && ! $validation['matches_any'])
                    ? sprintf('The revised regex still does not match any stored malware sample. %s', $validation['reason'])
                    : sprintf('The revised regex still overlaps too broadly across malware families. It matched %d sample(s) across %d family/families.', $validation['matched_count'], count($validation['families']))
            );
        }

        $existingSignature = WordPressMalwareSignature::query()
            ->whereKeyNot($signature->getKey())
            ->where('pattern', $candidate['pattern'])
            ->orWhere(function ($query) use ($signature, $candidate): void {
                $query->whereKeyNot($signature->getKey())
                    ->where('label', $candidate['label'])
                    ->where('signature_type', $candidate['signature_type']);
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
            'name' => $candidate['name'],
            'signature_type' => $candidate['signature_type'],
            'pattern' => $candidate['pattern'],
            'label' => $candidate['label'],
            'score' => $candidate['signature_type'] === 'heuristic' ? $candidate['score'] : null,
            'status' => 'draft',
            'source' => 'ai',
            'notes' => trim(($signature->notes ? $signature->notes . "\n\n" : '') . "AI revision\nFalse-positive risk: {$candidate['false_positive_risk']}\n\n{$candidate['reasoning']}"),
        ])->save();

        return [
            'signature' => $signature->fresh(),
            'risk' => $candidate['false_positive_risk'],
        ];
    }

    /**
     * @return array{verdict: string, overlap_risk: string, recommendation: string, reasoning: string}
     */
    public function reviewTestResults(WordPressMalwareSignature $signature): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = (string) config('services.openai.signature_model', 'gpt-4o-mini');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $testResult = is_array($signature->last_test_result) ? $signature->last_test_result : null;

        if (! $testResult) {
            throw new RuntimeException('Run Test Set before asking for an AI review.');
        }

        $decoded = $this->requestJsonSuggestion(
            $apiKey,
            $model,
            'You review malware-signature test results for a WordPress security product. Return exactly one JSON object only. Be concise, practical, and conservative.',
            $this->buildTestReviewPrompt($signature, $testResult)
        );

        return [
            'verdict' => trim((string) ($decoded['verdict'] ?? 'Needs manual review')),
            'overlap_risk' => trim((string) ($decoded['overlap_risk'] ?? 'unknown')),
            'recommendation' => trim((string) ($decoded['recommendation'] ?? 'Review the matched samples before approving the signature.')),
            'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
        ];
    }

    private function buildPrompt(WordPressSignatureSample $sample, string $signals, string $content): string
    {
        $trimmedContent = mb_substr($content, 0, self::SAMPLE_CONTENT_LIMIT);
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
        $lastAiReview = is_array($signature->last_ai_review) ? $signature->last_ai_review : null;
        $aiReviewContext = $lastAiReview ? $this->json($lastAiReview) : 'null';

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
- If the saved AI review says the signature is broad or should be narrowed, reduce overlap across unrelated malware families instead of returning another generic dangerous-function list.
- When narrowing, prefer the originating sample behavior and specific strings or flows over generic file-manager patterns.
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

Saved AI review of this test result:
{$aiReviewContext}

FirePhage scanner engine context:
{$engineContext}

Existing database signatures to avoid duplicating:
{$existingSignatures}

Recent sample library context:
{$sampleContext}

Matched malware sample excerpts:
{$this->matchedSampleExcerptContext(is_array($matchedSamples) ? $matchedSamples : [])}

Recent malware sample excerpts:
{$this->sampleExcerptContext('malware')}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $testResult
     */
    private function buildTestReviewPrompt(WordPressMalwareSignature $signature, array $testResult): string
    {
        $summary = $testResult['summary'] ?? [];
        $matchedSamples = is_array($testResult['matched_samples'] ?? null) ? $testResult['matched_samples'] : [];

        return <<<PROMPT
Review this WordPress malware-signature test result and determine whether the current hit pattern looks healthy or too broad.

Return strict JSON with these keys:
- verdict
- overlap_risk
- recommendation
- reasoning

Rules:
- Keep the answer concise.
- Focus on whether multiple malware hits look like good family coverage or suspicious overlap/breadth.
- Remember that one live WordPress file may match multiple signatures and should still only become one finding row in the plugin.
- Mention if the signature should stay as-is, be narrowed, or simply be monitored.
- No markdown.

Current signature:
- name: {$signature->name}
- type: {$signature->signature_type}
- label: {$signature->label}
- score: {$signature->score}
- pattern: {$signature->pattern}

Latest test result:
{$this->json($testResult)}

Matched sample excerpts:
{$this->matchedSampleExcerptContext($matchedSamples)}

Existing database signatures:
{$this->existingSignatureContext()}
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

    private function sampleExcerptContext(string $sampleType, int $limit = 4): string
    {
        $samples = WordPressSignatureSample::query()
            ->where('sample_type', $sampleType)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['name', 'content']);

        if ($samples->isEmpty()) {
            return '- No sample excerpts available.';
        }

        return $samples
            ->map(function (WordPressSignatureSample $sample): string {
                $excerpt = mb_substr((string) $sample->content, 0, 1800);

                return sprintf("- %s\n%s", $sample->name, $excerpt);
            })
            ->implode("\n\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $matchedSamples
     */
    private function matchedSampleExcerptContext(array $matchedSamples): string
    {
        $names = collect($matchedSamples)
            ->pluck('sample')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return '- No matched sample excerpts available.';
        }

        $samples = WordPressSignatureSample::query()
            ->whereIn('name', $names)
            ->orderBy('id', 'desc')
            ->get(['name', 'family', 'sample_type', 'content']);

        if ($samples->isEmpty()) {
            return '- No matched sample excerpts available.';
        }

        return $samples
            ->map(function (WordPressSignatureSample $sample): string {
                $excerpt = mb_substr((string) $sample->content, 0, 1200);

                return sprintf(
                    "- %s | %s | %s\n%s",
                    $sample->name,
                    $sample->sample_type ?: 'unknown',
                    $sample->family ?: 'n/a',
                    $excerpt
                );
            })
            ->implode("\n\n");
    }

    /**
     * @param  array<int, WordPressSignatureSample>  $samples
     * @return array{matches_any: bool, reason: string, matched_count: int, families: array<int, string>, sample_names: array<int, string>}
     */
    private function validatePatternAgainstSamples(string $pattern, array $samples): array
    {
        if (@preg_match($pattern, '') === false) {
            return ['matches_any' => false, 'reason' => 'The regex is invalid.', 'matched_count' => 0, 'families' => [], 'sample_names' => []];
        }

        $matchedNames = [];
        $families = [];

        foreach ($samples as $sample) {
            if (! $sample instanceof WordPressSignatureSample) {
                continue;
            }

            $content = (string) $sample->content;

            if (trim($content) === '') {
                continue;
            }

            if (@preg_match($pattern, $content) === 1) {
                $matchedNames[] = (string) $sample->name;
                $family = trim((string) $sample->family);

                if ($family !== '') {
                    $families[] = $family;
                }
            }
        }

        $matchedNames = array_values(array_unique($matchedNames));
        $families = array_values(array_unique($families));

        return [
            'matches_any' => $matchedNames !== [],
            'reason' => $matchedNames !== [] ? 'The regex matched at least one malware sample.' : 'The regex did not match any stored malware sample.',
            'matched_count' => count($matchedNames),
            'families' => $families,
            'sample_names' => $matchedNames,
        ];
    }

    /**
     * @param  array<string, mixed>  $testResult
     * @return array{matched_count: int, families: array<int, string>, sample_names: array<int, string>}
     */
    private function testSummaryFromResult(array $testResult): array
    {
        $matchedSamples = is_array($testResult['matched_samples'] ?? null) ? $testResult['matched_samples'] : [];
        $families = [];
        $names = [];

        foreach ($matchedSamples as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $name = trim((string) ($sample['sample'] ?? ''));
            $family = trim((string) ($sample['family'] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }

            if ($family !== '') {
                $families[] = $family;
            }
        }

        return [
            'matched_count' => count(array_unique($names)),
            'families' => array_values(array_unique($families)),
            'sample_names' => array_values(array_unique($names)),
        ];
    }

    /**
     * @param  array{matched_count: int, families: array<int, string>, sample_names: array<int, string>}  $currentSummary
     */
    private function revisionMustNarrow(WordPressMalwareSignature $signature, array $currentSummary): bool
    {
        $review = is_array($signature->last_ai_review) ? $signature->last_ai_review : [];
        $recommendation = strtolower(trim((string) ($review['recommendation'] ?? '')));
        $overlapRisk = strtolower(trim((string) ($review['overlap_risk'] ?? '')));

        return ($recommendation === 'narrow' || in_array($overlapRisk, ['medium', 'high'], true))
            && (($currentSummary['matched_count'] ?? 0) > 1 || count($currentSummary['families'] ?? []) > 1);
    }

    /**
     * @param  array{matches_any: bool, reason: string, matched_count: int, families: array<int, string>, sample_names: array<int, string>}  $newSummary
     * @param  array{matched_count: int, families: array<int, string>, sample_names: array<int, string>}  $currentSummary
     */
    private function isNarrowerRevision(array $newSummary, array $currentSummary, ?string $sourceSampleName): bool
    {
        if (! $newSummary['matches_any']) {
            return false;
        }

        if ($sourceSampleName !== null && $sourceSampleName !== '' && ! in_array($sourceSampleName, $newSummary['sample_names'], true)) {
            return false;
        }

        $currentFamilies = count($currentSummary['families']);
        $newFamilies = count($newSummary['families']);

        if ($newFamilies > 0 && $currentFamilies > 0 && $newFamilies < $currentFamilies) {
            return true;
        }

        return $newSummary['matched_count'] < $currentSummary['matched_count'];
    }

    private function extractSourceSampleName(WordPressMalwareSignature $signature): ?string
    {
        $notes = (string) ($signature->notes ?? '');

        if (preg_match('/Suggested from sample:\s*(.+)/i', $notes, $matches) !== 1) {
            return null;
        }

        $name = trim((string) ($matches[1] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @return array{name: string, signature_type: string, label: string, pattern: string, score: int, false_positive_risk: string, reasoning: string}
     */
    private function normalizeSignatureCandidate(array $decoded, string $defaultName, string $errorMessage, string $defaultType = 'heuristic', int $defaultScore = 1): array
    {
        $pattern = isset($decoded['pattern']) && is_string($decoded['pattern']) ? trim($decoded['pattern']) : '';
        $label = isset($decoded['label']) && is_string($decoded['label']) ? trim($decoded['label']) : '';
        $type = isset($decoded['signature_type']) && in_array($decoded['signature_type'], ['high_confidence', 'heuristic'], true)
            ? $decoded['signature_type']
            : $defaultType;
        $score = max(1, min(10, (int) ($decoded['score'] ?? $defaultScore)));
        $name = isset($decoded['name']) && is_string($decoded['name']) && trim($decoded['name']) !== ''
            ? trim($decoded['name'])
            : $defaultName;
        $reasoning = isset($decoded['reasoning']) && is_string($decoded['reasoning']) ? trim($decoded['reasoning']) : '';
        $risk = isset($decoded['false_positive_risk']) && is_string($decoded['false_positive_risk']) ? trim($decoded['false_positive_risk']) : 'unknown';

        if ($pattern === '' || $label === '' || @preg_match($pattern, '') === false) {
            throw new RuntimeException($errorMessage);
        }

        return [
            'name' => $name,
            'signature_type' => $type,
            'label' => $label,
            'pattern' => $pattern,
            'score' => $score,
            'false_positive_risk' => $risk,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * @param  array<int, string>  $contents
     * @return array{matches_any: bool, reason: string}
     */
    private function validatePatternAgainstContents(string $pattern, array $contents): array
    {
        if (@preg_match($pattern, '') === false) {
            return ['matches_any' => false, 'reason' => 'The regex is invalid.'];
        }

        foreach ($contents as $content) {
            if (! is_string($content) || trim($content) === '') {
                continue;
            }

            if (@preg_match($pattern, $content) === 1) {
                return ['matches_any' => true, 'reason' => 'The regex matched at least one sample.'];
            }
        }

        return ['matches_any' => false, 'reason' => 'The regex did not match any provided sample contents.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJsonSuggestion(string $apiKey, string $model, string $systemPrompt, string $userPrompt): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
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

        return $decoded;
    }

    private function buildFailedMatchCorrectionPrompt(WordPressSignatureSample $sample, array $previousSuggestion, string $content, string $failureReason): string
    {
        $trimmedContent = mb_substr($content, 0, self::SAMPLE_CONTENT_LIMIT);
        $previousJson = $this->json($previousSuggestion);

        return <<<PROMPT
Your previous regex suggestion failed validation.

Failure reason:
- {$failureReason}

Previous JSON suggestion:
{$previousJson}

Correct it and return strict JSON with these keys only:
- name
- signature_type
- label
- pattern
- score
- false_positive_risk
- reasoning

Rules:
- The regex must match the sample content below.
- Many malware samples are multiline. Do not assume `.*` crosses newlines.
- Prefer `[\s\S]` with a bounded range or a deliberate `s` modifier when spanning lines.
- Keep WordPress false positives low.
- Return a PHP `preg_match` compatible pattern with delimiters and modifiers, for example `/.../i`.
- JSON must be valid. Escape backslashes properly, such as `\\s` inside the JSON string.
- No markdown.

Sample metadata:
- name: {$sample->name}
- family: {$sample->family}
- type: {$sample->sample_type}

Sample content:
{$trimmedContent}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $testResult
     */
    private function buildFailedRevisionCorrectionPrompt(WordPressMalwareSignature $signature, array $previousSuggestion, array $testResult, string $failureReason): string
    {
        $previousJson = $this->json($previousSuggestion);

        return <<<PROMPT
Your previous revision failed validation.

Failure reason:
- {$failureReason}

Previous JSON suggestion:
{$previousJson}

Current signature:
- name: {$signature->name}
- type: {$signature->signature_type}
- label: {$signature->label}
- pattern: {$signature->pattern}

Latest test result:
{$this->json($testResult)}

Recent malware sample excerpts:
{$this->sampleExcerptContext('malware')}

Return strict JSON with these keys only:
- name
- signature_type
- label
- pattern
- score
- false_positive_risk
- reasoning

Rules:
- The regex must match at least one relevant malware sample excerpt above.
- Many malware samples are multiline. Do not assume `.*` crosses newlines.
- Prefer `[\s\S]` with a bounded range or a deliberate `s` modifier when spanning lines.
- Keep WordPress false positives low.
- Return a PHP `preg_match` compatible pattern with delimiters and modifiers, for example `/.../i`.
- JSON must be valid. Escape backslashes properly, such as `\\s` inside the JSON string.
- No markdown.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $previousSuggestion
     * @return array{name: string, signature_type: string, label: string, pattern: string, score: int, false_positive_risk: string, reasoning: string}
     */
    private function correctCandidateForSample(string $apiKey, string $model, WordPressSignatureSample $sample, array $previousSuggestion, string $content, string $defaultName, string $failureReason): array
    {
        $lastError = 'OpenAI returned a malformed corrected regex suggestion.';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $decoded = $this->requestJsonSuggestion(
                $apiKey,
                $model,
                'You correct malware-signature drafts for a WordPress security product. Return exactly one JSON object only. Do not wrap in markdown. Prefer maintainable signatures and conservative false-positive behavior.',
                $this->buildFailedMatchCorrectionPrompt($sample, $previousSuggestion, $content, $failureReason . " Attempt {$attempt} of 2.")
            );

            try {
                $candidate = $this->normalizeSignatureCandidate($decoded, $defaultName, $lastError);
            } catch (RuntimeException $exception) {
                $lastError = $exception->getMessage();
                $previousSuggestion = $decoded;
                continue;
            }

            $validation = $this->validatePatternAgainstContents($candidate['pattern'], [$content]);

            if ($validation['matches_any']) {
                return $candidate;
            }

            $failureReason = sprintf('The corrected regex still did not match the originating sample content. %s', $validation['reason']);
            $previousSuggestion = $decoded;
        }

        throw new RuntimeException($lastError);
    }

    /**
     * @param  array<string, mixed>  $previousSuggestion
     * @param  array<string, mixed>  $testResult
     * @param  array<int, WordPressSignatureSample>  $malwareSamples
     * @param  array{matched_count: int, families: array<int, string>, sample_names: array<int, string>}  $currentSummary
     * @return array{name: string, signature_type: string, label: string, pattern: string, score: int, false_positive_risk: string, reasoning: string}
     */
    private function correctCandidateForRevision(string $apiKey, string $model, WordPressMalwareSignature $signature, array $previousSuggestion, array $testResult, array $malwareSamples, array $currentSummary, ?string $sourceSampleName, string $failureReason): array
    {
        $lastError = 'OpenAI returned a malformed corrected revised regex.';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $decoded = $this->requestJsonSuggestion(
                $apiKey,
                $model,
                'You correct malware-signature revisions for a WordPress security product. Return exactly one JSON object only. Never approve signatures.',
                $this->buildFailedRevisionCorrectionPrompt($signature, $previousSuggestion, $testResult, $failureReason . " Attempt {$attempt} of 2.")
            );

            try {
                $candidate = $this->normalizeSignatureCandidate($decoded, $signature->name, $lastError, $signature->signature_type, (int) ($signature->score ?? 1));
            } catch (RuntimeException $exception) {
                $lastError = $exception->getMessage();
                $previousSuggestion = $decoded;
                continue;
            }

            $validation = $this->validatePatternAgainstSamples($candidate['pattern'], $malwareSamples);

            if ($validation['matches_any'] && (! $this->revisionMustNarrow($signature, $currentSummary) || $this->isNarrowerRevision($validation, $currentSummary, $sourceSampleName))) {
                return $candidate;
            }

            $failureReason = ! $validation['matches_any']
                ? sprintf('The corrected revision still did not match stored malware samples. %s', $validation['reason'])
                : sprintf('The corrected revision still overlaps too broadly. It matched %d sample(s) across %d family/families and must be narrower while still matching the source sample.', $validation['matched_count'], count($validation['families']));
            $previousSuggestion = $decoded;
        }

        throw new RuntimeException($lastError);
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
