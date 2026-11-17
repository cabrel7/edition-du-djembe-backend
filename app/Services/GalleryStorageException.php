<?php

declare(strict_types=1);

namespace App\Services;

/** Échec d'écriture disque d'une galerie ; le message est sûr à exposer (aucun chemin serveur). */
class GalleryStorageException extends \RuntimeException
{
}
