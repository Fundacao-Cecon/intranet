<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'documents.index' : 'login'));
Route::view('/login', 'access.login')->middleware('guest')->name('login');
Route::post('/login', [AccessController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::view('/senha', 'access.password')->name('password');
Route::post('/senha', [AccessController::class, 'changePassword'])->middleware('throttle:password-change');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AccessController::class, 'logout'])->name('logout');
    Route::get('/documentos', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/arquivos/{item}/visualizar', [DocumentController::class, 'read'])->whereNumber('item')->name('files.view');
    Route::get('/arquivos/{item}/download', [DocumentController::class, 'read'])->whereNumber('item')->name('files.download');
    Route::get('/arquivos/{item}/miniatura', [DocumentController::class, 'read'])->whereNumber('item')->name('files.thumbnail');
});
