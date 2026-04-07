<?php

namespace App\Services\Bunny;

use App\Models\SystemSetting;
use Illuminate\Support\Arr;

class BunnyEdgeErrorPageService
{
    private const SCRIPT_TYPE_MIDDLEWARE = 2;

    private const SETTING_KEY = 'bunny';

    private const SCRIPT_NAME = 'firephage-edge-error-pages';

    private const DOMAIN_PLACEHOLDER = '__FIREPHAGE_DOMAIN__';

    public function __construct(protected BunnyApiService $api) {}

    public function syncSharedScript(): array
    {
        $scriptId = $this->configuredScriptId();
        $code = $this->buildMiddlewareSource();
        $created = false;

        if ($scriptId > 0 && ! $this->isMiddlewareScript($scriptId)) {
            $scriptId = 0;
        }

        if ($scriptId <= 0) {
            $scriptId = $this->createScript($this->scriptName());
            $created = true;
        }

        $this->updateScriptCode($scriptId, $code);
        $this->publishScript($scriptId);
        $this->persistScriptId($scriptId);

        return [
            'script_id' => $scriptId,
            'created' => $created,
            'updated' => true,
            'name' => $this->scriptName(),
        ];
    }

    public function configuredScriptId(): int
    {
        return (int) Arr::get($this->settingValue(), 'edge_error_script_id', 0);
    }

    public function scriptName(): string
    {
        $name = trim((string) Arr::get($this->settingValue(), 'edge_error_script_name', self::SCRIPT_NAME));

        return $name !== '' ? $name : self::SCRIPT_NAME;
    }

    public function buildMiddlewareSource(): string
    {
        $templates = json_encode($this->compiledTemplates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $placeholder = self::DOMAIN_PLACEHOLDER;

        return <<<JS
const DOMAIN_PLACEHOLDER = {$this->jsonString($placeholder)};
const TEMPLATES = {$templates};
const UNAVAILABLE_STATUSES = new Set([500, 502, 503, 504]);

function pickTemplateKey(status) {
  if (status === 404) {
    return "404";
  }

  if (status === 403) {
    return "403";
  }

  if (status === 429) {
    return "429";
  }

  if (UNAVAILABLE_STATUSES.has(status)) {
    return "5xx";
  }

  return null;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function hostnameFor(request) {
  try {
    return new URL(request.url).hostname;
  } catch (_error) {
    return request?.headers?.get("host") || "this website";
  }
}

function renderTemplate(templateKey, host, status) {
  const template = TEMPLATES[templateKey];

  if (!template) {
    return null;
  }

  return template
    .replaceAll(DOMAIN_PLACEHOLDER, escapeHtml(host || "this website"))
    .replaceAll("__FIREPHAGE_STATUS__", String(status));
}

addEventListener("onOriginResponse", (event) => {
  const response = event.response;
  const status = Number(response?.status || 0);
  const templateKey = pickTemplateKey(status);

  if (!response || !templateKey) {
    return;
  }

  const html = renderTemplate(templateKey, hostnameFor(event.request), status);
  const headers = new Headers(response.headers || {});
  headers.set("content-type", "text/html; charset=utf-8");
  headers.set("cache-control", "no-store, no-cache, must-revalidate");
  headers.set("pragma", "no-cache");
  headers.delete("content-length");
  headers.delete("content-encoding");

  event.response = new Response(html, {
    status,
    statusText: response.statusText,
    headers,
  });
});
JS;
    }

    public function buildNativeUnavailableHtml(string $domainLabel = 'this website'): string
    {
        return $this->renderTemplate('edge-errors.unavailable', [
            'title' => '{{status_title}}',
            'headline' => '{{status_title}}',
            'lede' => '{{status_description}}',
            'domainLabel' => '{{target_hostname}}',
            'statusCode' => '{{status_code}}',
            'footerNote' => 'FirePhage edge fallback is active for {{target_hostname}} while the upstream service recovers.',
            'sideTitle' => 'Request context',
            'sideBody' => 'Bunny reported {{status_title}} for {{target_hostname}} while serving this request.',
            'recoveryCopy' => 'If this persists, review the upstream service, recent deploys, and dependencies behind {{target_hostname}}. Visitor IP: {{user_ip}}.',
        ]);
    }

    public function buildPreviewHtml(string $template, string $domainLabel = 'preview.firephage.com'): string
    {
        $domainLabel = trim($domainLabel) !== '' ? trim($domainLabel) : 'preview.firephage.com';

        return match ($template) {
            '403' => $this->renderTemplate('edge-errors.forbidden', [
                'domainLabel' => $domainLabel,
                'statusCode' => 403,
            ]),
            '404' => $this->renderTemplate('edge-errors.not-found', [
                'domainLabel' => $domainLabel,
                'statusCode' => 404,
            ]),
            '429' => $this->renderTemplate('edge-errors.rate-limited', [
                'domainLabel' => $domainLabel,
                'statusCode' => 429,
            ]),
            default => $this->renderTemplate('edge-errors.unavailable', [
                'domainLabel' => $domainLabel,
                'statusCode' => '502/504',
            ]),
        };
    }

    /**
     * @return array<string, string>
     */
    public function compiledTemplates(): array
    {
        $domain = self::DOMAIN_PLACEHOLDER;

        return [
            '404' => $this->renderTemplate('edge-errors.not-found', [
                'domainLabel' => $domain,
                'statusCode' => 404,
            ]),
            '403' => $this->renderTemplate('edge-errors.forbidden', [
                'domainLabel' => $domain,
                'statusCode' => 403,
            ]),
            '429' => $this->renderTemplate('edge-errors.rate-limited', [
                'domainLabel' => $domain,
                'statusCode' => 429,
            ]),
            '5xx' => $this->renderTemplate('edge-errors.unavailable', [
                'domainLabel' => $domain,
                'statusCode' => '__FIREPHAGE_STATUS__',
            ]),
        ];
    }

    protected function createScript(string $name): int
    {
        $payloads = [
            ['Name' => $name, 'ScriptType' => self::SCRIPT_TYPE_MIDDLEWARE],
            ['name' => $name, 'scriptType' => self::SCRIPT_TYPE_MIDDLEWARE],
            ['Name' => $name, 'Type' => self::SCRIPT_TYPE_MIDDLEWARE],
            ['name' => $name, 'type' => self::SCRIPT_TYPE_MIDDLEWARE],
            ['Name' => $name],
            ['name' => $name],
        ];

        $lastError = 'Unable to create Bunny edge script.';

        foreach ($payloads as $payload) {
            $response = $this->api->client()->post('/compute/script', $payload);

            if (! $response->successful()) {
                $lastError = $this->responseError($response->json(), $lastError);

                continue;
            }

            $scriptId = (int) (
                Arr::get($response->json(), 'Id')
                ?? Arr::get($response->json(), 'id')
                ?? 0
            );

            if ($scriptId > 0) {
                return $scriptId;
            }
        }

        throw new \RuntimeException($lastError);
    }

    protected function isMiddlewareScript(int $scriptId): bool
    {
        $response = $this->api->client()->get("/compute/script/{$scriptId}");

        if (! $response->successful()) {
            return false;
        }

        $type = Arr::get($response->json(), 'ScriptType');

        return $type === self::SCRIPT_TYPE_MIDDLEWARE || $type === 'Middleware';
    }

    protected function updateScriptCode(int $scriptId, string $code): void
    {
        $response = $this->api->client()->post("/compute/script/{$scriptId}/code", [
            'Code' => $code,
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseError($response->json(), 'Unable to update Bunny edge script code.'));
        }
    }

    protected function publishScript(int $scriptId): void
    {
        $response = $this->api->client()->post("/compute/script/{$scriptId}/publish", [
            'Note' => 'FirePhage edge error pages',
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            throw new \RuntimeException($this->responseError($response->json(), 'Unable to publish Bunny edge script release.'));
        }
    }

    protected function renderTemplate(string $view, array $data): string
    {
        return trim((string) view($view, $data)->render());
    }

    protected function persistScriptId(int $scriptId): void
    {
        $setting = SystemSetting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => [], 'is_encrypted' => false]
        );

        $value = $this->settingValue();
        $value['edge_error_script_id'] = $scriptId;
        $value['edge_error_script_name'] = $this->scriptName();

        $setting->forceFill(['value' => $value])->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function settingValue(): array
    {
        $value = SystemSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_array($value) ? $value : [];
    }

    protected function jsonString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function responseError(?array $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        return (string) (
            Arr::get($payload, 'Message')
            ?? Arr::get($payload, 'message')
            ?? Arr::get($payload, 'Error')
            ?? Arr::get($payload, 'error')
            ?? $fallback
        );
    }
}
