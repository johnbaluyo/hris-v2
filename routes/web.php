<?php

use App\Http\Controllers\EmploymentCertificateController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/verification/{token}', [EmploymentCertificateController::class, 'verify'])->middleware('throttle:20,1');

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PortalController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/hris.php';
