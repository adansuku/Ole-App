<?php

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

Route::get('admin/idiomas', 'ModelosController@idiomas')->name('admin.idiomas');
Route::get('admin/deleteidiomas/{id}', 'ModelosController@deletetatuajes');

Route::get('admin/tatuajes', 'ModelosController@tatuajes')->name('admin.tatuajes');
Route::get('admin/deletetatuajes/{id}', 'ModelosController@deletetatuajes');

Route::get('admin/cicatrices', 'ModelosController@cicatrices')->name('admin.cicatrices');
Route::get('admin/deletecicatrices/{id}', 'ModelosController@deletecicatrices');

Route::get('admin/piercings', 'ModelosController@piercings')->name('admin.piercings');
Route::get('admin/deletepiercings/{id}', 'ModelosController@deletepiercings');

Route::get('admin/deportes', 'ModelosController@deportes')->name('admin.deportes');
Route::get('admin/deletedeportes/{id}', 'ModelosController@deletedeportes');

    Route::post('modelos/pdf', ['uses' =>'ModelosController@pdf']);
    Route::post('package/api', ['uses' =>'ModelosController@package']);
    Route::post('addpackage/api', ['uses' =>'ModelosController@addpackage']);
    Route::post('getpackages/api', ['uses' =>'ModelosController@getpackages']);
    Route::get('packages/pdf/{id}', ['as' => 'package_export', 'uses' =>'PackagesController@pdf']);


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();

    Route::post('modelos/pdf', ['uses' =>'ModelosController@pdf']);
    Route::post('package/api', ['uses' =>'ModelosController@package']);
    Route::post('addpackage/api', ['uses' =>'ModelosController@addpackage']);
    Route::post('getpackages/api', ['uses' =>'ModelosController@getpackages']);
    Route::get('packages/pdf/{id}', ['as' => 'package_export', 'uses' =>'PackagesController@pdf']);
});