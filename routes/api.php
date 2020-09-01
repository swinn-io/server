<?php

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

Route::middleware('client')->group(function () {
    Route::group(['prefix' => 'message'], function () {
        Route::get('/', ['as' => 'message', 'uses' => 'MessageController@index']);
        Route::post('/', ['as' => 'message.store', 'uses' => 'MessageController@store']);
        Route::get('{id}', ['as' => 'message.show', 'uses' => 'MessageController@show']);
        Route::put('{id}', ['as' => 'message.update', 'uses' => 'MessageController@update']);
        Route::post('{id}', ['as' => 'message.new', 'uses' => 'MessageController@new']);
    });
});
