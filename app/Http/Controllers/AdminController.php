<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Domaine;
use App\Models\User;
use App\Models\Service;
use App\Models\Categorie;
use App\Models\Service_request;
use App\Models\Order;
use App\Models\Book;
use App\Models\Video;
use App\Models\Memoir;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Log;
class AdminController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesSafeUploads;
    use \App\Http\Controllers\Concerns\EnsuresAdminRole;

    private const VIDEO_DIR = 'video';
    private const SERVICE_DIR = 'service';
    private const ARTICLE_DIR = 'articles';
    private const BOOK_COVER_DIR = 'books/covers';
    private const BOOK_DOCUMENT_DIR = 'books/documents';
    private const BOOK_EXTRACT_DIR = 'books/extrait_documents';
    private const MEMOIR_COVER_DIR = 'memoirs/covers';
    private const MEMOIR_DOCUMENT_DIR = 'memoirs/documents';

    /**
     * Retourne uniquement les clés RÉELLEMENT présentes dans la requête
     * (les mises à jour partielles ne doivent pas écraser les autres colonnes
     * avec null — comportement piégeux de Request::only()).
     */
    private function onlyPresent(Request $request, array $keys): array
    {
        return array_filter(
            $request->only($keys),
            fn ($key) => $request->exists($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function validationError(\Illuminate\Validation\Validator $validator): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 404);
    }

    /**
     * Téléchargement admin du document d'un mémoire (tous statuts, brouillons inclus).
     * N'incrémente pas downloads_count (réservé aux téléchargements publics).
     */
    public function downloadMemoir($id)
    {
        $memoir = Memoir::find($id);
        if (!$memoir || !$memoir->document) {
            return response()->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
        }

        $filePath = $this->privateOrLegacyPath(
            'memoirs/documents/' . basename($memoir->document),
            'memoirs/documents'
        );
        if (!$filePath) {
            return response()->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
        }

        try {
            return response()->download(
                $filePath,
                $this->safeDownloadName(
                    (string) $memoir->title,
                    $this->downloadExtension($filePath, $memoir->extension, $memoir->document)
                )
            );
        } catch (\Throwable $e) {
            Log::error('Erreur lors du téléchargement admin du mémoire', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors du téléchargement du fichier'], 500);
        }
    }

    public function saveVideo(Request $request)
    {
        $isUpdate = (bool) $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'title' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'description' => ($isUpdate ? 'nullable' : 'required') . '|string|max:5000',
            'video_url' => ($isUpdate ? 'nullable' : 'required') . '|url|max:500',
            'thumbnail' => ($isUpdate ? 'nullable' : 'required') . '|file|image|mimes:' . \App\Support\UploadConstraints::IMAGE_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_IMAGE_KB,
            'status' => 'nullable|integer|in:0,1,2',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $this->beginUploads();
        try {
            // id fourni : la ligne DOIT exister (404 sinon), avant tout upload.
            $video = $isUpdate ? Video::findOrFail($request->id) : null;
            $data = $this->onlyPresent($request, ['title', 'description', 'video_url', 'status']);

            $oldThumbnail = null;
            if ($request->hasFile('thumbnail')) {
                $oldThumbnail = $video?->thumbnail;
                $data['thumbnail'] = $this->storeUpload($request->file('thumbnail'), self::VIDEO_DIR, \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($video) {
                $video->update($data);
            } else {
                $video = Video::create($data);
            }
            $this->deleteStoredFile($oldThumbnail, self::VIDEO_DIR);

            return response()->json(['message' => 'Vidéo enregistrée', 'video' => $video], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Vidéo non trouvée');
        } catch (\Exception $e) {
            $this->discardNewUploads();
            Log::error('Erreur lors de l\'enregistrement de la vidéo', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la vidéo'], 500);
        }
    }


    public function index()
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $categories = Categorie::all();
        $domaine = Domaine::all();

        $userNames = User::pluck('name', 'id');
        $serviceTitles = Service::pluck('title', 'id');
        $bookTitles = Book::pluck('title', 'id');
        $bookAuthors = Book::pluck('author', 'id');

        $service_requests = Service_request::all()->map(function ($res) use ($serviceTitles, $userNames) {
            $res->service = $serviceTitles[$res->service_id] ?? null;
            $res->user = $userNames[$res->user_id] ?? null;
            return $res;
        });

        $order = Order::all()->map(function ($ord) use ($userNames, $bookTitles, $bookAuthors) {
            $ord->user = $userNames[$ord->user_id] ?? null;
            if (isset($bookTitles[$ord->book_id])) {
                $ord->book = $bookTitles[$ord->book_id];
                $ord->bookauthor = $bookAuthors[$ord->book_id] ?? null;
            }
            return $ord;
        });

        $users = User::where('role', 1)->get();
        $videos = Video::all();

        return response()->json([
            'categories' => $categories,
            'domaine' => $domaine,
            'service_requests' => $service_requests,
            'orders' => $order,
            'users' => $users,
            'videos' => $videos
        ], 200);
    }

    public function getbook()
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $paginator = Book::orderBy('id', 'desc')->cursorPaginate(10000);

        return response()->json([
            'books' => $paginator->items(),
            'nexturl' => optional($paginator->nextCursor())->encode(),
            'hasmore' => $paginator->hasMorePages()
        ], 200);
    }

    public function getarticle()
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $paginator = Article::orderBy('id', 'asc')->cursorPaginate(10000);

        return response()->json([
            'articles' => $paginator->items(),
            'nexturl' => optional($paginator->nextCursor())->encode(),
            'hasmore' => $paginator->hasMorePages()
        ], 200);
    }

    public function getmemoir()
    {
        if ($denied = $this->denyUnlessAdmin()) {
            return $denied;
        }

        $paginator = Memoir::orderBy('id', 'desc')->cursorPaginate(10000);

        return response()->json([
            'memoirs' => $paginator->items(),
            'next_cursor' => optional($paginator->nextCursor())->encode(),
            'has_more' => $paginator->hasMorePages()
        ], 200);
    }

    public function saveArticle(Request $request)
    {
        $isUpdate = (bool) $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'title' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'excerpt' => ($isUpdate ? 'nullable' : 'required') . '|string|max:1000',
            'content' => ($isUpdate ? 'nullable' : 'required') . '|string',
            'category' => 'nullable|integer|exists:categories,id',
            'image' => 'nullable|file|image|mimes:' . \App\Support\UploadConstraints::IMAGE_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_IMAGE_KB,
            'published_date' => 'nullable|date',
            'author' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'views_count' => 'nullable|integer|min:0',
            'reading_time' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1,2',
            'featured' => 'nullable|boolean',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $this->beginUploads();
        try {
            $article = $isUpdate ? Article::findOrFail($request->id) : null;
            $data = $this->onlyPresent($request, [
                'title','excerpt','content','category','published_date','author',
                'meta_title','meta_description','tags','views_count',
                'reading_time','status','featured'
            ]);

            $oldImage = null;
            if ($request->hasFile('image')) {
                $oldImage = $article?->image;
                $data['image'] = $this->storeUpload($request->file('image'), self::ARTICLE_DIR, \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($article) {
                if (empty($article->slug)) {
                    $data['slug'] = $this->uniqueSlug('articles', $data['title'] ?? $article->title);
                }

                $article->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('articles', $data['title']);
                $article = Article::create($data);
            }
            $this->deleteStoredFile($oldImage, self::ARTICLE_DIR);

            return response()->json(['message' => 'Article enregistré avec succès','article' => $article], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Article non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement de l\'article', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'article'], 500);
        } catch (\Exception $e) {
            $this->discardNewUploads();
            Log::error('Erreur lors de l\'enregistrement de l\'article', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'article'], 500);
        }
    }

    public function deleteArticle($id)
    {
        // Article soft-deleted (restaurable) : l'image reste référencée, on ne la supprime pas.
        Article::findOrFail($id)->delete();
        return response()->json(['message' => 'Article deleted successfully']);
    }

    public function saveBook(Request $request)
    {
        $isUpdate = (bool) $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'title' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'author' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'category' => ($isUpdate ? 'nullable' : 'required') . '|integer|exists:categories,id',
            'description' => ($isUpdate ? 'nullable' : 'required') . '|string|max:10000',
            'price' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'isbn' => array_filter([
                $isUpdate ? 'nullable' : 'required',
                'string',
                'max:255',
                Rule::unique('books', 'isbn')->ignore($request->id),
            ]),
            'amazon_url' => 'nullable|url|max:500',
            'pages' => ($isUpdate ? 'nullable' : 'required') . '|integer|min:1',
            'document' => ($isUpdate ? 'nullable' : 'required') . '|file|mimes:' . \App\Support\UploadConstraints::DOCUMENT_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_DOCUMENT_KB,
            'extraitdocument' => ($isUpdate ? 'nullable' : 'required') . '|file|mimes:' . \App\Support\UploadConstraints::DOCUMENT_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_DOCUMENT_KB,
            'cover' => 'nullable|file|image|mimes:' . \App\Support\UploadConstraints::IMAGE_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_IMAGE_KB,
            'stock_quantity' => 'nullable|integer|min:0',
            'language' => 'nullable|string|max:10',
            'publisher' => 'nullable|string|max:255',
            'edition' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'published_date' => 'nullable|date',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $this->beginUploads();
        try {
            $book = $isUpdate ? Book::findOrFail($request->id) : null;
            $data = $this->onlyPresent($request, [
                'title','author','description','category','price','isbn',
                'amazon_url','pages','stock_quantity','language','publisher','edition',
                'status','published_date'
            ]);

            $replaced = [];
            if ($request->hasFile('cover')) {
                $replaced[] = [$book?->cover, self::BOOK_COVER_DIR, false];
                $data['cover'] = $this->storeUpload($request->file('cover'), self::BOOK_COVER_DIR, \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($request->hasFile('document')) {
                $replaced[] = [$book?->document, self::BOOK_DOCUMENT_DIR, true];
                $upload = $this->storePrivateUpload($request->file('document'), self::BOOK_DOCUMENT_DIR, \App\Support\UploadConstraints::DOCUMENT_MIMES);
                $data['document'] = $upload['filename'];
                $data['extension'] = $upload['extension'];
            }

            if ($request->hasFile('extraitdocument')) {
                $replaced[] = [$book?->extraitdocument, self::BOOK_EXTRACT_DIR, true];
                $data['extraitdocument'] = $this->storePrivateUpload($request->file('extraitdocument'), self::BOOK_EXTRACT_DIR, \App\Support\UploadConstraints::DOCUMENT_MIMES)['filename'];
            }

            if ($book) {
                if (empty($book->slug)) {
                    $data['slug'] = $this->uniqueSlug('books', $data['title'] ?? $book->title);
                }

                $book->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('books', $data['title']);
                $book = Book::create($data);
            }
            foreach ($replaced as [$old, $dir, $private]) {
                $this->deleteStoredFile($old, $dir, $private);
            }

            return response()->json(['message' => 'Livre enregistré avec succès','book' => $book], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Livre non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement du livre', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du livre'], 500);
        } catch (\Exception $e) {
            $this->discardNewUploads();
            Log::error('Erreur lors de l\'enregistrement du livre', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du livre'], 500);
        }
    }

    public function deleteMemoir($id)
    {
        $memoir = Memoir::findOrFail($id);
        $memoir->delete();
        $this->deleteStoredFile($memoir->cover, self::MEMOIR_COVER_DIR);
        $this->deleteStoredFile($memoir->document, self::MEMOIR_DOCUMENT_DIR, true);

        return response()->json(['message' => 'Memoir deleted']);
    }

    public function saveMemoir(Request $request)
    {
        $isUpdate = (bool) $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'title' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'author' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'university' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'domain' => ($isUpdate ? 'nullable' : 'required') . '|integer|exists:domaines,id',
            'year' => ($isUpdate ? 'nullable' : 'required') . '|integer|between:1900,2100',
            'abstract' => ($isUpdate ? 'nullable' : 'required') . '|string|max:10000',
            'keywords' => 'nullable|array',
            'supervisor' => 'nullable|string|max:255',
            'defense_date' => 'nullable|date',
            'grade' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:0,1,2',
            'cover' => 'nullable|file|image|mimes:' . \App\Support\UploadConstraints::IMAGE_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_IMAGE_KB,
            'document' => 'nullable|file|mimes:' . \App\Support\UploadConstraints::DOCUMENT_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_DOCUMENT_KB,
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $this->beginUploads();
        try {
            $memoir = $isUpdate ? Memoir::findOrFail($request->id) : null;
            $data = $this->onlyPresent($request, [
                'title','author','university','domain','year','abstract',
                'supervisor','defense_date','grade','status'
            ]);

            // Tableau PHP : le cast du modèle encode (pas de json_encode ici = pas de double encodage).
            // Mise à jour partielle sans `keywords` : valeur conservée.
            if ($request->exists('keywords')) {
                $data['keywords'] = $request->input('keywords');
            }

            $replaced = [];
            if ($request->hasFile('cover')) {
                $replaced[] = [$memoir?->cover, self::MEMOIR_COVER_DIR, false];
                $data['cover'] = $this->storeUpload($request->file('cover'), self::MEMOIR_COVER_DIR, \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($request->hasFile('document')) {
                $replaced[] = [$memoir?->document, self::MEMOIR_DOCUMENT_DIR, true];
                $upload = $this->storePrivateUpload($request->file('document'), self::MEMOIR_DOCUMENT_DIR, \App\Support\UploadConstraints::DOCUMENT_MIMES);
                $data['document'] = $upload['filename'];
                $data['extension'] = $upload['extension'];
            }

            if ($memoir) {
                if (empty($memoir->slug)) {
                    $data['slug'] = $this->uniqueSlug('memoirs', $data['title'] ?? $memoir->title);
                }

                $memoir->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('memoirs', $data['title']);
                $memoir = Memoir::create($data);
            }
            foreach ($replaced as [$old, $dir, $private]) {
                $this->deleteStoredFile($old, $dir, $private);
            }

            return response()->json(['message' => 'Mémoire enregistré','memoir' => $memoir], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Mémoire non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement du mémoire', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du mémoire'], 500);
        } catch (\Exception $e) {
            $this->discardNewUploads();
            Log::error('Erreur lors de l\'enregistrement du mémoire', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du mémoire'], 500);
        }
    }

    public function saveService(Request $request)
    {
        $isUpdate = (bool) $request->id;
        $this->normalizeBoolInput($request, 'is_active');

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'title' => ($isUpdate ? 'nullable' : 'required') . '|string|max:255',
            'description' => ($isUpdate ? 'nullable' : 'required') . '|string|max:5000',
            'price_range' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|file|image|mimes:' . \App\Support\UploadConstraints::IMAGE_MIMES . '|max:' . \App\Support\UploadConstraints::MAX_IMAGE_KB,
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $this->beginUploads();
        try {
            $service = $isUpdate ? Service::findOrFail($request->id) : null;
            $data = $this->onlyPresent($request, ['title','description','is_active','price_range']);

            $oldImage = null;
            if ($request->hasFile('image')) {
                $oldImage = $service?->image;
                $data['image'] = $this->storeUpload($request->file('image'), self::SERVICE_DIR, \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($service) {
                $service->update($data);
            } else {
                $service = Service::create($data);
            }
            $this->deleteStoredFile($oldImage, self::SERVICE_DIR);

            return response()->json(['message' => 'Service enregistré','service' => $service], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Service non trouvé');
        } catch (\Exception $e) {
            $this->discardNewUploads();
            Log::error('Erreur lors de l\'enregistrement du service', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du service'], 500);
        }
    }

    public function deleteService($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();
        $this->deleteStoredFile($service->image, self::SERVICE_DIR);

        return response()->json(['message' => 'Service deleted']);
    }

  

    public function deleteVideo($id)
    {
        $video = Video::findOrFail($id);
        $video->delete();
        $this->deleteStoredFile($video->thumbnail, self::VIDEO_DIR);

        return response()->json(['message' => 'Video deleted']);
    }

    public function saveCategorie(Request $request)
    {
        $isUpdate = (bool) $request->id;
        $this->normalizeBoolInput($request, 'is_active');

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'name' => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:255',
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'is_active' => 'sometimes|boolean',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $data = $this->onlyPresent($request, ['name','type','description','is_active']);

            if ($isUpdate) {
                $categorie = Categorie::findOrFail($request->id);
                // Slug unique généré une seule fois (jamais modifié ensuite : URLs stables).
                if (empty($categorie->slug)) {
                    $data['slug'] = $this->uniqueSlug('categories', $data['name'] ?? (string) $categorie->name);
                }
                $categorie->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('categories', (string) $data['name']);
                $categorie = Categorie::create($data)->refresh();
            }

            return response()->json(['message' => 'Categorie saved','categorie' => $categorie]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Catégorie non trouvée');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la catégorie', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la catégorie'], 500);
        }
    }

    public function deleteCategorie($id)
    {
        try {
            $categorie = Categorie::findOrFail($id);

            if (Book::withTrashed()->where('category', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette catégorie : des livres y sont encore rattachés.'
                ], 409);
            }

            $categorie->delete();

            return response()->json(['message' => 'Categorie deleted']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la catégorie', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression de la catégorie'], 500);
        }
    }

    public function saveDomaine(Request $request)
    {
        $isUpdate = (bool) $request->id;
        $this->normalizeBoolInput($request, 'is_active');

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'name' => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:255',
            'description' => 'nullable|string|max:5000',
            'is_active' => 'sometimes|boolean',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $data = $this->onlyPresent($request, ['name','description','is_active']);

            if ($isUpdate) {
                $domaine = Domaine::findOrFail($request->id);
                $domaine->update($data);
            } else {
                $domaine = Domaine::create($data)->refresh();
            }

            return response()->json(['message' => 'Domaine saved','domaine' => $domaine]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Domaine non trouvé');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du domaine', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du domaine'], 500);
        }
    }

    public function deleteDomaine($id)
    {
        try {
            $domaine = Domaine::findOrFail($id);

            if (Memoir::where('domain', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce domaine : des mémoires y sont encore rattachés.'
                ], 409);
            }

            $domaine->delete();

            return response()->json(['message' => 'Domaine deleted']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Domaine non trouvé'], 404);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du domaine', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression du domaine'], 500);
        }
    }
     public function deleteBook($id)
    {
        // Livre soft-deleted (restaurable, catégorie le référence via withTrashed) : fichiers conservés.
        Book::findOrFail($id)->delete();

        return response()->json(['message' => 'Book deleted']);
    }
}
