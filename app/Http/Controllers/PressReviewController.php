<?php
// app/Http/Controllers/PressReviewController.php

namespace App\Http\Controllers;

use App\Models\PressReview;
use App\Services\GalleryService;
use App\Services\GalleryStorageException;
use App\Services\PressReviewDocumentService;
use App\Support\UploadConstraints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
class PressReviewController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesSafeUploads;
    use \App\Http\Controllers\Concerns\EnsuresAdminRole;

    private const COVERS_DIR = 'press-reviews/covers';
    private const EDITOR_DIR = 'press-reviews/editor';
    private const DOCUMENTS_DIR = PressReviewDocumentService::DOCUMENTS_DIR;
    private const MAX_PER_PAGE = 50;
    private const SORTABLE_COLUMNS = ['published_at', 'created_at', 'updated_at', 'title', 'views', 'category', 'status'];
    private const MAX_TAGS = 20;
    private const MAX_TAG_LENGTH = 50;

    public function __construct(
        private readonly GalleryService $gallery,
        private readonly PressReviewDocumentService $documents
    )
    {
    }

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
        $query->orderBy(
            $this->sortColumn($request->query('order_by'), 'published_at'),
            $this->sortDirection($request->query('order_dir'))
        );

        // Pagination
        $perPage = $this->perPage($request->query('per_page'), 12);
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
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
        $query->orderBy(
            $this->sortColumn($request->query('order_by'), 'created_at'),
            $this->sortDirection($request->query('order_dir'))
        );

        $perPage = $this->perPage($request->query('per_page'), 20);
        $pressReviews = $query->paginate($perPage);

        // Statistiques (3 requêtes au lieu de 9 COUNT séparés)
        $byStatus = PressReview::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $byCategory = PressReview::query()->selectRaw('category, count(*) as c')->groupBy('category')->pluck('c', 'category');
        $stats = [
            'total' => (int) PressReview::count(),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'published' => (int) ($byStatus['published'] ?? 0),
            'archived' => (int) ($byStatus['archived'] ?? 0),
            'featured' => (int) PressReview::where('is_featured', true)->count(),
            'interviews' => (int) ($byCategory['interview'] ?? 0),
            'critiques' => (int) ($byCategory['critique'] ?? 0),
            'reportages' => (int) ($byCategory['reportage'] ?? 0),
            'articles' => (int) ($byCategory['article'] ?? 0)
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $this->normalizeMultiUploads($request);
        $this->beginUploads();

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
            'tags' => 'nullable|array|max:' . self::MAX_TAGS,
            'tags.*' => 'nullable|string|max:' . self::MAX_TAG_LENGTH,
            'tags_present' => 'nullable|boolean',
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
            $this->applyTags($request, $data);
			$data['is_featured']=!empty($data['is_featured'])?1:0;
            if (isset($data['content'])) {
                $data['content'] = $this->sanitizeHtml($data['content']);
            }
            // Gestion de l'image de couverture
            if ($request->hasFile('cover_image')) {
                $data['cover_image'] = $this->storeCover($request->file('cover_image'));
            }

            // Gestion de la galerie d'images (dédoublonnée par contenu)
            if ($request->hasFile('gallery_images')) {
                $data['gallery_images'] = $this->trackNewGallery([], $this->gallery->merge([], $request->file('gallery_images')));
            }

            // Gestion des documents
            if ($request->hasFile('documents')) {
                $data['documents'] = $this->storeDocuments([], $request->file('documents'));
            }

            // Date de publication
            if ($request->status === 'published' && !$request->published_at) {
                $data['published_at'] = now();
            }

            $pressReview = PressReview::create($data);
            $this->generateNewImageVariants();

            return response()->json([
                'success' => true,
                'message' => 'Revue de presse créée avec succès',
                'data' => $pressReview
            ], 201);

        } catch (GalleryStorageException $e) {
            $this->discardNewUploads();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->discardNewUploads();
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        $this->normalizeMultiUploads($request);
        $this->beginUploads();

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'summary' => 'sometimes|required|string',
            'content' => 'sometimes|required|string',
            'author' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'source_url' => 'nullable|url|max:500',
            'publication_date' => 'nullable|date',
            'cover_image' => 'nullable|image|mimes:' . UploadConstraints::IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'gallery_images.*' => 'nullable|image|mimes:' . UploadConstraints::IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB,
            'documents.*' => 'nullable|file|mimes:' . UploadConstraints::DOCUMENT_MIMES . '|max:' . UploadConstraints::MAX_DOCUMENT_KB,
            'video_url' => 'nullable|url|max:500',
            'audio_url' => 'nullable|url|max:500',
            'category' => 'sometimes|required|in:interview,critique,reportage,article,autre',
            'tags' => 'nullable|array|max:' . self::MAX_TAGS,
            'tags.*' => 'nullable|string|max:' . self::MAX_TAG_LENGTH,
            'tags_present' => 'nullable|boolean',
            
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
            $this->applyTags($request, $data);
            if (isset($data['content'])) {
                $data['content'] = $this->sanitizeHtml($data['content']);
            }

            // Gestion de l'image de couverture (l'ancienne n'est supprimée qu'APRÈS la persistance)
            $oldCover = null;
            if ($request->hasFile('cover_image')) {
                $oldCover = $pressReview->cover_image;
                $data['cover_image'] = $this->storeCover($request->file('cover_image'));
            }

            // Galerie : retrait des images non conservées (existing_gallery_images, si fourni)
            // puis ajout idempotent des nouvelles (dédoublonnage par sha256 du contenu).
            $uploads = $request->file('gallery_images');
            $uploads = is_array($uploads) ? $uploads : [];
            $hasKeepList = $request->has('existing_gallery_images');
            $removedGallery = [];
            if ($hasKeepList || $uploads !== []) {
                $current = is_array($pressReview->gallery_images) ? $pressReview->gallery_images : [];
                if ($hasKeepList) {
                    $current = $this->gallery->prune($current, $request->input('existing_gallery_images'), $uploads, $removedGallery);
                }
                $data['gallery_images'] = $this->trackNewGallery($current, $this->gallery->merge($current, $uploads));
            }

            // Documents : retrait des non conservés (existing_documents, si fourni) puis ajout idempotent.
            $docUploads = $request->file('documents');
            $docUploads = is_array($docUploads) ? $docUploads : [];
            $removedDocs = [];
            if ($request->has('existing_documents') || $docUploads !== []) {
                $docs = is_array($pressReview->documents) ? $pressReview->documents : [];
                if ($request->has('existing_documents')) {
                    $docs = $this->documents->prune($docs, $request->input('existing_documents'), $docUploads, $removedDocs);
                }
                $data['documents'] = $this->storeDocuments($docs, $this->documents->newUploads($docs, $docUploads));
            }

            // Mise à jour de la date de publication
            if (isset($data['status']) && $data['status'] === 'published' && !$pressReview->published_at) {
                $data['published_at'] = now();
            }

            $pressReview->update($data);
            $this->generateNewImageVariants();
            // Persisté : les nouveaux fichiers sont désormais référencés, plus rien à nettoyer si la suite échoue.
            $this->beginUploads();

            // Suppression disque APRÈS la persistance : un échec en base ne laisse pas de références mortes.
            $this->gallery->deleteFiles($removedGallery);
            $this->documents->deleteFiles($removedDocs);
            $this->deleteStoredFile($oldCover, self::COVERS_DIR);

            return response()->json([
                'success' => true,
                'message' => 'Revue de presse mise à jour avec succès',
                'data' => $pressReview->fresh()
            ], 200);

        } catch (GalleryStorageException $e) {
            $this->discardNewUploads();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            $this->discardNewUploads();
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $pressReview = PressReview::find($id);

        if (!$pressReview) {
            return response()->json([
                'success' => false,
                'message' => 'Revue de presse non trouvée'
            ], 404);
        }

        try {
            // Fichiers collectés AVANT la suppression, effacés APRÈS : un échec en base ne laisse pas de références mortes.
            $gallery = is_array($pressReview->gallery_images) ? $pressReview->gallery_images : [];
            $documents = is_array($pressReview->documents) ? $pressReview->documents : [];
            $cover = $pressReview->cover_image;

            $pressReview->delete();

            $this->deleteStoredFile($cover, self::COVERS_DIR);
            $this->gallery->deleteFiles($gallery);
            $this->documents->deleteFiles($documents);

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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
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
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
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
     * Retire UNE image de la galerie (basename uniquement, doit appartenir à CETTE revue).
     * Le fichier n'est supprimé qu'après la mise à jour en base.
     */
    public function deleteGalleryImage($id, string $filename)
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $pressReview = PressReview::find($id);
        $current = $pressReview && is_array($pressReview->gallery_images) ? $pressReview->gallery_images : [];
        $result = $pressReview ? $this->gallery->without($current, $filename) : null;

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Image de galerie non trouvée'], 404);
        }

        [$remaining, $removedPath] = $result;

        try {
            $pressReview->update(['gallery_images' => $remaining]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du retrait d\'une image de galerie', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression de l\'image'], 500);
        }

        $this->gallery->deleteFiles([$removedPath]);

        return response()->json([
            'success' => true,
            'message' => 'Image supprimée de la galerie',
            'data' => $pressReview->fresh(),
        ], 200);
    }

    /** Retire un document d'une revue (appartenance vérifiée) ; fichier supprimé après la mise à jour en base. */
    public function deleteDocument($id, string $filename)
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $pressReview = PressReview::find($id);
        $current = $pressReview && is_array($pressReview->documents) ? $pressReview->documents : [];
        $result = $pressReview ? $this->documents->without($current, $filename) : null;

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Document non trouvé'], 404);
        }

        [$remaining, $removed] = $result;

        try {
            $pressReview->update(['documents' => $remaining]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du retrait d\'un document', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression du document'], 500);
        }

        $this->documents->deleteFiles([$removed]);

        return response()->json([
            'success' => true,
            'message' => 'Document supprimé',
            'data' => $pressReview->fresh(),
        ], 200);
    }

    /** Couverture : écrite sous covers/, suivie pour nettoyage si l'enregistrement échoue. */
    /**
     * Tags : `tags_present` truthy + `tags` absent/vide => liste vidée ; ni l'un ni l'autre => inchangés.
     * Valeurs nettoyées (trim, vides retirées, dédoublonnées).
     */
    private function applyTags(Request $request, array &$data): void
    {
        $hasTags = $request->has('tags');
        if (!$hasTags && !$request->boolean('tags_present')) {
            unset($data['tags']);

            return;
        }

        $raw = is_array($request->input('tags')) ? $request->input('tags') : [];
        $clean = array_filter(array_map(static fn ($t) => is_string($t) ? trim($t) : '', $raw), static fn (string $t) => $t !== '');
        $data['tags'] = array_values(array_unique($clean));
    }

    private function sortColumn(mixed $requested, string $default): string
    {
        return is_string($requested) && in_array($requested, self::SORTABLE_COLUMNS, true) ? $requested : $default;
    }

    private function sortDirection(mixed $requested): string
    {
        return is_string($requested) && strtolower($requested) === 'asc' ? 'asc' : 'desc';
    }

    private function perPage(mixed $requested, int $default): int
    {
        $value = is_numeric($requested) ? (int) $requested : $default;

        return min(max($value, 1), self::MAX_PER_PAGE);
    }

    private function storeCover(\Illuminate\Http\UploadedFile $file): string
    {
        $stored = $this->storeUpload($file, self::COVERS_DIR, UploadConstraints::IMAGE_MIMES);

        return self::COVERS_DIR . '/' . $stored['filename'];
    }

    /**
     * Ajoute les documents à la liste existante ; chaque fichier écrit est suivi pour nettoyage.
     * Métadonnées lues AVANT le déplacement (le fichier temporaire disparaît après move).
     *
     * @param array<int, mixed> $existing
     * @param \Illuminate\Http\UploadedFile[] $files
     * @return array<int, mixed>
     */
    private function storeDocuments(array $existing, array $files): array
    {
        foreach ($files as $doc) {
            $originalName = $doc->getClientOriginalName();
            $size = $doc->getSize();
            $type = $doc->getMimeType();
            $stored = $this->storeUpload($doc, self::DOCUMENTS_DIR, UploadConstraints::DOCUMENT_MIMES);

            $existing[] = [
                'path' => self::DOCUMENTS_DIR . '/' . $stored['filename'],
                'name' => $originalName,
                'size' => $size,
                'type' => $type,
            ];
        }

        return $existing;
    }

    /**
     * Enregistre pour nettoyage les images de galerie ajoutées par merge() (absentes de $before).
     *
     * @param string[] $before
     * @param string[] $after
     * @return string[]
     */
    private function trackNewGallery(array $before, array $after): array
    {
        foreach (array_diff($after, $before) as $path) {
            $this->newUploads[] = [basename((string) $path), GalleryService::GALLERY_DIR, false];
        }

        return $after;
    }

    /**
     * Un fichier unique envoyé sous gallery_images / documents (non tableau) est ignoré par
     * les règles `.*` (donc NON validé) : on le normalise en tableau avant validation.
     */
    private function normalizeMultiUploads(Request $request): void
    {
        foreach (['gallery_images', 'documents'] as $key) {
            $value = $request->files->get($key);
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $request->files->set($key, [$value]);
                // Request::allFiles() met les fichiers convertis en cache : on l'invalide.
                (function (): void {
                    $this->convertedFiles = null;
                })->call($request);
            }
        }
    }

    /**
     * Upload d'image via l'éditeur de texte
     */
    public function uploadImage(Request $request)
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:' . UploadConstraints::IMAGE_MIMES . '|max:' . UploadConstraints::MAX_IMAGE_KB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $this->safeExt($file, UploadConstraints::IMAGE_MIMES);
            $file->move(public_path(self::EDITOR_DIR), $filename);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'upload d\'image éditeur', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de l\'image'
            ], 500);
        }

        // Après écriture réussie ; ne lève jamais et n'affecte pas la réponse.
        app(\App\Services\ImageVariantService::class)->generateForStored($filename, self::EDITOR_DIR);

        return response()->json([
            'success' => true,
            'url' => asset(self::EDITOR_DIR . '/' . $filename)
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
