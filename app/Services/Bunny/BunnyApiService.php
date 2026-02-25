<?php

namespace App\Services\Bunny;

use App\Models\SystemSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BunnyApiService
{
    public function client(): PendingRequest
    {
        $apiKey = $this->apiKey();

        if ($apiKey === '') {
            throw new \RuntimeException('Bunny API key is not configured in system settings.');
        }

        return Http::baseUrl(rtrim((string) config('edge.bunny.base_url', 'https://api.bunny.net'), '/'))
            ->acceptJson()
            ->withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(20);
    }

    public function apiKey(): string
    {
        $setting = SystemSetting::query()->where('key', 'bunny')->first();
        $value = $setting?->value;

        if (! is_array($value)) {
            return '';
        }

        return (string) ($value['api_key'] ?? '');
    }
}
