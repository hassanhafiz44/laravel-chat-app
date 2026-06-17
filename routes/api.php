<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\JobsDemoController;
use App\Http\Controllers\RelationshipDemoController;
use Illuminate\Support\Facades\Route;

Route::post('chat', [ChatController::class, 'send']);
Route::get('chat/{conversation}/messages', [ChatController::class, 'messages']);

Route::prefix('demo/jobs')->controller(JobsDemoController::class)->group(function () {
    Route::post('dispatch', 'dispatch');
    Route::post('chain', 'chain');
    Route::post('batch', 'batch');
});

Route::prefix('demo')->controller(RelationshipDemoController::class)->group(function () {
    Route::get('has-one', 'hasOne');
    Route::get('has-many', 'hasMany');
    Route::get('belongs-to', 'belongsTo');
    Route::get('belongs-to-many', 'belongsToMany');
    Route::get('has-many-through', 'hasManyThrough');
    Route::get('has-one-through', 'hasOneThrough');
    Route::get('morph-many', 'morphMany');
    Route::get('morph-to-many', 'morphToMany');
    Route::get('existence', 'existence');
});
