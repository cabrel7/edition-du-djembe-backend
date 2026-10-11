<?php

namespace App\Http\Controllers;

use App\Models\Domaine;
use App\Models\User;
use App\Models\Video;
use App\Models\Service;

use App\Models\Categorie;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesSafeUploads;
use App\Models\Article;
use App\Models\Book;
use App\Models\Memoir;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;



class UserController extends Controller
{
    use HandlesSafeUploads;

    public const BOOT_PAGE_SIZE = 24;

    /**
     * Affiche les 3 derniers contenus publiés (page d'accueil).
     */
    public function index()
    {
        $domainNames = Domaine::pluck('name', 'id');
        $categoryNames = Categorie::pluck('name', 'id');

        $lastarticles = Article::orderBy('created_at', 'desc')->where('status', 1)->take(3)->get();
        $lastmemoirs = Memoir::orderBy('created_at', 'desc')->where('status', 1)->take(3)->get()->map(function ($mem) use ($domainNames) {
            $mem->domain = $domainNames[$mem->domain] ?? $mem->domain;
            return $mem;
        });
        $lastbooks = Book::orderby('created_at', 'desc')->where('status', 1)->take(3)->get()->map(function ($book) use ($categoryNames) {
            $book->category = $categoryNames[$book->category] ?? $book->category;
            return $book;
        });
        return response()->json([
            'lastarticles' => $lastarticles,
            'lastmemoirs' => $lastmemoirs,
            'lastbooks' => $lastbooks
        ], 200);
    }
    public function updatearticleview(Request $request){
        $article = Article::find($request->id);
        if (!$article) {
            return response()->json(['message' => 'Article non trouvé'], 404);
        }
        Article::where('id', $article->id)->increment('views_count');
        return response()->json('success', 200);
    }

    public function updatememoirview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = Memoir::where('id', $request->id)->where('status', 1)->increment('views_count');
        if (!$updated) {
            return response()->json(['message' => 'Memoire non trouve'], 404);
        }

        return response()->json([
            'message' => 'Vue incrementee',
            'views_count' => Memoir::where('id', $request->id)->value('views_count'),
        ], 200);
    }
    public function downloadbook($id)
    {
        $book = book::where('id', $id)->where('status', 1)->first();
        if (!$book || !$book->extraitdocument) {
            return response()->json([
                'error' => 'Fichier PDF non trouvé'
            ], 404);
        }
        // Fichier privé (hors web root). basename() : les valeurs legacy
        // contiennent un chemin relatif ("books/documents/x.pdf"), le code
        // récent n'y stocke que le nom du fichier.
        $filePath = $this->privateOrLegacyPath(
            'books/extrait_documents/' . basename($book->extraitdocument),
            'books/extrait_documents'
        );

        if (!$filePath) {
            return response()->json([
                'error' => 'Fichier PDF non trouvé'
            ], 404);
        }



        return response()->download($filePath, $book->title . '.' . $book->extension, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $book->title . '.' . $book->extension . '"'
        ]);
    }


    public function downloadmemoir($id)
    {
        $memoir = Memoir::where('id', $id)->where('status', 1)->first();
        if (!$memoir || !$memoir->document) {
            return response()->json([
                'error' => 'Fichier PDF non trouvé'
            ], 404);
        }
        // Fichier privé (hors web root). basename() : valeurs legacy avec chemin.
        $filePath = $this->privateOrLegacyPath(
            'memoirs/documents/' . basename($memoir->document),
            'memoirs/documents'
        );

        if (!$filePath) {
            return response()->json([
                'error' => 'Fichier PDF non trouvé'
            ], 404);
        }
        Memoir::where('id', $id)->increment('downloads_count');

        return response()->download($filePath, $memoir->title . '.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $memoir->title . '.' . $memoir->extension . '"'
        ]);
    }
    /**
     * Colonnes publiques légères (exclut document/extraitdocument binaires).
     */
    private const BOOT_COLUMNS = [
        'books' => [
            'id', 'title', 'author', 'category', 'cover', 'description', 'published_date',
            'price', 'slug', 'isbn', 'amazon_url', 'pages', 'stock_quantity', 'language',
            'publisher', 'edition', 'rating',
        ],
        'articles' => [
            'id', 'title', 'excerpt', 'content', 'category', 'image', 'published_date',
            'author', 'slug', 'meta_title', 'meta_description', 'tags', 'views_count',
        ],
        'memoirs' => [
            'id', 'title', 'author', 'university', 'domain', 'year', 'slug', 'abstract',
            'download_url', 'file_size', 'file_format', 'keywords', 'supervisor',
            'defense_date', 'grade', 'views_count', 'downloads_count',
        ],
        'videos' => [
            'id', 'title', 'slug', 'description', 'thumbnail', 'video_url',
        ],
    ];

    /**
     * Liste publique paginée : seuls les contenus publiés (status=1).
     */
    private function publicPage(string $type)
    {
        $columns = self::BOOT_COLUMNS[$type] ?? ['*'];

        $query = match ($type) {
            'books' => Book::where('status', 1),
            'articles' => Article::where('status', 1),
            'memoirs' => Memoir::where('status', 1),
            'videos' => Video::where('status', 1),
            default => abort(404),
        };

        $paginator = $query->orderBy('id', 'desc')->select($columns)->cursorPaginate(self::BOOT_PAGE_SIZE);

        return [
            'items' => $paginator->items(),
            'next' => optional($paginator->nextCursor())->encode(),
            'hasmore' => $paginator->hasMorePages(),
        ];
    }

    public function getdata($type = null)
    {
        if ($type === 'null') {
            // Boot du front : base complète paginée, cache courte durée.
            $payload = Cache::remember('api:boot', now()->addMinutes(5), function () {
                $books = $this->publicPage('books');
                $articles = $this->publicPage('articles');
                $memoirs = $this->publicPage('memoirs');
                $videos = $this->publicPage('videos');

                return [
                    'domaine' => Domaine::all(),
                    'categories' => Categorie::all(),
                    'memoirs' => $memoirs['items'],
                    'memoirnexturl' => $memoirs['next'],
                    'hasmorememoir' => $memoirs['hasmore'],
                    'articles' => $articles['items'],
                    'articlenexturl' => $articles['next'],
                    'hasmorearticle' => $articles['hasmore'],
                    'books' => $books['items'],
                    'booknexturl' => $books['next'],
                    'hasmorebook' => $books['hasmore'],
                    'video' => $videos['items'],
                    'videonextUrl' => $videos['next'],
                    'hasmorevideo' => $videos['hasmore'],
                ];
            });

            return response()->json($payload, 200);
        }

        if (!empty($type)) {
            $page = match ($type) {
                'video' => $this->publicPage('videos'),
                'book' => $this->publicPage('books'),
                'article' => $this->publicPage('articles'),
                'memoir' => $this->publicPage('memoirs'),
                default => null,
            };

            if ($page !== null) {
                return response()->json(match ($type) {
                    'video' => [
                        'video' => $page['items'],
                        'videonextUrl' => $page['next'],
                        'hasmorevideo' => $page['hasmore'],
                    ],
                    'book' => [
                        'books' => $page['items'],
                        'booknexturl' => $page['next'],
                        'hasmorebook' => $page['hasmore'],
                    ],
                    'article' => [
                        'articles' => $page['items'],
                        'articlenexturl' => $page['next'],
                        'hasmorearticle' => $page['hasmore'],
                    ],
                    'memoir' => [
                        'memoirs' => $page['items'],
                        'memoirnexturl' => $page['next'],
                        'hasmorememoir' => $page['hasmore'],
                    ],
                }, 200);
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }
    /**
     * Store a newly created resource in storage.
     */
    // L'inscription publique (register) a été retirée : création de comptes
    // réservée à l'administration (aucun self-signup toléré).
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $token = $user->createToken("LaravelPassportToken")->accessToken;
            $user->save();

            // Le token n'est JAMAIS renvoyé en JSON (fallback tests/API) :
            // il voyage uniquement en cookie httpOnly, illisible par le JS.
            return response()->json(['user' => $user], 200)
                ->withCookie($this->authTokenCookie($request, $token));
        } else {
            return response()->json("mot de passe ou adresse email incorrect", 401);
        }
    }

    public function logout(Request $request)
    {
        optional($request->user()?->token())->revoke();

        return response()->json(['message' => 'deconnexion reussie'], 200)
            ->withCookie($this->clearAuthTokenCookie($request));
    }

    /**
     * Cookie httpOnly contenant le token Passport (24h, aligné tokensExpireIn).
     * SameSite : Lax par défaut (front et API même site). Mettre None+Secure
     * via API_COOKIE_SAMESITE si les domaines sont distincts.
     */
    private function authTokenCookie(Request $request, string $token)
    {
        return cookie(
            \App\Http\Middleware\AccessTokenFromCookie::COOKIE_NAME,
            $token,
            60 * 24,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            env('API_COOKIE_SAMESITE', 'Lax')
        );
    }

    private function clearAuthTokenCookie(Request $request)
    {
        return cookie(
            \App\Http\Middleware\AccessTokenFromCookie::COOKIE_NAME,
            '',
            -60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            env('API_COOKIE_SAMESITE', 'Lax')
        );
    }
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}
}
