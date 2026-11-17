<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast JSON -> array tolérant aux données historiques doublement encodées
 * (une chaîne JSON contenant elle-même une chaîne JSON). Lecture : toujours un tableau
 * (ou null). Écriture : un seul niveau d'encodage, quelle que soit l'entrée.
 */
class LenientJsonArray implements CastsAttributes
{
    private const MAX_DECODE_DEPTH = 3;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return self::decode($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_string($value)) {
            // JSON de tableau (éventuellement doublement encodé) => normalisé ; sinon la chaîne
            // devient une liste à un élément (jamais de valeur perdue à la relecture).
            $value = self::decode($value) ?? [$value];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Le champ "%s" attend un tableau ou une chaîne, %s reçu.', $key, get_debug_type($value)));
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** Décode jusqu'à obtenir un tableau ; null si la valeur n'est pas un JSON de tableau. */
    public static function decode(mixed $value): ?array
    {
        for ($i = 0; $i < self::MAX_DECODE_DEPTH && is_string($value); $i++) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : null;
    }
}
