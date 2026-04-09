<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AboutController extends Controller
{
    private const FOUNDER_PHOTO_PATH = '/tmp/IMG_20260408_120639.jpg';

    public function show(): View
    {
        return view('marketing.about');
    }

    public function founderPhoto(): BinaryFileResponse
    {
        abort_unless(is_file(self::FOUNDER_PHOTO_PATH), 404);

        $mime = mime_content_type(self::FOUNDER_PHOTO_PATH) ?: 'image/jpeg';

        return response()->file(self::FOUNDER_PHOTO_PATH, [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
