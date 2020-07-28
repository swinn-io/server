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

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('login')->group(function () {
    Route::get('/', 'LoginController@home')->name('login.home');
    Route::get('redirect/{provider}', 'LoginController@redirect')->name('login.redirect');
    Route::get('callback/{provider}', 'LoginController@callback')->name('login.callback');
});
