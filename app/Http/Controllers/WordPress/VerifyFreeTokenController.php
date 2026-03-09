<?php

namespace App\Http\Controllers\WordPress;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressSubscriberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RuntimeException;

class VerifyFreeTokenController extends Controller
{
    public function __invoke(string $token, WordPressSubscriberService $service): View
    {
        try {
            $subscriber = $service->verify($token);

            return view('wordpress.free-token-verified', [
                'verified' => true,
                'siteHost' => $subscriber->site_host,
            ]);
        } catch (RuntimeException $exception) {
            return view('wordpress.free-token-verified', [
                'verified' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
