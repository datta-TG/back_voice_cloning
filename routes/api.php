<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/test', function (){
    return \Illuminate\Support\Facades\Storage::disk('s3')->allDirectories('');
});

Route::middleware('auth:api')->group(function () {
    Route::post('/upload/{type}', [FileController::class, 'upload']);
    Route::get('/list-files/{type}', [FileController::class, 'list']);
    Route::post('/generate-voice', [FileController::class, 'generateVoice']);
    Route::post('/generate-video', [FileController::class, 'generateVideo']);
    Route::post('/get-url/{type}', [FileController::class, 'preview']);
});


Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::middleware('auth:api')->group(function () {
        Route::delete('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/update-user', [AuthController::class, 'updateUser']);
    });
});

Route::prefix('file')->group(function () {
    Route::middleware('auth:api')->group(function () {
/*        Route::post('/', [FileController::class, 'store']);
        Route::get('/', [FileController::class, 'getFiles']);
        Route::get('/{id}', [FileController::class, 'show']);
        Route::put('/{id}', [FileController::class, 'update']);
        Route::delete('/{id}', [FileController::class, 'destroy']);*/
    });
});
