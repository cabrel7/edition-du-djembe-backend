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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Log;
class AdminController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesSafeUploads;

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

        try {
            $data = $this->onlyPresent($request, ['title', 'description', 'video_url', 'status']);

            if ($request->hasFile('thumbnail')) {
                $data['thumbnail'] = $this->storeUpload($request->file('thumbnail'), 'video', \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            $video = Video::updateOrCreate(
                ['id' => $request->id],
                $data
            );

            return response()->json(['message' => 'Vidéo enregistrée', 'video' => $video], 200);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la vidéo', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Erreur lors de l\'enregistrement de la vidéo'], 500);
        }
    }


    public function index()
    {
        $user = auth()->user();

        if (!$user || $user->role != 1) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $categories = Categorie::all();
        $domaine = Domaine::all();

        $service_requests = Service_request::all()->map(function ($res) {
            $res->service = Service::find($res->service_id)?->title;
            $res->user = User::find($res->user_id)?->name;
            return $res;
        });

        $order = Order::all()->map(function ($ord) {
            $ord->user = User::find($ord->user_id)?->name;
            $book = Book::find($ord->book_id);
            if ($book) {
                $ord->book = $book->title;
                $ord->bookauthor = $book->author;
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
        $user = auth()->user();
        if (!$user || $user->role != 1) return response()->json(['message' => 'Unauthorized'], 401);

        $paginator = Book::orderBy('id', 'desc')->cursorPaginate(10000);

        return response()->json([
            'books' => $paginator->items(),
            'nexturl' => optional($paginator->nextCursor())->encode(),
            'hasmore' => $paginator->hasMorePages()
        ], 200);
    }

    public function getarticle()
    {
        $user = auth()->user();
        if (!$user || $user->role != 1) return response()->json(['message' => 'Unauthorized'], 401);

        $paginator = Article::orderBy('id', 'asc')->cursorPaginate(10000);

        return response()->json([
            'articles' => $paginator->items(),
            'nexturl' => optional($paginator->nextCursor())->encode(),
            'hasmore' => $paginator->hasMorePages()
        ], 200);
    }

    public function getmemoir()
    {
        $user = auth()->user();
        if (!$user || $user->role != 1) return response()->json(['message' => 'Unauthorized'], 401);

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

        try {
            $data = $this->onlyPresent($request, [
                'title','excerpt','content','published_date','author',
                'meta_title','meta_description','tags','views_count',
                'reading_time','status','featured'
            ]);

            if ($request->hasFile('image')) {
                $data['image'] = $this->storeUpload($request->file('image'), 'articles', \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($isUpdate) {
                $article = Article::findOrFail($request->id);

                if (empty($article->slug)) {
                    $data['slug'] = $this->uniqueSlug('articles', $data['title'] ?? $article->title);
                }

                $article->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('articles', $data['title']);
                $article = Article::create($data);
            }

            return response()->json(['message' => 'Article enregistré avec succès','article' => $article], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Article non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement de l\'article', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'article'], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de l\'article', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'article'], 500);
        }
    }

    public function deleteArticle($id)
    {
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

        try {
            $data = $this->onlyPresent($request, [
                'title','author','description','category','price','isbn',
                'amazon_url','pages','stock_quantity','language','publisher','edition',
                'status','published_date'
            ]);

            if ($request->hasFile('cover')) {
                $data['cover'] = $this->storeUpload($request->file('cover'), 'books/covers', \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($request->hasFile('document')) {
                $upload = $this->storeUpload($request->file('document'), 'books/documents', \App\Support\UploadConstraints::DOCUMENT_MIMES);
                $data['document'] = $upload['filename'];
                $data['extension'] = $upload['extension'];
            }

            if ($request->hasFile('extraitdocument')) {
                $data['extraitdocument'] = $this->storeUpload($request->file('extraitdocument'), 'books/extrait_documents', \App\Support\UploadConstraints::DOCUMENT_MIMES)['filename'];
            }

            if ($isUpdate) {
                $book = Book::findOrFail($request->id);

                if (empty($book->slug)) {
                    $data['slug'] = $this->uniqueSlug('books', $data['title'] ?? $book->title);
                }

                $book->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('books', $data['title']);
                $book = Book::create($data);
            }

            return response()->json(['message' => 'Livre enregistré avec succès','book' => $book], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Livre non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement du livre', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du livre'], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du livre', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du livre'], 500);
        }
    }

    public function deleteMemoir($id)
    {
        Memoir::findOrFail($id)->delete();
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

        try {
            $data = $this->onlyPresent($request, [
                'title','author','university','domain','year','abstract',
                'supervisor','defense_date','grade','status'
            ]);

            $data['keywords'] = $request->keywords !== null ? json_encode($request->keywords) : null;

            if ($request->hasFile('cover')) {
                $data['cover'] = $this->storeUpload($request->file('cover'), 'memoirs/covers', \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            if ($request->hasFile('document')) {
                $upload = $this->storeUpload($request->file('document'), 'memoirs/documents', \App\Support\UploadConstraints::DOCUMENT_MIMES);
                $data['document'] = $upload['filename'];
                $data['extension'] = $upload['extension'];
            }

            if ($isUpdate) {
                $memoir = Memoir::findOrFail($request->id);

                if (empty($memoir->slug)) {
                    $data['slug'] = $this->uniqueSlug('memoirs', $data['title'] ?? $memoir->title);
                }

                $memoir->update($data);
            } else {
                $data['slug'] = $this->uniqueSlug('memoirs', $data['title']);
                $memoir = Memoir::create($data);
            }

            return response()->json(['message' => 'Mémoire enregistré','memoir' => $memoir], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Mémoire non trouvé'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Erreur base de données lors de l\'enregistrement du mémoire', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du mémoire'], 500);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du mémoire', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du mémoire'], 500);
        }
    }

    public function saveService(Request $request)
    {
        $isUpdate = (bool) $request->id;

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

        try {
            $data = $this->onlyPresent($request, ['title','description','is_active','price_range']);

            if ($request->hasFile('image')) {
                $data['image'] = $this->storeUpload($request->file('image'), 'service', \App\Support\UploadConstraints::IMAGE_MIMES)['filename'];
            }

            $service = Service::updateOrCreate(
                ['id' => $request->id],
                $data
            );

            return response()->json(['message' => 'Service enregistré','service' => $service], 200);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du service', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du service'], 500);
        }
    }

    public function deleteService($id)
    {
        Service::findOrFail($id)->delete();
        return response()->json(['message' => 'Service deleted']);
    }

  

    public function deleteVideo($id)
    {
        Video::findOrFail($id)->delete();
        return response()->json(['message' => 'Video deleted']);
    }

    public function saveCategorie(Request $request)
    {
        $categorie = Categorie::updateOrCreate(
            ['id' => $request->id],
            $this->onlyPresent($request, ['name','type','description','is_active'])
        );

        return response()->json(['message' => 'Categorie saved','categorie' => $categorie]);
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
        $domaine = Domaine::updateOrCreate(
            ['id' => $request->id],
            $this->onlyPresent($request, ['name','description','is_active'])
        );

        return response()->json(['message' => 'Domaine saved','domaine' => $domaine]);
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
        Book::findOrFail($id)->delete();
        return response()->json(['message' => 'Book deleted']);
    }
}
