<?php

namespace App\Http\Controllers;

use App\Models\Video;

use App\Models\Article;

use App\Models\Book;

use App\Models\Memoir;

use Illuminate\Http\Response;

class SitemapController extends Controller

{

    public function index()

    {

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';

        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        

        // Page d'accueil

        $sitemap .= $this->addUrl('https://leseditionsdudjembe.com/', '1.0', 'daily', now());

        

        // Pages statiques

        $staticPages = [

            'a-propos' => ['priority' => '0.9', 'changefreq' => 'monthly'],

            'catalogue' => ['priority' => '0.9', 'changefreq' => 'weekly'],

            'contact' => ['priority' => '0.8', 'changefreq' => 'monthly'],

            'actualites' => ['priority' => '0.8', 'changefreq' => 'daily'],

            'memoires' => ['priority' => '0.8', 'changefreq' => 'weekly'],

            'services' => ['priority' => '0.7', 'changefreq' => 'monthly'],

            'soumettre-mon-travail' => ['priority' => '0.9', 'changefreq' => 'monthly'],

            'videos' => ['priority' => '0.7', 'changefreq' => 'weekly'],

        ];

        

        foreach ($staticPages as $page => $config) {

            $sitemap .= $this->addUrl(

                "https://leseditionsdudjembe.com/{$page}",

                $config['priority'],

                $config['changefreq'],

                now()

            );

        }

        

        // Livres

        $books = Book::where('status', 1)->get();

        foreach ($books as $book) {

            $sitemap .= $this->addUrl(

                "https://leseditionsdudjembe.com/livre/{$book->id}",

                '0.8',

                'weekly',

                $book->updated_at

            );

        }

        

        // Articles

        $articles = Article::where('status', 1)->get();

        foreach ($articles as $article) {

            $sitemap .= $this->addUrl(

                "https://leseditionsdudjembe.com/actualites/{$article->slug}",

                '0.7',

                'monthly',

                $article->updated_at

            );

        }

        

        // Mémoires

        $memoirs = Memoir::where('status', 1)->get();

        foreach ($memoirs as $memoir) {

            $sitemap .= $this->addUrl(

                "https://leseditionsdudjembe.com/memoires/{$memoir->slug}",

                '0.7',

                'monthly',

                $memoir->updated_at

            );

        }

        

        // Vidéos

        $videos = Video::where('status', 1)->get();

        foreach ($videos as $video) {

            $sitemap .= $this->addUrl(

                "https://leseditionsdudjembe.com/videos/{$video->slug}",

                '0.6',

                'monthly',

                $video->updated_at

            );

        }

        

        $sitemap .= '</urlset>';

        

        return response($sitemap, 200)

            ->header('Content-Type', 'application/xml');

    }

    

    private function addUrl($loc, $priority, $changefreq, $lastmod)

    {

        $url = '<url>';

        $url .= "<loc>{$loc}</loc>";

        $url .= "<lastmod>" . $lastmod->format('Y-m-d') . "</lastmod>";

        $url .= "<changefreq>{$changefreq}</changefreq>";

        $url .= "<priority>{$priority}</priority>";

        $url .= '</url>';

        

        return $url;

    }

}
