<?php

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

Route::middleware('auth:api')->group(function () {
    Route::group(['prefix' => 'contact'], function () {
        Route::get('/', ['as' => 'contact', 'uses' => 'ContactController@index']);
        Route::get('{id}', ['as' => 'contact.show', 'uses' => 'ContactController@show']);
    });
    Route::group(['prefix' => 'message'], function () {
        Route::get('/', ['as' => 'message', 'uses' => 'MessageController@index']);
        Route::post('/', ['as' => 'message.store', 'uses' => 'MessageController@store']);
        Route::get('{id}', ['as' => 'message.show', 'uses' => 'MessageController@show']);
        Route::put('{id}', ['as' => 'message.update', 'uses' => 'MessageController@update']);
        Route::post('{id}', ['as' => 'message.new', 'uses' => 'MessageController@new']);
    });
    Route::group(['prefix' => 'user'], function () {
        Route::get('/me', ['as' => 'user.me', 'uses' => 'UserController@me']);
        Route::get('/{id}', ['as' => 'user.show', 'uses' => 'UserController@show']);
    });
});
