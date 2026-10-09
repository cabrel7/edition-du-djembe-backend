<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    public function index()
    {
        $robots = "User-agent: *\n";
        $robots .= "Allow: /\n";
        $robots .= "Disallow: /admin/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "Disallow: /private/\n\n";
        
        $robots .= "# Sitemaps\n";
        $robots .= "Sitemap: https://leseditionsdudjembe.com/sitemap.xml\n";
        $robots .= "Sitemap: https://api.leseditionsdudjembe.com/sitemap.xml\n\n";
        
        $robots .= "# Crawl delay\n";
        $robots .= "Crawl-delay: 10\n";
        
        return response($robots, 200)
            ->header('Content-Type', 'text/plain');
    }
}
