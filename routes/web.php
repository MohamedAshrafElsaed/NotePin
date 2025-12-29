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
use App\Http\Controllers\TextNoteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Home / Recording page - always accessible
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Login route for auth middleware redirect
Route::get('/login', function () {
    return redirect('/');
})->name('login');

// Locale
Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->name('locale.update')
    ->whereIn('locale', ['ar', 'en']);

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

// Recordings - public upload (audio)
Route::post('/recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::get('/recordings/{recording}', [RecordingController::class, 'json'])->name('recordings.json');
Route::post('/recordings/{recording}/process', [RecordingProcessController::class, 'store'])->name('recordings.process');

// Text notes - public (pasted text, auto-processed)
Route::post('/notes/text', [TextNoteController::class, 'store'])->name('notes.text.store');

// Share - requires auth
Route::post('/recordings/{recording}/share', [ShareController::class, 'store'])
    ->middleware('auth')
    ->name('recordings.share');

// Notes show - accessible for viewing own recordings
Route::get('/notes/{id}', [RecordingController::class, 'show'])->name('notes.show');

// Public Share
Route::get('/share/{token}', [ShareController::class, 'show'])->name('share.show');

// API
Route::post('/api/events', [EventController::class, 'store'])->name('api.events.store');

// Dashboard (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notes', [NotesController::class, 'index'])->name('notes.index');
});
