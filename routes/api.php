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
    route::post('/login', 'login')->middleware('throttle:6,1');
    route::post('/register', 'store')->middleware('throttle:6,1');
    Route::get('/data/{type}', 'getdata');
    Route::get('/downloadmemoir/{id}', 'downloadmemoir');
  
    Route::get('/downloadbook/{id}', 'downloadbook');
    Route::get('/getbasedata', 'index');
    route::post('/updatearticleview','updatearticleview');
    route::post('/updatememoirview','updatememoirview');
});


// Routes publiques
Route::get('/press-reviews', [PressReviewController::class, 'index']);
Route::get('/press-reviews/popular-tags', [PressReviewController::class, 'popularTags']);
Route::get('/press-reviews/{slug}', [PressReviewController::class, 'show']);

// Route publique pour soumettre un contact
Route::post('/contacts', [ContactController::class, 'store']);
Route::post('/submissions', [SubmissionController::class, 'store']);

// Déconnexion (révocation du token Passport courant)
Route::middleware('auth:api')->post('/logout', [UserController::class, 'logout']);

// Routes admin (protégées : auth + rôle admin)
Route::middleware(['auth:api', 'admin'])->group(function () {
    Route::get('/admin/press-reviews', [PressReviewController::class, 'adminIndex']);
    Route::get('/admin/press-reviews/{id}', [PressReviewController::class, 'adminShow']);
    Route::post('/admin/press-reviews', [PressReviewController::class, 'store']);
    Route::put('/admin/press-reviews/{id}', [PressReviewController::class, 'update']);
    Route::delete('/admin/press-reviews/{id}', [PressReviewController::class, 'destroy']);
    Route::put('/admin/press-reviews/{id}/status', [PressReviewController::class, 'updateStatus']);
    Route::put('/admin/press-reviews/{id}/featured', [PressReviewController::class, 'toggleFeatured']);
    Route::post('/admin/press-reviews/upload-image', [PressReviewController::class, 'uploadImage']);

    Route::prefix('admin')->group(function () {
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::get('/submissions/statistics', [SubmissionController::class, 'statistics']);
        Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
        Route::put('/submissions/{id}/status', [SubmissionController::class, 'updateStatus']);
        Route::get('/submissions/{id}/download', [SubmissionController::class, 'download']);
        Route::post('/submissions/{id}/send-email', [SubmissionController::class, 'sendEmail']);
        Route::delete('/submissions/{id}', [SubmissionController::class, 'destroy']);
    });

    // Team members
    Route::get('team_members', [TeamPartnerController::class, 'adminGetTeamMembers']);
    Route::post('team_members', [TeamPartnerController::class, 'saveTeamMember']);
    Route::put('team_members/{id}', [TeamPartnerController::class, 'saveTeamMember']);
    Route::delete('team_members/{id}', [TeamPartnerController::class, 'deleteTeamMember']);

    // Partners
    Route::get('partners', [TeamPartnerController::class, 'adminGetPartners']);
    Route::post('partners', [TeamPartnerController::class, 'savePartner']);
    Route::put('partners/{id}', [TeamPartnerController::class, 'savePartner']);
    Route::delete('partners/{id}', [TeamPartnerController::class, 'deletePartner']);

    // Contacts
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/statistics', [ContactController::class, 'statistics']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::put('/contacts/{id}/status', [ContactController::class, 'updateStatus']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);

    // Contenu éditorial
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
