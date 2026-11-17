<?php

/*
|--------------------------------------------------------------------------
| Variantes WebP des images publiques
|--------------------------------------------------------------------------
| Voir App\Services\ImageVariantService et la section « Variantes WebP » de API.md.
*/
return [
    // Racine des répertoires ci-dessous ; null = public_path(). Surchargée uniquement par les tests.
    'base_path' => null,

    // Liste blanche (relative à base_path). Aucun fichier hors de ces dossiers n'est jamais lu ni écrit.
    'directories' => [
        'articles',
        'books/covers',
        'memoirs/covers',
        'video',
        'partners',
        'team',
        'press-reviews/covers',
        'press-reviews/gallery',
        'press-reviews/editor',
        'service',
    ],

    // Fichier de verrou de la commande app:optimize-images ; null = storage/framework/optimize-images.lock.
    'lock_file' => null,
];
