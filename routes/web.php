<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [PortalController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/hris.php';
