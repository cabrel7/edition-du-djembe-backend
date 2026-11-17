<?php

return [
    // Durée de mémorisation d'une réponse 2xx (secondes) : 24 h.
    'ttl_seconds' => 86400,

    // Durée max du verrou d'exécution (secondes) : couvre un upload lent, expire seul si le worker meurt.
    // LIMITE : au-delà de ce délai le verrou expire et une requête rejouée avec la même clé peut
    // s'exécuter en parallèle de la première (doublon possible). À relever si les uploads dépassent 120 s.
    // L'empreinte des fichiers envoyés = nom + taille (pas le contenu) : deux fichiers différents de
    // mêmes nom et taille sont traités comme identiques pour une même clé.
    'lock_seconds' => 120,

    // Attente max d'une requête concurrente avant 409 (secondes). 0 = 409 immédiat.
    'lock_wait_seconds' => 5,

    // Taille max d'un corps de réponse mémorisé (octets) : au-delà, la réponse n'est pas rejouable.
    'max_body_bytes' => 262144,

    // Valeur du Retry-After renvoyé avec le 409 (secondes).
    'retry_after_seconds' => 2,
];
