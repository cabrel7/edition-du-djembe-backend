<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\domaine as Domaine;
use App\Models\User;
use App\Models\Service;
use App\Models\Categorie;
use App\Models\Service_request;
use App\Models\order;
use App\Models\Book;
use App\Models\Video;
use App\Models\Memoir;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Log;
class AdminController extends Controller
{
 public function saveVideo(Request $request)
    {
        $data = $request->only(['title', 'description', 'video_url', 'status']);

        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/video'), $filename);
            $data['thumbnail'] = $filename;
        }

        $video = Video::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json(['message' => 'Video saved', 'video' => $video]);
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
        $user = auth()->user();
        if (!$user || $user->role != 1) return response()->json(['message' => 'Unauthorized'], 401);

        $data = $request->only([
            'title','excerpt','content','published_date','author','slug',
            'meta_title','meta_description','tags','views_count',
            'reading_time','status','featured'
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/articles'), $filename);
            $data['image'] = $filename;
        }

        $article = Article::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json(['message' => 'Article saved successfully','article' => $article]);
    }

    public function deleteArticle($id)
    {
        Article::findOrFail($id)->delete();
        return response()->json(['message' => 'Article deleted successfully']);
    }

    public function saveBook(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role != 1) return response()->json(['message' => 'Unauthorized'], 401);

        $data = $request->only([
            'title','author','description','category','price','isbn','slug',
            'pages','stock_quantity','language','publisher','edition',
            'status','published_date'
        ]);

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/books/covers'), $filename);
            $data['cover'] = $filename;
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(20) . '.' . $extension;
            $file->move(public_path('/books/documents'), $filename);
            $data['document'] = $filename;
            $data['extension'] = $extension;
        }

        if ($request->hasFile('extraitdocument')) {
            $file = $request->file('extraitdocument');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/books/extrait_documents'), $filename);
            $data['extraitdocument'] = $filename;
        }

        $book = Book::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json(['message' => 'Book saved successfully','book' => $book]);
    }

    public function deleteMemoir($id)
    {
        Memoir::findOrFail($id)->delete();
        return response()->json(['message' => 'Memoir deleted']);
    }

    public function saveMemoir(Request $request)
    {
        $data = $request->only([
            'title','author','university','domain','year','abstract',
            'slug','supervisor','defense_date','grade','status'
        ]);

        $data['keywords'] = json_encode($request->keywords);

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/memoirs/covers'), $filename);
            $data['cover'] = $filename;
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(20) . '.' . $extension;
            $file->move(public_path('/memoirs/documents'), $filename);
            $data['document'] = $filename;
            $data['extension'] = $extension;
        }

        $memoir = Memoir::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json(['message' => 'Memoir saved','memoir' => $memoir]);
    }

    public function saveService(Request $request)
    {
        $data = $request->only(['title','description','is_active','price_range']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/service'), $filename);
            $data['image'] = $filename;
        }

        $service = Service::updateOrCreate(
            ['id' => $request->id],
            $data
        );

        return response()->json(['message' => 'Service saved','service' => $service]);
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
            $request->only(['name','type','description','is_active'])
        );

        return response()->json(['message' => 'Categorie saved','categorie' => $categorie]);
    }

    public function deleteCategorie($id)
    {
        Categorie::findOrFail($id)->delete();
        return response()->json(['message' => 'Categorie deleted']);
    }

    public function saveDomaine(Request $request)
    {
        $domaine = Domaine::updateOrCreate(
            ['id' => $request->id],
            $request->only(['name','description','is_active'])
        );

        return response()->json(['message' => 'Domaine saved','domaine' => $domaine]);
    }

    public function deleteDomaine($id)
    {
        Domaine::findOrFail($id)->delete();
        return response()->json(['message' => 'Domaine deleted']);
    }
     public function deleteBook($id)
    {
        Book::findOrFail($id)->delete();
        return response()->json(['message' => 'Book deleted']);
    }
}
