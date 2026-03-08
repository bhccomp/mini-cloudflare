<?php

use App\Http\Controllers\Api\PackageChecksumController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/plugin/checksums', PackageChecksumController::class);
