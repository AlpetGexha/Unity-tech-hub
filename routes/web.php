<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// HrHire has no login, so the main dashboard must stay outside any auth group.
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

require __DIR__.'/settings.php';

// HrHire has no login. This must stay outside the auth group above.
require __DIR__.'/hr.php';
