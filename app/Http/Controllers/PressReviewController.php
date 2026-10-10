<?php
// app/Http/Controllers/PressReviewController.php

namespace App\Http\Controllers;

use App\Models\PressReview;
use App\Support\UploadConstraints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
class PressReviewController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesSafeUploads;

    // ==================== PARTIE PUBLIQUE ====================

    /**
     * Afficher la liste des revues de presse publiées
     */
    public function index(Request $request)
    {
        $query = PressReview::published();

        // Filtres
        if ($request->has('category') && $request->category) {
            $query->byCategory($request->category);
        }

        if ($request->has('featured') && $request->featured) {
            $query->featured();
        }

        if ($request->has('tag') && $request->tag) {
            $query->withTag($request->tag);
        }

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Tri
        $orderBy = $request->get('order_by', 'published_at');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Pagination
        $perPage = $request->get('per_page', 12);
        $pressReviews = $query->paginate($perPage);

        // Statistiques
        $stats = [
            'total' => PressReview::published()->count(),
            'interviews' => PressReview::published()->byCategory('interview')->count(),
            'critiques' => PressReview::published()->byCategory('critique')->count(),
            'reportages' => PressReview::published()->byCategory('reportage')->count(),
            'articles' => PressReview::published()->byCategory('article')->count(),
            'featured' => PressReview::published()->featured()->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'press_reviews' => $pressReviews->items(),
                'pagination' => [
                    'total' => $pressReviews->total(),
                    'per_page' => $pressReviews->perPage(),
                    'current_page' => $pressReviews->currentPage(),
                    'last_page' => $pressReviews->lastPage(),
                    'from' => $pressReviews->firstItem(),
                    'to' => $pressReviews->lastItem()
                ],
                'stats' => $stats,
                'categories' => [
                    ['value' => 'interview', 'label' => 'Interviews', 'count' => PressReview::published()->byCategory('interview')->count()],
                    ['value' => 'critique', 'label' => 'Critiques', 'count' => PressReview::published()->byCategory('critique')->count()],
                    ['value' => 'reportage', 'label' => 'Reportages', 'count' => PressReview::published()->byCategory('reportage')->count()],
                    ['value' => 'article', 'label' => 'Articles', 'count' => PressReview::published()->byCategory('article')->count()],
                    ['value' => 'autre', 'label' => 'Autres', 'count' => PressReview::published()->byCategory('autre')->count()],
                ]
            ]
        ], 200);
    }

    /**
     * Afficher une revue de presse spécifique
     */
    public function show($slug)
    {
        $pressReview = PressReview::published()
            ->where('slug', $slug)
            ->first();

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        // Incrémenter les vues
        $pressReview->incrementViews();

        // Suggestions (articles similaires)
        $suggestions = PressReview::published()
            ->where('id', '!=', $pressReview->id)
            ->where(function ($query) use ($pressReview) {
                $query->where('category', $pressReview->category)
                      ->orWhereJsonContains('tags', $pressReview->tags[0] ?? '');
            })
            ->orderBy('published_at', 'desc')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'summary', 'cover_image', 'published_at', 'category']);

        return response()->json([
            'success' => true,
            'data' => [
                'press_review' => $pressReview,
                'suggestions' => $suggestions
            ]
        ], 200);
    }

    /**
     * Récupérer les tags populaires
     */
    public function popularTags()
    {
        $pressReviews = PressReview::published()->get();
        
        $tags = [];
        foreach ($pressReviews as $review) {
            if ($review->tags) {
                foreach ($review->tags as $tag) {
                    if (!isset($tags[$tag])) {
                        $tags[$tag] = 0;
                    }
                    $tags[$tag]++;
                }
            }
        }

        arsort($tags);
        $popularTags = array_slice($tags, 0, 15, true);

        $result = [];
        foreach ($popularTags as $tag => $count) {
            $result[] = [
                'name' => $tag,
                'count' => $count,
                'slug' => Str::slug($tag)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ], 200);
    }

    // ==================== PARTIE ADMIN ====================

    /**
     * Récupérer toutes les revues de presse (admin)
     */
    public function adminIndex(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = PressReview::query();

        // Filtres
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('featured') && $request->featured) {
            $query->where('is_featured', true);
        }

        // Tri
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $perPage = $request->get('per_page', 20);
        $pressReviews = $query->paginate($perPage);

        // Statistiques
        $stats = [
            'total' => PressReview::count(),
            'draft' => PressReview::where('status', 'draft')->count(),
            'published' => PressReview::where('status', 'published')->count(),
            'archived' => PressReview::where('status', 'archived')->count(),
            'featured' => PressReview::where('is_featured', true)->count(),
            'interviews' => PressReview::where('category', 'interview')->count(),
            'critiques' => PressReview::where('category', 'critique')->count(),
            'reportages' => PressReview::where('category', 'reportage')->count(),
            'articles' => PressReview::where('category', 'article')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'press_reviews' => $pressReviews->items(),
                'pagination' => [
                    'total' => $pressReviews->total(),
                    'per_page' => $pressReviews->perPage(),
                    'current_page' => $pressReviews->currentPage(),
                    'last_page' => $pressReviews->lastPage()
                ],
                'stats' => $stats
            ]
        ], 200);
    }

    /**
     * Récupérer une revue de presse pour l'admin
     */
    public function adminShow($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pressReview
        ], 200);
    }

    /**
     * Créer une revue de presse
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'summary' => 'required|string',
            'content' => 'required|string',
            'author' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'source_url' => 'nullable|url|max:500',
            'publication_date' => 'nullable|date',
            'cover_image' => 'nullable|image|mimes:' . UploadConstraints::IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'gallery_images.*' => 'nullable|image|mimes:' . UploadConstraints::IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'documents.*' => 'nullable|file|mimes:' . UploadConstraints::DOCUMENT_MIMES . '|max:' . UploadConstraints::MAX_DOCUMENT_KB,
            'video_url' => 'nullable|url|max:500',
            'audio_url' => 'nullable|url|max:500',
            'category' => 'required|in:interview,critique,reportage,article,autre',
            'tags' => 'nullable|array',
            // 'is_featured' => 'boolean',
            'status' => 'required|in:draft,published,archived',
            'published_at' => 'nullable|date',
            // 'meta_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'title', 'slug', 'subtitle', 'summary', 'content', 'author',
                'source', 'source_url', 'publication_date', 'video_url', 'audio_url',
                'category', 'tags', 'is_featured', 'status', 'published_at'
            ]);
			$data['is_featured']=!empty($data['is_featured'])?1:0;
            if (isset($data['content'])) {
                $data['content'] = $this->sanitizeHtml($data['content']);
            }
            // Gestion de l'image de couverture
            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $file->move(public_path('press-reviews/covers'), $filename);
            $path = 'press-reviews/covers/' . $filename;
            $data['cover_image'] = $path;

            }

            // Gestion de la galerie d'images
            if ($request->hasFile('gallery_images')) {
                $galleryPaths = [];
                foreach ($request->file('gallery_images') as $image) {
                      $file = $image;
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $file->move(public_path('press-reviews/gallery/'), $filename);
            $path = 'press-reviews/gallery/' . $filename;
            $galleryPaths[] = $path;


                }
                $data['gallery_images'] = $galleryPaths;
            }

            // Gestion des documents
            if ($request->hasFile('documents')) {
             $documentPaths = [];

foreach ($request->file('documents') as $doc) {

    $filename = Str::random(20) . '.' . $this->safeExt($doc, UploadConstraints::DOCUMENT_MIMES);

    // 🔥 récupérer les infos AVANT le move
    $originalName = $doc->getClientOriginalName();
    $size = $doc->getSize();
    $type = $doc->getMimeType();

    $doc->move(public_path('press-reviews/documents/'), $filename);

    $path = 'press-reviews/documents/' . $filename;

    $documentPaths[] = [
        'path' => $path,
        'name' => $originalName,
        'size' => $size,
        'type' => $type
    ];
}

                $data['documents'] = $documentPaths;
            }

            // Date de publication
            if ($request->status === 'published' && !$request->published_at) {
                $data['published_at'] = now();
            }

            $pressReview = PressReview::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Revue de presse créée avec succès',
                'data' => $pressReview
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création d\'une revue de presse', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * Mettre à jour une revue de presse
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'summary' => 'sometimes|required|string',
            'content' => 'sometimes|required|string',
            'author' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'source_url' => 'nullable|url|max:500',
            'publication_date' => 'nullable|date',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'gallery_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'documents.*' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'video_url' => 'nullable|url|max:500',
            'audio_url' => 'nullable|url|max:500',
            'category' => 'sometimes|required|in:interview,critique,reportage,article,autre',
            'tags' => 'nullable|array',
            
            'status' => 'sometimes|required|in:draft,published,archived',
            'published_at' => 'nullable|date',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'title', 'slug', 'subtitle', 'summary', 'content', 'author',
                'source', 'source_url', 'publication_date', 'video_url', 'audio_url',
                'category', 'tags', 'is_featured', 'status', 'published_at'
            ]);
            if (isset($data['content'])) {
                $data['content'] = $this->sanitizeHtml($data['content']);
            }

            // Gestion de l'image de couverture
            if ($request->hasFile('cover_image')) {
                // Supprimer l'ancienne image
                if ($pressReview->cover_image) {
                    $imagePath = public_path($pressReview->cover_image);
                    if (File::exists($imagePath)) {
                      File::delete($imagePath); // Ou unlink($imagePath);
                    }   
                 
                }
                    $file = $request->file('cover_image');
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $file->move(public_path('press-reviews/covers'), $filename);
            $path = 'press-reviews/covers/' . $filename;
            $data['cover_image'] = $path;

            }

            // Gestion de la galerie d'images (ajout, sans effacer les images existantes)
            if ($request->hasFile('gallery_images')) {
                $galleryPaths = is_array($pressReview->gallery_images) ? $pressReview->gallery_images : [];
                foreach ($request->file('gallery_images') as $image) {

                    $file = $image;
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $file->move(public_path('press-reviews/gallery'), $filename);
            $path = 'press-reviews/gallery/' . $filename;
                    $galleryPaths[] = $path;
                }
                $data['gallery_images'] = $galleryPaths;
            }

            // Gestion des documents (ajout, sans effacer les documents existants)
            if ($request->hasFile('documents')) {
               $documentPaths = is_array($pressReview->documents) ? $pressReview->documents : [];

foreach ($request->file('documents') as $doc) {

    $filename = Str::random(20) . '.' . $this->safeExt($doc, UploadConstraints::DOCUMENT_MIMES);

    // 🔥 récupérer les infos AVANT le move
    $originalName = $doc->getClientOriginalName();
    $size = $doc->getSize();
    $type = $doc->getMimeType();

    $doc->move(public_path('press-reviews/documents/'), $filename);

    $path = 'press-reviews/documents/' . $filename;

    $documentPaths[] = [
        'path' => $path,
        'name' => $originalName,
        'size' => $size,
        'type' => $type
    ];
}

                $data['documents'] = $documentPaths;
            }

            // Mise à jour de la date de publication
            if (isset($data['status']) && $data['status'] === 'published' && !$pressReview->published_at) {
                $data['published_at'] = now();
            }

            $pressReview->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Revue de presse mise à jour avec succès',
                'data' => $pressReview->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour d\'une revue de presse', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une revue de presse
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        try {
            // Supprimer les fichiers associés
            if ($pressReview->cover_image) {
              
                  $imagePath = public_path($pressReview->cover_image);
                    if (File::exists($imagePath)) {
                      File::delete($imagePath); // Ou unlink($imagePath);
                    }   
            }

            if ($pressReview->gallery_images) {
                foreach ($pressReview->gallery_images as $image) {
                      $imagePath = public_path($image);
                    if (File::exists($imagePath)) {
                      File::delete($imagePath); // Ou unlink($imagePath);
                    }   
                    // Storage::disk('public')->delete($image);
                }
            }

            if ($pressReview->documents) {
                foreach ($pressReview->documents as $doc) {
                        $docPath = public_path($doc['path'] ?? $doc);
                    if (File::exists($docPath)) {
                      File::delete($docPath); // Ou unlink($imagePath);
                    }  
                    // Storage::disk('public')->delete($doc['path'] ?? $doc);
                }
            }

            $pressReview->delete();

            return response()->json([
                'success' => true,
                'message' => 'Revue de presse supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression d\'une revue de presse', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut (raccourci)
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,published,archived'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        $oldStatus = $pressReview->status;
        $pressReview->status = $request->status;

        if ($request->status === 'published' && !$pressReview->published_at) {
            $pressReview->published_at = now();
        }

        $pressReview->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $pressReview->id,
                'status' => $pressReview->status,
                'old_status' => $oldStatus
            ]
        ], 200);
    }

    /**
     * Basculer le statut "à la une"
     */
    public function toggleFeatured($id)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        $pressReview->is_featured = !$pressReview->is_featured;
        $pressReview->save();

        return response()->json([
            'success' => true,
            'message' => $pressReview->is_featured ? 'Article mis à la une' : 'Article retiré de la une',
            'data' => [
                'id' => $pressReview->id,
                'is_featured' => $pressReview->is_featured
            ]
        ], 200);
    }

    /**
     * Upload d'image via l'éditeur de texte
     */
    public function uploadImage(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $path = $file->storeAs('press-reviews/editor', $filename, 'public');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'upload d\'image éditeur', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de l\'image'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path)
        ], 200);
    }

    /**
     * Sanitise un fragment HTML (anti XSS stocké) : tags autorisés,
     * suppression des handlers d'événements et des URL javascript:.
     */
    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><h2><h3><h4><strong><em><u><ul><ol><li><a><blockquote><img><span><div><figure><figcaption><table><thead><tbody><tr><td><th>';
        $clean = strip_tags($html, $allowed);
        // Retire les handlers on*="..." / on*=...
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        // Retire les URL javascript: dans href/src
        $clean = preg_replace('/\s(href|src)\s*=\s*("|\'|\s)*\s*javascript\s*:[^"\'>\s]*/i', ' $1="#"', $clean);

        return $clean;
    }
}
