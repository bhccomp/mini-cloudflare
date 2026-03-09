<?php

use App\Http\Controllers\Api\PluginConnectController;
use App\Http\Controllers\Api\PluginFirewallSummaryController;
use App\Http\Controllers\Api\PackageChecksumController;
use App\Http\Controllers\Api\PluginPerformanceSummaryController;
use App\Http\Controllers\Api\PluginReportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/plugin/checksums', PackageChecksumController::class);
Route::post('/plugin/connect', PluginConnectController::class);
Route::post('/plugin/report', PluginReportController::class);
Route::get('/plugin/firewall-summary', PluginFirewallSummaryController::class);
Route::get('/plugin/performance-summary', PluginPerformanceSummaryController::class);
