<?php

use App\Http\Controllers\DeviceAttendanceController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Middleware\AuthenticateServiceClient;
use Illuminate\Support\Facades\Route;

Route::post('notifications/external-create', [SystemNotificationController::class, 'create'])
    ->middleware([AuthenticateServiceClient::class.':notifications.publish', 'throttle:60,1']);

Route::middleware([AuthenticateServiceClient::class.':attendance.ingest', 'throttle:attendance-ingest'])->group(function () {
    Route::get('dtr-time-log/max-id', [DeviceAttendanceController::class, 'maxId']);
    Route::post('dtr-time-log/batch-insert', [DeviceAttendanceController::class, 'store']);
});
