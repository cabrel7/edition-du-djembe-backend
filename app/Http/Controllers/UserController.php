<?php

namespace App\Http\Controllers;

use App\Models\domaine;
use App\Models\User;
use App\Models\Video;
use App\Models\Service;

use App\Models\Categorie;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Book;
use App\Models\Memoir;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;



class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lastarticles = Article::orderBy('created_at', 'desc')->where('status', 1)->take(3)->get();
        $lastmemoirs = Memoir::orderBy('created_at', 'desc')->where('status', 1)->take(3)->get()->map(function ($mem) {
            $mem->domain = domaine::where('id', $mem->domain)->first()->name;
            return $mem;
        });
        $lastbooks = Book::orderby('created_at', 'desc')->where('status', 1)->take(3)->get()->map(function ($book) {
            $book->category = Categorie::where('id', $book->category)->first()->name;
            return $book;
        });
        return response()->json([
            'lastarticles' => $lastarticles,
            'lastmemoirs' => $lastmemoirs,
            'lastbooks' => $lastbooks
        ], 200);
    }
    public function updatearticleview(Request $request){
        $article=Article::where('id',$request->id)->first();
        $article->views_count= $article->views_count+1;
        $article->save();
        return response()->json('success',200);
    }
    public function downloadbook($id)
    {
        $book = book::find($id);
        $filePath =  public_path("books/extrait_documents/" . $book->extraitdocument);


        if (!file_exists($filePath)) {
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
        $memoir = Memoir::where('id',$id)->first();
        $filePath =  public_path("memoirs/documents/" . $memoir->document);

        // Vérifier si le fichier existe
        if (!file_exists($filePath)) {
            return response()->json([
                'error' => 'Fichier PDF non trouvé'
            ], 404);
        }
        $memoir = Memoir::find($id);
        $memoir->downloads_count = $memoir->downloads_count + 1;
        $memoir->save();



        return response()->download($filePath, $memoir->title . '.' . $memoir->extension, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $memoir->title . '.' . $memoir->extension . '"'
        ]);
    }
    public function getdata($type = null)
    {
        if ($type == 'null') {
            $categories = Categorie::all();
            $domaine = domaine::all();
            $services = service::all();
            $paginator = video::orderBy('id', 'desc')->cursorPaginate(10000);
            $videonextUrl = optional($paginator->nextCursor())->encode();
            $hasmorevideo = $paginator->hasMorePages();
            $videos = $paginator->items();

            $paginator = book::orderBy('id', 'desc')->cursorPaginate(10000);
            $booknextUrl = optional($paginator->nextCursor())->encode();
            $hasmorebook = $paginator->hasMorePages();

            $books = $paginator->items(); // Les éléments de la page actuelle

            $paginator = Article::orderBy('id', 'desc')->cursorPaginate(10000);
            $articlenexturl = optional($paginator->nextCursor())->encode();
            $hasmorearticle = $paginator->hasMorePages();
            $articles = $paginator->items(); // Les éléments de la page actuelle


            $paginator = Memoir::orderBy('id', 'desc')->cursorPaginate(10000);
            $memoirnexturl = optional($paginator->nextCursor())->encode();
            $hasmorememoir = $paginator->hasMorePages();
            $memoirs = $paginator->items(); // Les éléments de la page actuelle

            return response()->json([
                // 'services'=>$services,
                'domaine' => $domaine,
                'categories' => $categories,
                'memoirs' => $memoirs,
                'memoirnexturl' => $memoirnexturl,
                'hasmorememoir' => $hasmorememoir,
                'articles' => $articles,
                'articlenexturl' => $articlenexturl,
                'hasmorearticle' => $hasmorearticle,
                'books' => $books,
                'booknexturl' => $booknextUrl,

                'hasmorebook' => $hasmorebook,
                'video' => $videos,
                'videonextUrl' => $videonextUrl,
                'hasmorevideo' => $hasmorevideo

            ], 200);
        }

        if (!empty($type)) {
            if ($type == 'video') {

                $paginator = video::orderBy('id', 'desc')->cursorPaginate(10000);
                $videonextUrl = optional($paginator->nextCursor())->encode();
                $hasmorevideo = $paginator->hasMorePages();
                $videos = $paginator->items();

                return response()->json([
                    'video' => $videos,
                    'videonextUrl' => $videonextUrl,
                    'hasmorevideo' => $hasmorevideo

                ], 200);
            }
            if ($type == 'book') {
                $paginator = book::orderBy('id', 'desc')->cursorPaginate(10000);
                $booknextUrl = optional($paginator->nextCursor())->encode();
                $hasmorebook = $paginator->hasMorePages();
                $books = $paginator->items(); // Les éléments de la page actuelle

                return response()->json(
                    [
                        'books' => $books,
                        'booknexturl' => $booknextUrl,

                        'hasmorebook' => $hasmorebook,
                    ],
                    200
                );
            }

            if ($type == 'article') {
                $paginator = Article::orderBy('id', 'desc')->cursorPaginate(10000);
                $articlenexturl = optional($paginator->nextCursor())->encode();
                $hasmorearticle = $paginator->hasMorePages();
                $articles = $paginator->items(); // Les éléments de la page actuelle

                return response()->json([
                    'articles' => $articles,
                    'articlenexturl' => $articlenexturl,
                    'hasmorearticle' => $hasmorearticle,

                ], 200);
            }
            if ($type == 'memoir') {
                $paginator = Memoir::orderBy('id', 'desc')->cursorPaginate(10000);
                $memoirnexturl = optional($paginator->nextCursor())->encode();
                $hasmorememoir = $paginator->hasMorePages();
                $memoirs = $paginator->items(); // Les éléments de la page actuelle

                return response()->json([
                    'memoirs' => $memoirs,
                    'memoirnexturl' => $memoirnexturl,
                    'hasmorememoir' => $hasmorememoir,


                ], 200);
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 404);
        }
        $user = new User();
        $user->name = $request->name;
        $user->prenom = $request->prenom;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();
        $token = $user->createToken("LaravelPassportToken")->accessToken;
        return response()->json(['user' => $user, 'token' => $token], 200);
    }
    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $token = $user->createToken("LaravelPassportToken")->accessToken;
            // $user->isconnected = 1;
            $user->save();
            return response()->json(['user' => $user, 'token' => $token], 200);
        } else {
            return response()->json("mot de passe ou adresse email incorrect", 401);
        }
    }
    public function logout(Request $request)
    {
        $user = Auth::user();
        $user->isconnected = 0;
        $user->save();
        $token = $request->user()->token();
        $token->revoke();
        return response()->json(['message' => 'deconnexion reussie'], 200);
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
