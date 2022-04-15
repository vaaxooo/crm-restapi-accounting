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


Route::group(['prefix' => 'auth'], function () {
    Route::post('login', '\App\Http\Controllers\AuthController@login');
    Route::post('logout', '\App\Http\Controllers\AuthController@logout');
    Route::post('refresh', '\App\Http\Controllers\AuthController@refresh');
    Route::post('me', '\App\Http\Controllers\AuthController@me');
});

Route::group(['prefix' => 'accounting'], function () {
    Route::get('offices', '\App\Http\Controllers\AccountingController@offices');
    Route::get('{office}/incomes', '\App\Http\Controllers\AccountingController@incomes');
    Route::get('{office}/expenses', '\App\Http\Controllers\AccountingController@expenses');
    Route::get('{office}/payouts', '\App\Http\Controllers\AccountingController@payouts');
    Route::get('{office}/weekly-report', '\App\Http\Controllers\AccountingController@weekly_report');

    Route::get('amqp', '\App\Http\Controllers\AccountingController@amqp');
});


Route::group(['prefix' => 'settings'], function () {
    Route::get('{office}/get', '\App\Http\Controllers\AccountingController@getOfficeData');
    Route::patch('{office}/update-name', '\App\Http\Controllers\AccountingController@setOfficeName');
});
