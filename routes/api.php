<?php
use App\Http\Controllers\PressReviewController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TeamPartnerController;

use App\Http\Controllers\UserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SubmissionController;
// use Illuminate\Support\Facades\Broadcast;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('data/team', [TeamPartnerController::class, 'getTeamMembers']);
Route::post('data/partner', [TeamPartnerController::class, 'getPartners']);


// Broadcast::routes(['middleware' => ['auth:api']]);
Route::controller(UserController::class)->group(function () {
    route::post('/login','login');
    route::post('/register','store');
    Route::get('/data/{type}', 'getdata');
    Route::get('/downloadmemoir/{id}', 'downloadmemoir');
  
    Route::get('/downloadbook/{id}', 'downloadbook');
    Route::get('/getbasedata', 'index');
    route::post('/updatearticleview','updatearticleview');
});


// Routes publiques
Route::get('/press-reviews', [PressReviewController::class, 'index']);
Route::get('/press-reviews/popular-tags', [PressReviewController::class, 'popularTags']);
Route::get('/press-reviews/{slug}', [PressReviewController::class, 'show']);

// Routes admin (protégées)
Route::middleware('auth:api')->group(function () {
    Route::get('/admin/press-reviews', [PressReviewController::class, 'adminIndex']);
    Route::get('/admin/press-reviews/{id}', [PressReviewController::class, 'adminShow']);
    Route::post('/admin/press-reviews', [PressReviewController::class, 'store']);
    Route::put('/admin/press-reviews/{id}', [PressReviewController::class, 'update']);
    Route::delete('/admin/press-reviews/{id}', [PressReviewController::class, 'destroy']);
    Route::put('/admin/press-reviews/{id}/status', [PressReviewController::class, 'updateStatus']);
    Route::put('/admin/press-reviews/{id}/featured', [PressReviewController::class, 'toggleFeatured']);
    Route::post('/admin/press-reviews/upload-image', [PressReviewController::class, 'uploadImage']);
});

// Route publique pour soumettre un contact
Route::post('/contacts', [ContactController::class, 'store']);
Route::post('/submissions', [SubmissionController::class, 'store']);
Route::middleware(['auth:api'])->prefix('admin')->group(function () {
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
    Route::put('/submissions/{id}/status', [SubmissionController::class, 'updateStatus']);
    Route::get('/submissions/{id}/download', [SubmissionController::class, 'download']);
    Route::delete('/submissions/{id}', [SubmissionController::class, 'destroy']);
    Route::get('/submissions/statistics', [SubmissionController::class, 'statistics']);
});
Route::middleware('auth:api')->group(function () {


 Route::get('team_members', [TeamPartnerController::class, 'adminGetTeamMembers']);
    Route::post('team_members', [TeamPartnerController::class, 'saveTeamMember']);
    Route::put('team_members/{id}', [TeamPartnerController::class, 'saveTeamMember']);
    Route::delete('team_members/{id}', [TeamPartnerController::class, 'deleteTeamMember']);
    
    // Partners
    Route::get('partners', [TeamPartnerController::class, 'adminGetPartners']);
    Route::post('partners', [TeamPartnerController::class, 'savePartner']);
    Route::put('partners/{id}', [TeamPartnerController::class, 'savePartner']);
    Route::delete('partners/{id}', [TeamPartnerController::class, 'deletePartner']);   
   Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::put('/contacts/{id}/status', [ContactController::class, 'updateStatus']);
    Route::post('/contacts/{id}/replies', [ContactController::class, 'addReply']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
    Route::get('/contacts/statistics', [ContactController::class, 'statistics']);
    Route::controller(AdminController::class)->group(function () {
        Route::get('/getmemoir', 'getmemoir');
        Route::get('/getarticle', 'getarticle');
        Route::get('/getbook', 'getbook');
        Route::get('/adminbase', 'index');
        Route::post('/saveDomaine', 'saveDomaine');
        Route::post('/saveCategorie', 'saveCategorie');
        Route::post('/saveVideo', 'saveVideo');
         Route::post('/saveService', 'saveService');
          Route::post('/saveArticle', 'saveArticle');
          Route::post('/saveMemoir', 'saveMemoir');
           Route::post('/saveBook', 'saveBook');
        route::delete('/deleteDomaine/{id}', 'deleteDomaine');
        route::delete('/deleteCategorie/{id}', 'deleteCategorie');
        route::delete('/deleteVideo/{id}', 'deleteVideo');
        route::delete('/deleteBook/{id}', 'deleteBook');
        route::delete('/deleteService/{id}', 'deleteService');
        route::delete('/deleteMemoir/{id}', 'deleteMemoir');
          route::delete('/deleteArticle/{id}', 'deleteArticle');
      
    });
});
