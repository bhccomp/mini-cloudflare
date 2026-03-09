<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressMalwareSignatureService;
use Illuminate\Http\JsonResponse;

class PluginSignatureController extends Controller
{
    public function __invoke(WordPressMalwareSignatureService $service): JsonResponse
    {
        return response()
            ->json($service->manifest())
            ->header('Cache-Control', 'public, max-age=21600');
    }
}
