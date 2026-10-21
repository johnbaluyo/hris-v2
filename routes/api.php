<?php

use App\Http\Controllers\DeviceAttendanceController;
use App\Http\Middleware\AuthenticateServiceClient;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateServiceClient::class.':attendance.ingest', 'throttle:attendance-ingest'])->group(function () {
    Route::get('dtr-time-log/max-id', [DeviceAttendanceController::class, 'maxId']);
    Route::post('dtr-time-log/batch-insert', [DeviceAttendanceController::class, 'store']);
});
