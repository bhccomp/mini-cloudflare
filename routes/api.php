<?php

use App\Http\Controllers\Api\PluginConnectController;
use App\Http\Controllers\Api\PluginFirewallSummaryController;
use App\Http\Controllers\Api\PackageChecksumController;
use App\Http\Controllers\Api\PluginFreeTokenRegistrationController;
use App\Http\Controllers\Api\PluginFreeTokenStatusController;
use App\Http\Controllers\Api\PluginFreeTokenVerifyController;
use App\Http\Controllers\Api\PluginPerformanceSummaryController;
use App\Http\Controllers\Api\PluginReportController;
use App\Http\Controllers\Api\PluginSignatureController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/plugin/checksums', PackageChecksumController::class);
Route::post('/plugin/free-token/register', PluginFreeTokenRegistrationController::class)->middleware('throttle:10,1');
Route::get('/plugin/free-token/status', PluginFreeTokenStatusController::class)->middleware('throttle:30,1');
Route::post('/plugin/free-token/verify', PluginFreeTokenVerifyController::class)->middleware('throttle:30,1');
Route::get('/plugin/signatures', PluginSignatureController::class)->middleware('throttle:60,1');
Route::post('/plugin/connect', PluginConnectController::class);
Route::post('/plugin/report', PluginReportController::class);
Route::get('/plugin/firewall-summary', PluginFirewallSummaryController::class);
Route::get('/plugin/performance-summary', PluginPerformanceSummaryController::class);
