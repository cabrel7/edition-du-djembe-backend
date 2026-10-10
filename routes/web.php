<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
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

// Healthcheck (monitoring / load balancer) — vérifie aussi la base.
Route::get('/up', function () {
    try {
        DB::select('select 1');
    } catch (\Throwable $e) {
        return response()->json(['status' => 'degraded'], 503);
    }

    return response()->json(['status' => 'ok'], 200);
})->name('up');
