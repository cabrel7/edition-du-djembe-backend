<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesSafeUploads
{
    private function safeExt(UploadedFile $file, string $allowedMimes): string
    {
        $allowed = explode(',', $allowedMimes);
        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, $allowed, true) ? $guessed : $allowed[0];
    }

    private function storeUpload(UploadedFile $file, string $directory, string $allowedMimes): array
    {
        $extension = $this->safeExt($file, $allowedMimes);
        $filename = Str::random(20) . '.' . $extension;
        $file->move(public_path($directory), $filename);

        return ['filename' => $filename, 'extension' => $extension];
    }

    private function uniqueSlug(string $table, string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function validationMessages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être une chaîne de caractères.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'numeric' => 'Le champ :attribute doit être un nombre.',
            'date' => 'Le champ :attribute doit être une date valide.',
            'url' => 'Le champ :attribute doit être une URL valide.',
            'max' => [
                'file' => 'Le fichier :attribute ne doit pas dépasser :max Ko.',
                'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
                'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
            ],
            'min' => [
                'file' => 'Le fichier :attribute est trop petit.',
                'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
                'numeric' => 'Le champ :attribute doit être au minimum :min.',
            ],
            'between' => 'Le champ :attribute doit être compris entre :min et :max.',
            'mimes' => 'Le fichier :attribute doit être de type :values.',
            'image' => 'Le fichier :attribute doit être une image.',
            'file' => 'Le champ :attribute doit être un fichier valide.',
            'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
            'unique' => 'La valeur de :attribute est déjà utilisée.',
            'in' => 'La valeur sélectionnée pour :attribute est invalide.',
            'boolean' => 'Le champ :attribute doit être vrai ou faux.',
            'array' => 'Le champ :attribute doit être une liste valide.',
        ];
    }
}
