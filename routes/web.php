<?php

use App\Http\Controllers\TwitchAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('twitch/auth')->group(function () {
    Route::get('/', [TwitchAuthController::class, 'index']);
    Route::get('callback', [TwitchAuthController::class, 'callback']);
    Route::get('refresh/{twitchUserId}', [TwitchAuthController::class, 'refresh']);
    Route::get('validate/{twitchUserId}', [TwitchAuthController::class, 'validateToken']);
});
