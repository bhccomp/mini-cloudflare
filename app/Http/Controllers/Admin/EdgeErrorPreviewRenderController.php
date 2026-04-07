<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EdgeErrorPreviewRenderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response((string) $request->string('html'))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
