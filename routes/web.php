<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/broadcast', 'App\Http\Controllers\StreamingController@index');
    Route::get('/streaming/{streamId}', 'App\Http\Controllers\StreamingController@consumer');
    Route::post('/stream-offer', 'App\Http\Controllers\StreamingController@makeStreamOffer');
    Route::post('/stream-answer', 'App\Http\Controllers\StreamingController@makeStreamAnswer');
});

