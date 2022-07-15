<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller2;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/allUsers', [\App\Http\Controllers\ApiController::class, 'allUsers_api']);
Route::get('/allResources', [\App\Http\Controllers\ApiController::class, 'allResources_api']);
// Route::get('/allResources', [\App\Http\Controllers\ApiController::class, 'allResources_api']);

Route::get('/addUser', [\App\Http\Controllers\ApiController::class, 'addUser_api']);
Route::get('/addFile', [\App\Http\Controllers\ApiController::class, 'addFile_api']);

Route::get('/addEdgeUserUser', [\App\Http\Controllers\ApiController::class, 'addEdgeUserUser_api']);
Route::get('/addEdgeUserFile', [\App\Http\Controllers\ApiController::class, 'addEdgeUserFile_api']);

Route::get('/deleteGraph', [\App\Http\Controllers\ApiController::class, 'deleteGraph_api']);