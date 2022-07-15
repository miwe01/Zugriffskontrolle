<?php

use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Controller2;
use App\Http\Controllers\FactoryController;

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

// Route::get('/', function () {
//     return view('welcome');
// });

//Route::get('/', [Controller::class, 'test']);

//Route::get('/', [Controller::class, 'test']);

Route::get('/', function () {
    return view('demo');
});
Route::get('/add', function () {
    return view('add');
});


Route::get('/graph', [BaseController::class, 'test']);

Route::get('/access', [Controller::class, 'evaluatePolicy']);
// Route::get('/test2', [Controller2::class, 'evaluatePolicy']);
Route::get('/test3', [BaseController::class, 'test']);

Route::get('/create', [FactoryController::class, 'create']);