<?php

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

Route::get('/', [\App\Http\Controllers\FrontEnd\PageController::class, 'index']);
Route::prefix('login')->group(function () {
    Route::get('/', 'App\Http\Controllers\LoginController@home')->name('login');
    Route::get('redirect/{provider}', 'App\Http\Controllers\LoginController@redirect')->name('login.redirect');
    Route::get('callback/{provider}', 'App\Http\Controllers\LoginController@callback')->name('login.callback');
});
Route::post('logout', 'App\Http\Controllers\LoginController@logout')->name('logout');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [\App\Http\Controllers\FrontEnd\DashboardController::class, 'index'])->name('dashboard');
    });
    Route::prefix('thread')->group(function () {
        Route::get('{thread}', [\App\Http\Controllers\FrontEnd\ThreadController::class, 'show'])->name('thread.show');
    });
});
