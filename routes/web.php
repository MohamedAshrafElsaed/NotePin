<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingProcessController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/', fn() => Inertia::render('Welcome'));
Route::get('/notes', fn() => Inertia::render('Notes/Index'));
Route::get('/notes/{id}', fn() => Inertia::render('Notes/Show'));
Route::get('/share/{token}', fn() => Inertia::render('Share/Show'));


Route::post('/recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::get('/recordings/{recording}', [RecordingController::class, 'json'])->name('recordings.json');
Route::get('/notes/{id}', [RecordingController::class, 'show'])->name('notes.show')->where('id', '[0-9]+');
Route::post('/recordings/{recording}/process', [RecordingProcessController::class, 'store'])->name('recordings.process');


Route::post('/recordings/{recording}/share', [ShareController::class, 'store'])->name('recordings.share');
Route::get('/share/{token}', [ShareController::class, 'show'])->name('share.show');



Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->name('locale.update')
    ->whereIn('locale', ['ar', 'en']);
