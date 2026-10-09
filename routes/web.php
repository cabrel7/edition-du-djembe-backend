<?php
use Illuminate\Support\Facades\Route;
use App\Events\Hello;
use App\Http\Controllers\SitemapController;

use App\Http\Controllers\RobotsController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/robots.txt', [RobotsController::class, 'index']);

Route::get('/sitemap.xml', [SitemapController::class, 'index']);

Route::get('/broadcast',function(){

    broadcast(new Hello());
    return "Event has been sent!";
});
