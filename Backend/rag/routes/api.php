<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\QueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/documents', [DocumentController::class, 'store']);
Route::get('/documents', [DocumentController::class, 'index']);
Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
Route::post('/query', [QueryController::class, 'ask']);
