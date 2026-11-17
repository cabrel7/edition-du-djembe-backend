<?php

declare(strict_types=1);

namespace App\Services;

enum ImageVariantStatus: string
{
    /** Variante écrite. */
    case Created = 'created';
    /** Rien à faire (déjà à jour, animé, webp léger, mémoire…) : voir `reason`. */
    case Skipped = 'skipped';
    /** La variante n'était pas plus légère que l'original : non conservée. */
    case TooLarge = 'too_large';
    /** Pas une image supportée / chemin refusé. */
    case Unsupported = 'unsupported';
    case Failed = 'failed';
}
