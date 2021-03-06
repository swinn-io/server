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

Route::get('/', 'App\Http\Controllers\FrontEndController@welcome');
Route::prefix('login')->group(function () {
    Route::get('/', 'App\Http\Controllers\LoginController@home')->name('login');
    Route::get('redirect/{provider}', 'App\Http\Controllers\LoginController@redirect')->name('login.redirect');
    Route::get('callback/{provider}', 'App\Http\Controllers\LoginController@callback')->name('login.callback');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/data', function () {
        return view('data.index');
    })->name('data.index');
    Route::get('/contact', \App\Http\Livewire\Contact\Index::class)->name('contact.index');
    Route::get('/contact/{id}', \App\Http\Livewire\Contact\Show::class)->name('contact.display');
    Route::get('/message', \App\Http\Livewire\Message\Index::class)->name('message.index');
});
