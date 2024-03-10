<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('authorization', [\App\Http\Controllers\UsersController::class, 'authorization']);
Route::post('registration', [\App\Http\Controllers\UsersController::class, 'registration']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('logout', [\App\Http\Controllers\UsersController::class, 'logout']);
    Route::post('files', [\App\Http\Controllers\FilesController::class, 'uploadFiles']);
    Route::get('files/disk', [\App\Http\Controllers\FilesController::class, 'getUserFiles']);
    Route::patch('files/{fileId}', [\App\Http\Controllers\FilesController::class, 'updateFile']);
    Route::delete('files/{fileId}', [\App\Http\Controllers\FilesController::class, 'deleteFile']);
    Route::get('files/{fileId}', [\App\Http\Controllers\FilesController::class, 'downloadFile']);
    Route::post('files/{fileId}/accesses', [\App\Http\Controllers\FilesController::class, 'setFileAccesses']);
    Route::delete('files/{fileId}/accesses', [\App\Http\Controllers\FilesController::class, 'deleteFileAccesses']);
    Route::get('shared', [\App\Http\Controllers\UsersController::class, 'sharedFiles']);
});
