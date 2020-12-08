<?php

use App\Notifications\MessageCreated;
use Illuminate\Support\Facades\Notification;
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

Route::get('/', 'FrontEndController@welcome');
Route::prefix('login')->group(function () {
    Route::get('/', 'LoginController@home')->name('login');
    Route::get('redirect/{provider}', 'LoginController@redirect')->name('login.redirect');
    Route::get('callback/{provider}', 'LoginController@callback')->name('login.callback');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function (){
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/data', function () {
        return view('data.index');
    })->name('data.index');
    Route::get('/contact', function () {
        return view('contact');
    })->name('contact.index');
});
