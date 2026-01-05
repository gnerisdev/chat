<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;

Route::post('/chat', [Controller::class, 'sendMessage']);
Route::get('/chat/status', [Controller::class, 'getStatus']);


