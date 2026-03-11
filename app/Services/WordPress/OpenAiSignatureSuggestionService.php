<?php

namespace App\Services\WordPress;

use App\Models\WordPressMalwareSignature;
use App\Models\WordPressSignatureSample;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiSignatureSuggestionService
{
    private const SAMPLE_CONTENT_LIMIT = 12000;
    private const OPENAI_TIMEOUT_SECONDS = 45;
    private const OPENAI_MAX_RETRIES = 3;
    private const OPENAI_RETRY_SLEEP_MS = 800;
    private const REVIEW_EXCERPT_LIMIT = 500;
    private const REVIEW_MATCH_LIMIT = 3;
    private const REVIEW_WORDPRESS_FILE_LIMIT = 10;
    private const REVIEW_SIGNATURE_CONTEXT_LIMIT = 8;
    private const AUTO_FIX_MAX_ATTEMPTS = 3;

    /**
     * @var array<int, array{id:int,name:string,sample_type:string,content:string}>
     */
    private ?array $sampleCorpus = null;

    /**
     * @var array<string, true>|null
     */
    private ?array $activePatternIndex = null;

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

        $response = $this->sendChatCompletionRequest($apiKey, $model, [
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
        ]);

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
        $content = trim((string) ($sample->content ?? ''));

        if ($content === '') {
            throw new RuntimeException('This sample does not contain any text content to analyze.');
        }

        $candidate = $this->buildDeterministicCandidate($sample, $content);

        $existingSignature = WordPressMalwareSignature::query()
            ->where('status', '!=', 'archived')
            ->where('pattern', $candidate['pattern'])
            ->first();

        if ($existingSignature) {
            throw new RuntimeException(sprintf(
                'A similar signature already exists: %s (%s).',
                $existingSignature->name,
                $existingSignature->status
            ));
        }

        $signature = WordPressMalwareSignature::query()->create([
            'name' => $sample->name,
            'signature_type' => $candidate['signature_type'],
            'pattern' => $candidate['pattern'],
            'label' => $candidate['label'],
            'score' => $candidate['signature_type'] === 'heuristic' ? $candidate['score'] : null,
            'status' => 'draft',
            'source' => 'deterministic',
            'notes' => trim("Suggested from sample: {$sample->name}\nFalse-positive risk: {$candidate['false_positive_risk']}\n\n{$candidate['reasoning']}"),
        ]);

        $this->activePatternIndex[(string) $candidate['pattern']] = true;

        return [
            'signature' => $signature,
            'risk' => $candidate['false_positive_risk'],
        ];
    }

    /**
     * @return array{name: string, signature_type: string, label: string, pattern: string, score: int, false_positive_risk: string, reasoning: string}
     */
    private function buildDeterministicCandidate(WordPressSignatureSample $sample, string $content): array
    {
        $candidates = [];

        foreach ($this->candidatePatternsFromContent($content) as $pattern => $metadata) {
            if (! $this->isValidUtf8Pattern($pattern)) {
                continue;
            }

            $evaluation = $this->evaluateDeterministicPattern($pattern, $sample);

            if (! $evaluation['matches_source']) {
                continue;
            }

            if ($evaluation['malware_hits'] !== 1) {
                continue;
            }

            if ($evaluation['clean_hits'] > 0 || $evaluation['false_positive_hits'] > 0) {
                continue;
            }

            if ($this->activeSignatureConflictExists($pattern)) {
                continue;
            }

            $candidates[] = [
                'pattern' => $pattern,
                'metadata' => $metadata,
                'evaluation' => $evaluation,
            ];
        }

        if ($candidates === []) {
            throw new RuntimeException('No exact single-sample signature candidate could be derived from this file.');
        }

        usort($candidates, function (array $left, array $right): int {
            $leftEval = $left['evaluation'];
            $rightEval = $right['evaluation'];

            return [
                -((int) ($left['metadata']['priority'] ?? 0)),
                -strlen((string) $left['pattern']),
            ] <=> [
                -((int) ($right['metadata']['priority'] ?? 0)),
                -strlen((string) $right['pattern']),
            ];
        });

        $selected = $candidates[0];
        $metadata = $selected['metadata'];
        $evaluation = $selected['evaluation'];

        return [
            'name' => $sample->name,
            'signature_type' => 'high_confidence',
            'label' => (string) ($metadata['label'] ?? 'sample-specific anchor'),
            'pattern' => (string) $selected['pattern'],
            'score' => 10,
            'false_positive_risk' => ($evaluation['malware_hits'] ?? 0) <= 1 ? 'low' : 'medium',
            'reasoning' => sprintf(
                'Built from a sample-specific anchor. Matches source sample "%s" and exactly %d malware sample, with %d clean hits and %d false-positive hits in the current sample library.',
                $sample->name,
                (int) ($evaluation['malware_hits'] ?? 0),
                (int) ($evaluation['clean_hits'] ?? 0),
                (int) ($evaluation['false_positive_hits'] ?? 0),
            ),
        ];
    }

    private function activeSignatureConflictExists(string $pattern): bool
    {
        return isset($this->activePatternIndex()[$pattern]);
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

        $sourceSample = $this->sourceSampleForSignature($signature);

        $decoded = $this->requestJsonSuggestion($apiKey, $model, 'You revise malware-signature drafts for a WordPress security product. Return exactly one JSON object only. Never approve signatures. Revise conservatively and reduce false positives.', $this->buildRevisionPrompt($signature, $testResult, $sourceSample));
        $candidate = $this->normalizeSignatureCandidate($decoded, $signature->name, 'OpenAI returned a malformed revised regex.', $signature->signature_type, (int) ($signature->score ?? 1));

        $malwareSamples = WordPressSignatureSample::query()
            ->where('sample_type', 'malware')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['name', 'family', 'content']);

        if ($sourceSample instanceof WordPressSignatureSample && $malwareSamples->doesntContain(fn (WordPressSignatureSample $sample): bool => $sample->name === $sourceSample->name)) {
            $malwareSamples->prepend($sourceSample);
        }

        $validation = $this->validatePatternAgainstSamples($candidate['pattern'], $malwareSamples->all());
        $currentSummary = $this->testSummaryFromResult($testResult);
        $sourceSampleName = $this->extractSourceSampleName($signature);
        $sourceSampleContent = $sourceSample?->content ? (string) $sourceSample->content : null;
        $mustNarrow = $this->revisionMustNarrow($signature, $currentSummary);

        if (
            (($sourceSampleContent !== null && ! $this->patternMatchesContent($candidate['pattern'], $sourceSampleContent))
                || (($testResult['summary']['malware_hits'] ?? 0) === 0 && ! $validation['matches_any']))
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
                sourceSample: $sourceSample,
                failureReason: (($sourceSampleContent !== null && ! $this->patternMatchesContent($candidate['pattern'], $sourceSampleContent)))
                    ? 'The revised regex no longer matches the original source malware sample. It must continue matching that source sample.'
                    : ((($testResult['summary']['malware_hits'] ?? 0) === 0 && ! $validation['matches_any'])
                    ? sprintf('The revised regex still does not match any stored malware sample. %s', $validation['reason'])
                    : sprintf('The revised regex still overlaps too broadly across malware families. It matched %d sample(s) across %d family/families.', $validation['matched_count'], count($validation['families'])))
            );
        }

        $existingSignature = WordPressMalwareSignature::query()
            ->whereKeyNot($signature->getKey())
            ->where('status', '!=', 'archived')
            ->where('pattern', $candidate['pattern'])
            ->first();

        if ($existingSignature) {
            throw new RuntimeException(sprintf(
                'A similar signature already exists: %s (%s).',
                $existingSignature->name,
                $existingSignature->status
            ));
        }

        $originalState = $signature->only([
            'name',
            'signature_type',
            'pattern',
            'label',
            'score',
            'status',
            'source',
            'notes',
            'last_tested_at',
            'last_test_result',
            'test_history',
            'last_ai_review',
        ]);

        $signature->forceFill([
            'name' => $signature->name,
            'signature_type' => $candidate['signature_type'],
            'pattern' => $candidate['pattern'],
            'label' => $candidate['label'],
            'score' => $candidate['signature_type'] === 'heuristic' ? $candidate['score'] : null,
            'status' => 'draft',
            'source' => 'ai',
            'notes' => trim(($signature->notes ? $signature->notes . "\n\n" : '') . "AI revision\nFalse-positive risk: {$candidate['false_positive_risk']}\n\n{$candidate['reasoning']}"),
        ])->save();

        $postRevisionTest = app(WordPressSignatureLabService::class)->testSignature($signature);
        $outcome = (string) ($postRevisionTest['outcome'] ?? 'pass');

        if ($outcome !== 'pass') {
            $signature->forceFill($originalState)->save();

            throw new RuntimeException(sprintf(
                'AI revision was rejected by the full test set. Outcome: %s. Malware hits: %d, clean hits: %d, false-positive hits: %d, WordPress core hits: %d.',
                ucfirst($outcome),
                (int) ($postRevisionTest['summary']['malware_hits'] ?? 0),
                (int) ($postRevisionTest['summary']['clean_hits'] ?? 0),
                (int) ($postRevisionTest['summary']['false_positive_hits'] ?? 0),
                (int) ($postRevisionTest['summary']['wordpress_core_hits'] ?? 0),
            ));
        }

        return [
            'signature' => $signature->fresh(),
            'risk' => $candidate['false_positive_risk'],
            'test_result' => $postRevisionTest,
        ];
    }

    /**
     * @return array{fixed:bool,already_passing:bool,signature:WordPressMalwareSignature,attempts:array<int,array<string,mixed>>,final_test_result:array<string,mixed>,message:string}
     */
    public function autoFixSignature(WordPressMalwareSignature $signature, int $maxAttempts = self::AUTO_FIX_MAX_ATTEMPTS): array
    {
        $lab = app(WordPressSignatureLabService::class);
        $signature = $signature->fresh();
        $currentResult = $lab->testSignature($signature);
        $attempts = [];

        if (($currentResult['outcome'] ?? 'pass') === 'pass') {
            return [
                'fixed' => true,
                'already_passing' => true,
                'signature' => $signature->fresh(),
                'attempts' => [],
                'final_test_result' => $currentResult,
                'message' => 'Signature already passed the full test set.',
            ];
        }

        $baseReview = $this->reviewTestResults($signature->fresh());

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $signature = $signature->fresh();
            $reviewWithFeedback = $baseReview;
            $reviewWithFeedback['autofix_feedback'] = $this->buildAutoFixFeedback($currentResult, $attempts);
            $reviewWithFeedback['autofix_attempt'] = $attempt;

            $signature->forceFill([
                'last_ai_review' => $reviewWithFeedback,
            ])->save();

            try {
                $revision = $this->reviseSignature($signature);

                $attempts[] = [
                    'attempt' => $attempt,
                    'status' => 'fixed',
                    'message' => 'AI revision passed the full test set.',
                    'test_result' => $revision['test_result'],
                ];

                return [
                    'fixed' => true,
                    'already_passing' => false,
                    'signature' => $revision['signature'],
                    'attempts' => $attempts,
                    'final_test_result' => $revision['test_result'],
                    'message' => sprintf('AI auto-fix succeeded on attempt %d.', $attempt),
                ];
            } catch (\Throwable $exception) {
                $attempts[] = [
                    'attempt' => $attempt,
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];

                $baseReview['autofix_feedback'] = $this->buildAutoFixFeedback($currentResult, $attempts);

                $signature->fresh()->forceFill([
                    'last_ai_review' => array_merge($baseReview, [
                        'autofix_feedback' => $this->buildAutoFixFeedback($currentResult, $attempts),
                        'autofix_attempt' => $attempt,
                        'last_error' => $exception->getMessage(),
                    ]),
                ])->save();
            }
        }

        return [
            'fixed' => false,
            'already_passing' => false,
            'signature' => $signature->fresh(),
            'attempts' => $attempts,
            'final_test_result' => $currentResult,
            'message' => sprintf('AI auto-fix did not produce a passing revision after %d attempt(s).', $maxAttempts),
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
- Do not use backreferences like `\1` or `\2` unless that capture group is explicitly defined earlier in the same pattern.
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

    /**
     * @return array<string, array{label:string}>
     */
    private function candidatePatternsFromContent(string $content): array
    {
        $patterns = [];
        $literals = $this->extractCandidateLiterals($content);

        foreach (array_slice($literals, 0, 16) as $literal) {
            if (! $this->isSafePatternText($literal)) {
                continue;
            }

            $patterns['/' . preg_quote($literal, '/') . '/'] = [
                'label' => 'sample-specific literal',
                'priority' => 10,
            ];
        }

        $literalCount = min(count($literals), 8);

        for ($index = 0; $index < $literalCount - 1; $index++) {
            $first = $literals[$index];
            $second = $literals[$index + 1];

            if (! $this->isSafePatternText($first) || ! $this->isSafePatternText($second)) {
                continue;
            }

            $patterns['/' . preg_quote($first, '/') . '[\\s\\S]{0,160}' . preg_quote($second, '/') . '/'] = [
                'label' => 'sample-specific literal chain',
                'priority' => 18,
            ];
        }

        foreach ($this->extractCandidateLines($content) as $line) {
            $normalizedLine = preg_replace('/\s+/', '\\s+', preg_quote($line, '/')) ?: '';

            if ($normalizedLine === '') {
                continue;
            }

            $patterns['/' . $normalizedLine . '/'] = [
                'label' => 'sample-specific line fragment',
                'priority' => 24,
            ];
        }

        foreach ($this->extractCandidateIdentifiers($content) as $identifier) {
            $patterns['/\$?' . preg_quote($identifier, '/') . '\b/'] = [
                'label' => 'sample-specific identifier',
                'priority' => 8,
            ];
        }

        foreach ($this->extractCandidateHexAndBase64Fragments($content) as $fragment) {
            $patterns['/' . preg_quote($fragment, '/') . '/'] = [
                'label' => 'sample-specific encoded fragment',
                'priority' => 26,
            ];
        }

        foreach ($this->extractCandidateTokenChains($content) as $chain) {
            $parts = array_map(
                static fn (string $part): string => preg_quote($part, '/'),
                $chain
            );

            $patterns['/' . implode('[\\s\\S]{0,120}', $parts) . '/'] = [
                'label' => 'sample-specific token chain',
                'priority' => 28,
            ];
        }

        foreach ($this->extractHeadTailPatterns($content) as $pattern => $label) {
            $patterns[$pattern] = [
                'label' => $label,
                'priority' => 32,
            ];
        }

        $exactTinyPattern = $this->extractExactTinyContentPattern($content);

        if ($exactTinyPattern !== null) {
            $patterns[$exactTinyPattern] = [
                'label' => 'exact source-file content',
                'priority' => 80,
            ];
        }

        foreach ($this->extractCandidateUniqueWindows($content) as $window) {
            $patterns['/' . preg_quote($window, '/') . '/s'] = [
                'label' => 'sample-specific content window',
                'priority' => 34,
            ];
        }

        foreach ($this->extractCandidateWindowChains($content) as $chain) {
            $patterns['/' . preg_quote($chain['start'], '/') . '[\\s\\S]{0,' . $chain['gap'] . '}' . preg_quote($chain['end'], '/') . '/s'] = [
                'label' => 'sample-specific content window chain',
                'priority' => 48,
            ];
        }

        foreach ($this->extractAnchoredLinePatterns($content) as $pattern => $metadata) {
            $patterns[$pattern] = $metadata;
        }

        return $patterns;
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateLiterals(string $content): array
    {
        preg_match_all('/([\'"])(.{6,120}?)\1/s', $content, $matches);

        $literals = collect($matches[2] ?? [])
            ->filter(function ($literal): bool {
                if (! is_string($literal)) {
                    return false;
                }

                $literal = trim($literal);

                if ($literal === '' || strlen($literal) < 6 || strlen($literal) > 120) {
                    return false;
                }

                if (preg_match('/^[a-z_]+$/i', $literal) === 1 && strlen($literal) < 12) {
                    return false;
                }

                return preg_match('/[A-Za-z]/', $literal) === 1;
            })
            ->map(fn (string $literal): string => trim($literal))
            ->unique()
            ->sortByDesc(fn (string $literal): int => $this->literalSpecificityScore($literal))
            ->values()
            ->all();

        return array_values($literals);
    }

    private function literalSpecificityScore(string $literal): int
    {
        $score = strlen($literal);

        if (preg_match('/[A-Z]/', $literal) === 1 && preg_match('/[a-z]/', $literal) === 1) {
            $score += 10;
        }

        if (preg_match('/[0-9]/', $literal) === 1) {
            $score += 8;
        }

        if (preg_match('/[_\-\/\.]/', $literal) === 1) {
            $score += 8;
        }

        if (preg_match('/(?:base64|gzinflate|str_rot13|php:\/\/input|FilesMan|auth_pass|fromCharCode|document\.write|multipart\/form-data)/i', $literal) === 1) {
            $score += 25;
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateLines(string $content): array
    {
        return collect(preg_split('/\R+/', $content) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter(function (string $line): bool {
                if ($line === '' || strlen($line) < 20 || strlen($line) > 180) {
                    return false;
                }

                return preg_match('/(?:base64|gzinflate|eval|assert|php:\/\/input|FilesMan|auth_pass|fromCharCode|document\.write|multipart\/form-data|move_uploaded_file|file_put_contents)/i', $line) === 1;
            })
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateIdentifiers(string $content): array
    {
        preg_match_all('/(?<![A-Za-z0-9_])(?:\$)?([A-Za-z_][A-Za-z0-9_]{5,40})(?![A-Za-z0-9_])/', $content, $matches);

        return collect($matches[1] ?? [])
            ->filter(function ($identifier): bool {
                if (! is_string($identifier)) {
                    return false;
                }

                if (preg_match('/^(?:array|string|function|class|public|private|protected|return|include|require|define|session_start|error_reporting|basename|dirname|isset|empty|header|time|eval|assert|system|exec|shell_exec|passthru|move_uploaded_file|file_put_contents|getenv|microtime)$/i', $identifier) === 1) {
                    return false;
                }

                return true;
            })
            ->unique()
            ->sortByDesc(fn (string $identifier): int => strlen($identifier) + (preg_match('/[0-9_]/', $identifier) === 1 ? 12 : 0))
            ->take(16)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateHexAndBase64Fragments(string $content): array
    {
        preg_match_all('/[A-Fa-f0-9]{24,}/', $content, $hexMatches);
        preg_match_all('/[A-Za-z0-9+\/]{32,}={0,2}/', $content, $base64Matches);

        return collect(array_merge($hexMatches[0] ?? [], $base64Matches[0] ?? []))
            ->filter(function ($fragment): bool {
                return is_string($fragment)
                    && strlen($fragment) >= 24
                    && preg_match('/^(?:[A-Za-z0-9+\/=]+|[A-Fa-f0-9]+)$/', $fragment) === 1;
            })
            ->map(static fn (string $fragment): string => substr($fragment, 0, min(strlen($fragment), 80)))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function extractCandidateTokenChains(string $content): array
    {
        preg_match_all(
            '/(?:FilesMan|auth_pass|p4ssw0rD|MeTaLTeaM|b374k|emp3ror|canirun|etcpasswdfile|shellhelp|runcommand|k1r4_surl|surl_autofill_include|create_function|gzinflate|base64_decode|php:\/\/input|String\.fromCharCode|document\.write|set_magic_quotes_runtime)/i',
            $content,
            $matches
        );

        $tokens = array_values(array_unique(array_map(
            static fn (string $token): string => $token,
            $matches[0] ?? []
        )));

        $chains = [];

        for ($index = 0; $index < count($tokens) - 1; $index++) {
            $chains[] = [$tokens[$index], $tokens[$index + 1]];
        }

        if (count($tokens) >= 3) {
            for ($index = 0; $index < count($tokens) - 2; $index++) {
                $chains[] = [$tokens[$index], $tokens[$index + 1], $tokens[$index + 2]];
            }
        }

        return array_slice($chains, 0, 12);
    }

    /**
     * @return array<string, string>
     */
    private function extractHeadTailPatterns(string $content): array
    {
        $patterns = [];
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R+/', $content) ?: []),
            static fn (string $line): bool => $line !== ''
        ));

        if ($lines === []) {
            return $patterns;
        }

        $head = $this->bestBoundarySnippet(array_slice($lines, 0, 8));
        $tail = $this->bestBoundarySnippet(array_reverse(array_slice($lines, -8)));

        if ($head !== null) {
            $patterns['/' . preg_quote($head, '/') . '/'] = 'source-file head snippet';
        }

        if ($tail !== null) {
            $patterns['/' . preg_quote($tail, '/') . '/'] = 'source-file tail snippet';
        }

        if ($head !== null && $tail !== null && $head !== $tail) {
            $patterns['/' . preg_quote($head, '/') . '[\\s\\S]{0,12000}' . preg_quote($tail, '/') . '/s'] = 'source-file head-tail anchor';
        }

        return $patterns;
    }

    private function extractExactTinyContentPattern(string $content): ?string
    {
        $trimmed = trim($content);

        if ($trimmed === '' || strlen($trimmed) > 24 || ! $this->isSafePatternText($trimmed)) {
            return null;
        }

        return '/^\s*' . preg_quote($trimmed, '/') . '\s*$/s';
    }

    /**
     * @return array<string, array{label:string,priority:int}>
     */
    private function extractAnchoredLinePatterns(string $content): array
    {
        $patterns = [];
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R+/', $content) ?: []),
            static fn (string $line): bool => $line !== ''
        ));

        if ($lines === []) {
            return $patterns;
        }

        $first = $this->bestBoundarySnippet(array_slice($lines, 0, 3));
        $last = $this->bestBoundarySnippet(array_reverse(array_slice($lines, -3)));

        if ($first !== null && $this->isSafePatternText($first)) {
            $patterns['/^\s*' . preg_quote($first, '/') . '/s'] = [
                'label' => 'source-file first-line anchor',
                'priority' => 52,
            ];
        }

        if ($last !== null && $this->isSafePatternText($last)) {
            $patterns['/' . preg_quote($last, '/') . '\s*$/s'] = [
                'label' => 'source-file last-line anchor',
                'priority' => 52,
            ];
        }

        if ($first !== null && $last !== null && $first !== $last && $this->isSafePatternText($first) && $this->isSafePatternText($last)) {
            $patterns['/^\s*' . preg_quote($first, '/') . '[\\s\\S]{0,18000}' . preg_quote($last, '/') . '\s*$/s'] = [
                'label' => 'source-file first-last anchor',
                'priority' => 70,
            ];
        }

        return $patterns;
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateUniqueWindows(string $content): array
    {
        $normalized = trim($content);

        if ($normalized === '') {
            return [];
        }

        $length = strlen($normalized);
        $windowSizes = [120, 96, 72, 56, 40, 32, 24];
        $positions = array_values(array_unique(array_filter([
            0,
            (int) floor(max(0, $length - 1) * 0.15),
            (int) floor(max(0, $length - 1) * 0.33),
            (int) floor(max(0, $length - 1) * 0.5),
            (int) floor(max(0, $length - 1) * 0.66),
            (int) floor(max(0, $length - 1) * 0.85),
            max(0, $length - 120),
        ], static fn (int $position): bool => $position >= 0)));

        $windows = [];

        foreach ($windowSizes as $windowSize) {
            if ($length < $windowSize) {
                continue;
            }

            foreach ($positions as $position) {
                $slice = substr($normalized, min($position, max(0, $length - $windowSize)), $windowSize);
                $slice = trim((string) $slice);

                if (! $this->isUsefulContentWindow($slice)) {
                    continue;
                }

                $windows[] = $slice;
            }
        }

        return collect($windows)
            ->unique()
            ->sortByDesc(static fn (string $window): int => strlen($window) + (preg_match('/[A-Za-z]/', $window) === 1 ? 10 : 0) + (preg_match('/[0-9]/', $window) === 1 ? 8 : 0))
            ->take(18)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{start:string,end:string,gap:int}>
     */
    private function extractCandidateWindowChains(string $content): array
    {
        $normalized = trim($content);

        if ($normalized === '' || strlen($normalized) < 80) {
            return [];
        }

        $length = strlen($normalized);
        $segments = [
            substr($normalized, 0, min(48, $length)),
            substr($normalized, max(0, (int) floor(($length / 2) - 24)), min(48, $length)),
            substr($normalized, max(0, $length - 48), min(48, $length)),
        ];

        $segments = array_values(array_filter(array_map('trim', $segments), fn (string $segment): bool => $this->isUsefulContentWindow($segment)));

        if (count($segments) < 2) {
            return [];
        }

        $chains = [];

        for ($index = 0; $index < count($segments) - 1; $index++) {
            if ($segments[$index] === $segments[$index + 1]) {
                continue;
            }

            $chains[] = [
                'start' => $segments[$index],
                'end' => $segments[$index + 1],
                'gap' => 12000,
            ];
        }

        return array_slice($chains, 0, 4);
    }

    private function isUsefulContentWindow(string $slice): bool
    {
        $slice = trim($slice);

        if ($slice === '' || strlen($slice) < 24 || ! $this->isSafePatternText($slice)) {
            return false;
        }

        if (preg_match('/^\s*<\?php\s*$/i', $slice) === 1) {
            return false;
        }

        if (preg_match('/^[\s<>\?;{}()\[\]\$=_\-\/\\\\]+$/', $slice) === 1) {
            return false;
        }

        return preg_match('/[A-Za-z0-9]/', $slice) === 1;
    }

    private function isSafePatternText(string $value): bool
    {
        return $value !== '' && mb_check_encoding($value, 'UTF-8');
    }

    private function isValidUtf8Pattern(string $pattern): bool
    {
        return $pattern !== '' && mb_check_encoding($pattern, 'UTF-8');
    }

    /**
     * @return array<string, true>
     */
    private function activePatternIndex(): array
    {
        if ($this->activePatternIndex !== null) {
            return $this->activePatternIndex;
        }

        $this->activePatternIndex = [];

        foreach (WordPressMalwareSignature::query()->where('status', '!=', 'archived')->pluck('pattern') as $pattern) {
            $pattern = (string) $pattern;

            if ($pattern !== '') {
                $this->activePatternIndex[$pattern] = true;
            }
        }

        return $this->activePatternIndex;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function bestBoundarySnippet(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (strlen($line) < 16) {
                continue;
            }

            if (preg_match('/^(?:<\?php|\/\/|\/\*|\*\/|\*|\}|{|;|if\s*\(|foreach\s*\(|for\s*\(|while\s*\()/i', $line) === 1 && strlen($line) < 28) {
                continue;
            }

            return substr($line, 0, min(strlen($line), 140));
        }

        return null;
    }

    /**
     * @return array{matches_source:bool,malware_hits:int,clean_hits:int,false_positive_hits:int}
     */
    private function evaluateDeterministicPattern(string $pattern, WordPressSignatureSample $sourceSample): array
    {
        $matchesSource = false;
        $malwareHits = 0;
        $cleanHits = 0;
        $falsePositiveHits = 0;

        foreach ($this->sampleCorpus() as $sample) {
            $sampleContent = $sample['content'];

            if ($sampleContent === '' || @preg_match($pattern, $sampleContent) !== 1) {
                continue;
            }

            if ((int) $sample['id'] === (int) $sourceSample->id) {
                $matchesSource = true;
            }

            if ($sample['sample_type'] === 'malware') {
                $malwareHits++;
            } elseif ($sample['sample_type'] === 'clean') {
                $cleanHits++;
            } else {
                $falsePositiveHits++;
            }
        }

        return [
            'matches_source' => $matchesSource,
            'malware_hits' => $malwareHits,
            'clean_hits' => $cleanHits,
            'false_positive_hits' => $falsePositiveHits,
        ];
    }

    /**
     * @return array<int, array{id:int,name:string,sample_type:string,content:string}>
     */
    private function sampleCorpus(): array
    {
        if ($this->sampleCorpus !== null) {
            return $this->sampleCorpus;
        }

        $this->sampleCorpus = WordPressSignatureSample::query()
            ->get(['id', 'name', 'sample_type', 'content'])
            ->map(static function (WordPressSignatureSample $sample): array {
                return [
                    'id' => (int) $sample->id,
                    'name' => (string) $sample->name,
                    'sample_type' => (string) $sample->sample_type,
                    'content' => trim((string) ($sample->content ?? '')),
                ];
            })
            ->filter(static fn (array $sample): bool => $sample['content'] !== '')
            ->values()
            ->all();

        return $this->sampleCorpus;
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
            ->limit(self::REVIEW_SIGNATURE_CONTEXT_LIMIT)
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
    private function buildRevisionPrompt(WordPressMalwareSignature $signature, array $testResult, ?WordPressSignatureSample $sourceSample = null): string
    {
        $engineContext = $this->engineContext();
        $existingSignatures = $this->existingSignatureContext();
        $sampleContext = $this->sampleContext();
        $summary = $testResult['summary'] ?? [];
        $matchedSamples = $testResult['matched_samples'] ?? [];
        $lastAiReview = is_array($signature->last_ai_review) ? $signature->last_ai_review : null;
        $aiReviewContext = $lastAiReview ? $this->json($lastAiReview) : 'null';
        $autofixFeedback = is_array($lastAiReview) ? trim((string) ($lastAiReview['autofix_feedback'] ?? '')) : '';
        $autofixFeedbackContext = $autofixFeedback !== '' ? $autofixFeedback : '- No prior auto-fix attempt feedback.';
        $sourceSampleContext = $this->sourceSampleContext($sourceSample);
        $sourceMatchContext = $this->sourceMatchContext($signature, $sourceSample);

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
- Do not use backreferences like `\1` or `\2` unless that capture group is explicitly defined earlier in the same pattern.
- If the previous signature failed to match malware hits, explicitly fix the likely reason instead of making only cosmetic changes.
- If the saved AI review says the signature is broad or should be narrowed, reduce overlap across unrelated malware families instead of returning another generic dangerous-function list.
- When narrowing, prefer the originating sample behavior and specific strings or flows over generic file-manager patterns.
- Before returning, sanity-check that your revised regex still matches the original source sample shown below.
- No markdown.

Current signature:
- name: {$signature->name}
- type: {$signature->signature_type}
- label: {$signature->label}
- score: {$signature->score}
- pattern: {$signature->pattern}
- notes: {$signature->notes}

Original source sample:
{$sourceSampleContext}

Current signature match inside the source sample:
{$sourceMatchContext}

Latest test result:
- malware_hits: {$summary['malware_hits']}
- clean_hits: {$summary['clean_hits']}
- false_positive_hits: {$summary['false_positive_hits']}
- wordpress_core_hits: {$summary['wordpress_core_hits']}
- risk: {$testResult['risk']}
- matched_samples: {$this->json($matchedSamples)}

Saved AI review of this test result:
{$aiReviewContext}

Auto-fix feedback from prior failed attempts:
{$autofixFeedbackContext}

FirePhage scanner engine context:
{$engineContext}

Existing database signatures to avoid duplicating:
{$existingSignatures}

Recent sample library context:
{$sampleContext}

Matched malware sample excerpts:
{$this->matchedSampleExcerptContext(is_array($matchedSamples) ? $matchedSamples : [])}

Matched clean/false-positive sample excerpts:
{$this->matchedSampleExcerptContextByTypes($testResult, ['clean', 'false_positive'])}

Matched WordPress core file paths:
{$this->matchedWordPressFileContext($testResult)}

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
        $matchedFileLines = $this->matchedFileListContext($testResult, ['clean', 'false_positive', 'clean_wordpress_core'], self::REVIEW_WORDPRESS_FILE_LIMIT);

        return <<<PROMPT
Review this WordPress malware-signature test result and determine whether the current hit pattern looks healthy, causes clean-file false positives, or is simply ineffective.

Return strict JSON with these keys:
- verdict
- overlap_risk
- recommendation
- reasoning

Rules:
- Keep the answer concise.
- Treat any matched clean files or matched WordPress core files as a serious false-positive problem.
- If clean or WordPress core files were hit, prefer recommendation `narrow`.
- If malware_hits is zero and no clean files were hit, prefer recommendation `revise`.
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
- malware_hits: {$summary['malware_hits']}
- clean_hits: {$summary['clean_hits']}
- false_positive_hits: {$summary['false_positive_hits']}
- wordpress_core_hits: {$summary['wordpress_core_hits']}
- risk: {$testResult['risk']}
- outcome: {$testResult['outcome']}

Matched sample excerpts:
{$this->matchedSampleExcerptContext($matchedSamples, self::REVIEW_MATCH_LIMIT, self::REVIEW_EXCERPT_LIMIT)}

Matched clean/false-positive sample excerpts:
{$this->matchedSampleExcerptContextByTypes($testResult, ['clean', 'false_positive'], self::REVIEW_MATCH_LIMIT, self::REVIEW_EXCERPT_LIMIT)}

Matched clean file paths:
{$matchedFileLines}

Matched WordPress core file paths:
{$this->matchedWordPressFileContext($testResult, self::REVIEW_WORDPRESS_FILE_LIMIT)}

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
    private function matchedSampleExcerptContext(array $matchedSamples, int $limit = 6, int $excerptLimit = 1200): string
    {
        $names = collect($matchedSamples)
            ->pluck('sample')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->take($limit)
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
            ->map(function (WordPressSignatureSample $sample) use ($excerptLimit): string {
                $excerpt = mb_substr((string) $sample->content, 0, $excerptLimit);

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
     * @param  array<string, mixed>  $testResult
     * @param  array<int, string>  $types
     */
    private function matchedSampleExcerptContextByTypes(array $testResult, array $types, int $limit = 6, int $excerptLimit = 1200): string
    {
        $matches = is_array($testResult['matched_files'] ?? null) ? $testResult['matched_files'] : [];
        $names = collect($matches)
            ->filter(function ($match) use ($types): bool {
                return is_array($match) && in_array((string) ($match['type'] ?? ''), $types, true);
            })
            ->pluck('sample')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        if ($names === []) {
            return '- No matched clean sample excerpts available.';
        }

        $samples = WordPressSignatureSample::query()
            ->whereIn('name', $names)
            ->orderBy('id', 'desc')
            ->get(['name', 'family', 'sample_type', 'content']);

        if ($samples->isEmpty()) {
            return '- No matched clean sample excerpts available.';
        }

        return $samples
            ->map(function (WordPressSignatureSample $sample) use ($excerptLimit): string {
                $excerpt = mb_substr((string) $sample->content, 0, $excerptLimit);

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
     * @param  array<string, mixed>  $testResult
     */
    private function matchedWordPressFileContext(array $testResult, int $limit = 40): string
    {
        $matches = is_array($testResult['matched_wordpress_files'] ?? null) ? $testResult['matched_wordpress_files'] : [];

        if ($matches === []) {
            return '- No matched WordPress core files.';
        }

        return collect($matches)
            ->filter(fn ($match): bool => is_array($match))
            ->map(fn ($match): string => '- ' . (string) ($match['file_path'] ?? $match['sample'] ?? 'unknown file'))
            ->take($limit)
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $testResult
     * @param  array<int, string>  $types
     */
    private function matchedFileListContext(array $testResult, array $types, int $limit = 10): string
    {
        $lines = array_map(
            static fn (string $path): string => '- ' . $path,
            array_slice($this->matchedFilePathsFromResult($testResult, $types), 0, $limit)
        );

        return $lines === [] ? '- No matched clean file paths.' : implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $testResult
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    private function matchedFilePathsFromResult(array $testResult, array $types): array
    {
        $matches = is_array($testResult['matched_files'] ?? null) ? $testResult['matched_files'] : [];

        return collect($matches)
            ->filter(function ($match) use ($types): bool {
                return is_array($match) && in_array((string) ($match['type'] ?? ''), $types, true);
            })
            ->map(fn ($match): string => (string) ($match['file_path'] ?? $match['sample'] ?? 'unknown file'))
            ->filter(fn (string $path): bool => trim($path) !== '')
            ->unique()
            ->values()
            ->all();
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
        $matchedCount = (int) ($currentSummary['matched_count'] ?? 0);
        $familyCount = count($currentSummary['families'] ?? []);

        return ($recommendation === 'narrow' || in_array($overlapRisk, ['medium', 'high'], true))
            && ($matchedCount > 2 || $familyCount > 1);
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

    private function sourceSampleForSignature(WordPressMalwareSignature $signature): ?WordPressSignatureSample
    {
        $sourceSampleName = $this->extractSourceSampleName($signature);

        if ($sourceSampleName === null) {
            return null;
        }

        return WordPressSignatureSample::query()
            ->where('name', $sourceSampleName)
            ->first(['name', 'family', 'sample_type', 'content']);
    }

    private function sourceSampleContext(?WordPressSignatureSample $sourceSample): string
    {
        if (! $sourceSample instanceof WordPressSignatureSample) {
            return '- No linked source sample was found.';
        }

        return sprintf(
            "- %s | %s | %s\n%s",
            $sourceSample->name,
            $sourceSample->sample_type ?: 'unknown',
            $sourceSample->family ?: 'n/a',
            mb_substr((string) $sourceSample->content, 0, 1500)
        );
    }

    private function sourceMatchContext(WordPressMalwareSignature $signature, ?WordPressSignatureSample $sourceSample): string
    {
        if (! $sourceSample instanceof WordPressSignatureSample) {
            return '- No linked source sample was found.';
        }

        $content = (string) $sourceSample->content;
        $pattern = (string) $signature->pattern;

        if (trim($content) === '' || @preg_match($pattern, '') === false) {
            return '- The current signature pattern is not valid enough to extract a source match excerpt.';
        }

        $matched = @preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        if ($matched !== 1 || ! isset($matches[0][0], $matches[0][1])) {
            return '- The current signature did not produce a direct source-match excerpt.';
        }

        $matchText = (string) $matches[0][0];
        $offset = (int) $matches[0][1];
        $start = max(0, $offset - 250);
        $length = strlen($matchText) + 500;
        $excerpt = substr($content, $start, $length);

        return $excerpt === false || trim($excerpt) === ''
            ? '- The current signature did not produce a direct source-match excerpt.'
            : $excerpt;
    }

    /**
     * @param  array<string, mixed>  $currentResult
     * @param  array<int, array<string, mixed>>  $attempts
     */
    private function buildAutoFixFeedback(array $currentResult, array $attempts): string
    {
        $summary = is_array($currentResult['summary'] ?? null) ? $currentResult['summary'] : [];
        $lines = [
            sprintf(
                'Current blockers: malware_hits=%d, clean_hits=%d, false_positive_hits=%d, wordpress_core_hits=%d.',
                (int) ($summary['malware_hits'] ?? 0),
                (int) ($summary['clean_hits'] ?? 0),
                (int) ($summary['false_positive_hits'] ?? 0),
                (int) ($summary['wordpress_core_hits'] ?? 0),
            ),
        ];

        $cleanPaths = array_slice($this->matchedFilePathsFromResult($currentResult, ['clean', 'false_positive', 'clean_wordpress_core']), 0, 8);

        if ($cleanPaths !== []) {
            $lines[] = 'Avoid matching these clean files: ' . implode(', ', $cleanPaths);
        }

        if ($attempts !== []) {
            $lines[] = 'Prior failed auto-fix attempts:';

            foreach (array_slice($attempts, -3) as $attempt) {
                $lines[] = sprintf(
                    '- Attempt %d: %s',
                    (int) ($attempt['attempt'] ?? 0),
                    (string) ($attempt['message'] ?? 'Unknown failure')
                );
            }
        }

        $lines[] = 'The next revision must still match the original source malware sample, keep malware coverage, and reduce WordPress clean-file hits to zero.';

        return implode("\n", $lines);
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

        if ($pattern === '') {
            throw new RuntimeException($errorMessage . ' Missing "pattern" in AI response.');
        }

        if ($label === '') {
            throw new RuntimeException($errorMessage . ' Missing "label" in AI response.');
        }

        if (($backreferenceError = $this->invalidBackreferenceError($pattern)) !== null) {
            throw new RuntimeException($errorMessage . ' ' . $backreferenceError);
        }

        if (@preg_match($pattern, '') === false) {
            throw new RuntimeException($errorMessage . sprintf(
                ' Invalid regex received: %s',
                $this->truncateForError($pattern, 220)
            ));
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
        $response = $this->sendChatCompletionRequest($apiKey, $model, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);

        $payload = $response->json();
        $contentText = (string) data_get($payload, 'choices.0.message.content', '');
        $decoded = json_decode($contentText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'OpenAI returned an invalid JSON suggestion: %s',
                $this->truncateForError($contentText, 300)
            ));
        }

        return $decoded;
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     */
    private function sendChatCompletionRequest(string $apiKey, string $model, array $messages): \Illuminate\Http\Client\Response
    {
        $response = Http::withToken($apiKey)
            ->timeout(self::OPENAI_TIMEOUT_SECONDS)
            ->retry(
                self::OPENAI_MAX_RETRIES,
                self::OPENAI_RETRY_SLEEP_MS,
                function (\Throwable $exception, $request): bool {
                    return true;
                },
                throw: false
            )
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatOpenAiHttpError($response));
        }

        return $response;
    }

    private function formatOpenAiHttpError(\Illuminate\Http\Client\Response $response): string
    {
        $status = $response->status();
        $body = trim($response->body());
        $body = $this->truncateForError($body, 400);

        if ($body === '') {
            return sprintf('OpenAI request failed with HTTP %d and an empty response body.', $status);
        }

        return sprintf('OpenAI request failed with HTTP %d: %s', $status, $body);
    }

    private function truncateForError(string $value, int $limit): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit) . '...'
            : $value;
    }

    private function patternMatchesContent(string $pattern, string $content): bool
    {
        if (trim($content) === '' || @preg_match($pattern, '') === false) {
            return false;
        }

        return @preg_match($pattern, $content) === 1;
    }

    private function invalidBackreferenceError(string $pattern): ?string
    {
        if (! preg_match('/^(.)(.*)\\1([a-z]*)$/s', $pattern, $matches)) {
            return null;
        }

        $body = (string) ($matches[2] ?? '');
        $captureCount = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === '\\') {
                $i++;
                continue;
            }

            if ($char !== '(') {
                continue;
            }

            $next = $body[$i + 1] ?? '';

            if ($next === '?') {
                $modifier = $body[$i + 2] ?? '';

                if (in_array($modifier, [':', '=', '!', '>'], true)) {
                    continue;
                }
            }

            $captureCount++;
        }

        preg_match_all('/(?<!\\\\)\\\\([1-9][0-9]*)/', $body, $backreferenceMatches);

        foreach ($backreferenceMatches[1] ?? [] as $backreference) {
            $groupNumber = (int) $backreference;

            if ($groupNumber > $captureCount) {
                return sprintf(
                    'Invalid backreference \\%d: the pattern defines only %d capture group(s).',
                    $groupNumber,
                    $captureCount
                );
            }
        }

        return null;
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
- Do not use backreferences like `\1` or `\2` unless that capture group is explicitly defined earlier in the same pattern.
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
    private function buildFailedRevisionCorrectionPrompt(WordPressMalwareSignature $signature, array $previousSuggestion, array $testResult, string $failureReason, ?WordPressSignatureSample $sourceSample = null): string
    {
        $previousJson = $this->json($previousSuggestion);
        $sourceSampleContext = $this->sourceSampleContext($sourceSample);
        $sourceMatchContext = $this->sourceMatchContext($signature, $sourceSample);
        $lastAiReview = is_array($signature->last_ai_review) ? $signature->last_ai_review : [];
        $autofixFeedback = trim((string) ($lastAiReview['autofix_feedback'] ?? ''));
        $autofixFeedbackContext = $autofixFeedback !== '' ? $autofixFeedback : '- No prior auto-fix attempt feedback.';

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

Original source sample:
{$sourceSampleContext}

Current signature match inside the source sample:
{$sourceMatchContext}

Auto-fix feedback from prior failed attempts:
{$autofixFeedbackContext}

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
- Do not use backreferences like `\1` or `\2` unless that capture group is explicitly defined earlier in the same pattern.
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
        $lastError = 'OpenAI returned a corrected regex that still failed validation.';

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
            $lastError = $failureReason;
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
    private function correctCandidateForRevision(string $apiKey, string $model, WordPressMalwareSignature $signature, array $previousSuggestion, array $testResult, array $malwareSamples, array $currentSummary, ?string $sourceSampleName, ?WordPressSignatureSample $sourceSample, string $failureReason): array
    {
        $lastError = 'OpenAI returned a corrected revised regex that still failed validation.';
        $sourceSampleContent = $sourceSample?->content ? (string) $sourceSample->content : null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $decoded = $this->requestJsonSuggestion(
                $apiKey,
                $model,
                'You correct malware-signature revisions for a WordPress security product. Return exactly one JSON object only. Never approve signatures.',
                $this->buildFailedRevisionCorrectionPrompt($signature, $previousSuggestion, $testResult, $failureReason . " Attempt {$attempt} of 2.", $sourceSample)
            );

            try {
                $candidate = $this->normalizeSignatureCandidate($decoded, $signature->name, $lastError, $signature->signature_type, (int) ($signature->score ?? 1));
            } catch (RuntimeException $exception) {
                $lastError = $exception->getMessage();
                $previousSuggestion = $decoded;
                continue;
            }

            $validation = $this->validatePatternAgainstSamples($candidate['pattern'], $malwareSamples);

            if (
                $validation['matches_any']
                && ($sourceSampleContent === null || $this->patternMatchesContent($candidate['pattern'], $sourceSampleContent))
                && (! $this->revisionMustNarrow($signature, $currentSummary) || $this->isNarrowerRevision($validation, $currentSummary, $sourceSampleName))
            ) {
                return $candidate;
            }

            $failureReason = ($sourceSampleContent !== null && ! $this->patternMatchesContent($candidate['pattern'], $sourceSampleContent))
                ? 'The corrected revision no longer matches the original source malware sample. It must continue matching that source sample.'
                : (! $validation['matches_any']
                ? sprintf('The corrected revision still did not match stored malware samples. %s', $validation['reason'])
                : sprintf('The corrected revision still overlaps too broadly. It matched %d sample(s) across %d family/families and must be narrower while still matching the source sample.', $validation['matched_count'], count($validation['families'])));
            $lastError = $failureReason;
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
