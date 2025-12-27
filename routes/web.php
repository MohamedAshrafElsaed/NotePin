<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingProcessController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Locale
Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// Auth Routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/email', [EmailAuthController::class, 'send'])->name('auth.email.send');
Route::get('/auth/email/callback', [EmailAuthController::class, 'callback'])->name('auth.email.callback');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Recordings
Route::post('/recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::get('/recordings/{recording}', [RecordingController::class, 'json'])->name('recordings.json');
Route::post('/recordings/{recording}/process', [RecordingProcessController::class, 'store'])->name('recordings.process');
Route::post('/recordings/{recording}/share', [ShareController::class, 'store'])->name('recordings.share');

// Notes
Route::get('/notes', [NotesController::class, 'index'])->name('notes.index');
Route::get('/notes/{id}', [RecordingController::class, 'show'])->name('notes.show');

// Public Share
Route::get('/share/{token}', [ShareController::class, 'show'])->name('share.show');

// API
Route::post('/api/events', [EventController::class, 'store'])->name('api.events.store');


// Dashboard (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notes', fn() => redirect('/dashboard'));
});

Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->name('locale.update')
    ->whereIn('locale', ['ar', 'en']);
