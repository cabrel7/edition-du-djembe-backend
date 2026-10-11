# API — Les Éditions du Djembé

API REST JSON (Laravel 10 + Passport). Conçue pour être consommée par des clients
web, mobiles ou générés par IA : authentification par cookie httpOnly, pagination
par curseur, erreurs JSON normalisées.

## Table des matières

1. [Conventions](#1-conventions)
2. [Authentification](#2-authentification)
3. [Données publiques](#3-données-publiques)
4. [Presse (revues de presse)](#4-presse-revues-de-presse)
5. [Formulaires publics](#5-formulaires-publics)
6. [Administration](#6-administration)

---

## 1. Conventions

| Élément | Valeur |
|---|---|
| Base URL (prod) | `https://api.leseditionsdudjembe.com/api` |
| Base URL (dev) | `http://localhost:8000/api` |
| Format | `application/json; charset=utf-8` (sauf uploads `multipart/form-data`) |
| Encodage dates | ISO 8601 (`2026-01-15T10:30:00.000000Z`) |
| Langue des erreurs | Français |

### Erreurs

Toute erreur retourne JSON :

```json
{ "message": "Description lisible" }
```

Échec de validation (HTTP 422) :

```json
{
  "success": false,
  "message": "Erreur de validation",
  "errors": { "title": ["Le champ title est obligatoire."] }
}
```

Codes courants : `401` non authentifié · `403` non admin · `404` introuvable ·
`422` validation · `429` trop de requêtes (login/register : 6/min/IP).

### Pagination (curseur)

Les listes publiques retournent **24 éléments** maximum par page plus une clé de
curseur. Pour la page suivante, renvoyez le curseur reçu :

```
GET /api/data/book?cursor=<curseur_reçu>
```

Réponse paginée (exemple livres) :

```json
{
  "books": [ ... ],
  "booknexturl": "eyJpZCI6MTJ9",
  "hasmorebook": true
}
```

`hasmore*` = `false` → dernière page. Les listes admin utilisent le même schéma
(`nexturl` / `hasmore`, pages de 10000 = liste quasi complète).

### Uploads

En-têtes : `Content-Type: multipart/form-data`.

| Type | Extensions | Taille max |
|---|---|---|
| Image | jpg, jpeg, png, gif, webp | 5 Mo |
| Document | pdf, doc, docx | 20 Mo |
| Image partenaire | jpg, jpeg, png, gif, svg | 5 Mo |

---

## 2. Authentification

L'authentification utilise un **cookie httpOnly** `edj_token` (durée 24 h,
SameSite=Lax, Secure sur HTTPS). Le token n'est jamais exposé en JSON ni en
`localStorage` : le navigateur le renvoie automatiquement. Pour un client non
navigateur, transmettez la valeur du cookie dans l'en-tête HTTP :

```
Authorization: Bearer <valeur_du_cookie_edj_token>
```

### POST /api/login

Throttle : 6 requêtes/minute/IP.

```json
{ "email": "admin@example.com", "password": "password" }
```

Réponse `200` (+ en-tête `Set-Cookie: edj_token=...; HttpOnly`), **sans token dans le JSON** :

```json
{ "user": { "id": 1, "name": "Admin", "email": "admin@example.com", "role": 1 } }
```

`role: 1` = administrateur. HTTP `401` si identifiants invalides.

### POST /api/register

Throttle : 6 requêtes/minute/IP.

```json
{ "name": "Jean", "prenom": "Dupont", "email": "jean@example.com", "password": "secret123" }
```

Même réponse que login (cookie + `user`).

### POST /api/logout  🔒

Révoke le token courant et efface le cookie. Réponse `200` :
`{ "message": "Déconnexion réussie" }`.

---

## 3. Données publiques

### GET /api/data/{type}

Boot et pagination du site public. `{type}` :

| type | Clés de réponse |
|---|---|
| `null` (boot) | `domaine`, `categories`, `books`, `articles`, `memoirs`, `video` + curseurs `booknexturl`/`hasmorebook`, `articlenexturl`/`hasmorearticle`, `memoirnexturl`/`hasmorememoir`, `videonextUrl`/`hasmorevideo` |
| `book` | `books`, `booknexturl`, `hasmorebook` |
| `article` | `articles`, `articlenexturl`, `hasmorearticle` |
| `memoir` | `memoirs`, `memoirnexturl`, `hasmorememoir` |
| `video` | `video`, `videonextUrl`, `hasmorevideo` |

Paramètre : `?cursor=<curseur>` (page suivante). Boot mis en cache 5 min.

Élément livre : `id`, `title`, `author`, `category` (id), `description`, `price`,
`isbn`, `cover`, `pages`, `language`, `status`, `published_date`, `amazon_url`…
Seuls les contenus **publiés** (`status = 1`) sont exposés.

Élément article : `id`, `title`, `excerpt`, `content`, `image`, `author`,
`published_date`, `tags`, `status`, `featured`, `reading_time`.

Élément mémoire : `id`, `title`, `author`, `university`, `domain` (id), `year`,
`abstract`, `keywords`, `supervisor`, `grade`, `cover`, `status`.

Élément vidéo : `id`, `title`, `description`, `video_url`, `thumbnail`, `status`.

### GET /api/downloadmemoir/{id}  ·  GET /api/downloadbook/{id}

Téléchargement du PDF. `200` (PDF) · `404` si introuvable ou non publié.

### POST /api/updatearticleview · POST /api/updatememoirview

Incrémente le compteur de vues.

```json
{ "id": 3 }
```

Réponse : `{ "success": true }`.

### GET /api/getbasedata

Données de base (catégories, domaines…). Réponse : `{ "categories": [...], "domaine": [...] }`.

### POST /api/data/team · POST /api/data/partner

Listes membres / partenaires (pagination curseur identique) :

```json
{ "team_members": [...], "teamnexturl": "...", "hasmoreteam": true }
```

```json
{ "partners": [...], "partnernexturl": "...", "hasmorepartner": true }
```

---

## 4. Presse (revues de presse)

### GET /api/press-reviews

Liste publique des revues **publiées**. Paramètres optionnels :
`?status=&category=&search=&featured=&order_by=created_at&order_dir=desc&per_page=20&page=1`.

Réponse :

```json
{
  "success": true,
  "data": {
    "press_reviews": [ ... ],
    "pagination": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 45 }
  }
}
```

### GET /api/press-reviews/popular-tags

`{ "tags": [ { "tag": "musique", "count": 8 }, ... ] }`.

### GET /api/press-reviews/{slug}

Détail par slug. `404` si absent ou non publié.

---

## 5. Formulaires publics

### POST /api/contacts

```json
{ "name": "Nom", "email": "a@b.c", "subject": "Sujet", "message": "Bonjour…" }
```

Réponse `201` : `{ "success": true, "message": "..." }`.

### POST /api/submissions

Soumission d'un manuscrit (`multipart/form-data`) :

| Champ | Type | Requis |
|---|---|---|
| `type` | `book` \| `memoir` \| `article` | ✅ |
| `first_name`, `last_name` | texte (2–50) | ✅ |
| `email` | email ≤100 | ✅ |
| `title` | texte (3–200) | ✅ |
| `summary` | texte (100–2000) | ✅ |
| `file` | PDF ≤ 20 Mo uniquement | ✅ |
| `rgpd_consent` | `true` (consentement obligatoire) | ✅ |
| `phone` | texte ≤20 | ❌ |

Réponse `201` : `{ "success": true, "message": "..." }`.

---

## 6. Administration 🔒

Toutes les routes ci-dessous exigent d'être authentifié (`edj_token` ou
`Authorization: Bearer`) **et** `role = 1` (sinon `401` / `403`).

### Dashboard & listes

| Méthode | Chemin | Description |
|---|---|---|
| GET | `/api/adminbase` | Dashboard : `categories`, `domaine`, `service_requests`, `orders`, `users`, `videos` |
| GET | `/api/getbook` | `books` + `nexturl`, `hasmore` |
| GET | `/api/getarticle` | `articles` + `nexturl`, `hasmore` |
| GET | `/api/getmemoir` | `memoirs` + `nexturl`, `hasmore` |

### Contenus (create/update)

Tous en `POST`, champs partiels autorisés à la mise à jour (envoyez `id` pour
l'update ; `success: true, data: {...}` en réponse).

#### /api/saveBook — `multipart/form-data`

| Champ | Type | Création | Update |
|---|---|---|---|
| `id` | entier | — | ✅ |
| `title`, `author` | texte ≤255 | ✅ | optionnel |
| `category` | entier (id catégorie) | ✅ | optionnel |
| `description` | texte ≤10000 | ✅ | optionnel |
| `price` | décimal ≥0 | ✅ | optionnel |
| `isbn` | texte (unique) | ✅ | optionnel |
| `pages` | entier ≥1 | ✅ | optionnel |
| `document`, `extraitdocument` | fichier ≤20 Mo | ✅ | optionnel |
| `cover` | image ≤5 Mo | ❌ | optionnel |
| `amazon_url`, `stock_quantity`, `language`, `publisher`, `edition`, `status` (bool), `published_date` | — | optionnel | optionnel |

#### /api/saveArticle

`title`, `excerpt` (≤1000), `content` requis à la création · optionnels :
`image` (image ≤5 Mo), `published_date`, `author`, `meta_title`,
`meta_description` (≤1000), `tags` (tableau), `views_count`, `reading_time`,
`status` (`0|1|2`), `featured` (booléen).

#### /api/saveMemoir

`title`, `author`, `university`, `domain` (id domaine), `year` (1900–2100),
`abstract` (≤10000) requis à la création · optionnels : `keywords` (tableau),
`supervisor`, `defense_date`, `grade`, `status` (`0|1|2`), `cover`, `document`.

#### /api/saveVideo

`title`, `description`, `video_url` (URL), `thumbnail` (**image uniquement** :
jpg, jpeg, png, gif, webp ≤5 Mo) requis à la création · optionnel : `status` (`0|1|2`).

#### /api/saveDomaine · /api/saveCategorie · /api/saveService

Champs communs : `name` ✅, `description`, `is_active` (bool). `type` supplémentaire
pour catégories et services (texte). Mise à jour partielle via `id`.

### Suppressions

`DELETE /api/delete{Domaine|Categorie|Video|Book|Service|Memoir|Article}/{id}`
→ `{ "success": true }` · `404` si absent · `409` si référencé (contrôle
d'intégrité).

### Presse — routes `/api/admin/press-reviews`

| Méthode | Chemin | Description |
|---|---|---|
| GET | `/` | Liste + stats (filtres : `status`, `category`, `search`, `featured`, `order_by`, `order_dir`, `per_page`) |
| GET | `/{id}` | Détail |
| POST | `/` | Création (`multipart/form-data`) : `title` ✅, `summary` ✅, `content` ✅, `category` ✅ (`interview|critique|reportage|article|autre`), `status` ✅ (`draft|published|archived`), `subtitle`, `author`, `source`, `source_url`, `publication_date`, `published_at`, `tags` (tableau), `cover_image` (image ≤5 Mo), `gallery_images.*`, `documents.*`, `video_url`, `audio_url` (slug et `is_featured` gérés automatiquement / par la route dédiée) |
| PUT | `/{id}` | Mise à jour (partielle) |
| PUT | `/{id}/status` | `{ "status": "published" }` |
| PUT | `/{id}/featured` | Bascule article à la une |
| POST | `/upload-image` | `multipart/form-data`, champ `image` → `{ "url": "..." }` |
| DELETE | `/{id}` | Suppression |

### Soumissions — `/api/admin/submissions`

`GET /` (liste paginée) · `GET /statistics` · `GET /{id}` ·
`PUT /{id}/status` (`{ "status": "approved" }`) · `GET /{id}/download` (PDF) ·
`POST /{id}/send-email` · `DELETE /{id}`.

### Contacts — `/api/contacts`

`GET /` (liste) · `GET /statistics` · `GET /{id}` ·
`PUT /{id}/status` (`{ "status": "read" }`) · `DELETE /{id}`.

### Équipe & partenaires

| Méthode | Chemin | Description |
|---|---|---|
| GET/POST | `/api/team_members` | Liste / création (`multipart/form-data` : `name`, `role`, `bio`, `image`…) |
| PUT/DELETE | `/api/team_members/{id}` | Mise à jour / suppression |
| GET/POST | `/api/partners` | Idem (`name`, `website`, `logo`… image ou svg) |
| PUT/DELETE | `/api/partners/{id}` | Mise à jour / suppression |

---

## Annexe — codes statut métier (contenus)

| Champ | Valeurs |
|---|---|
| `status` livres | `false` = brouillon · `true` = publié |
| `status` articles/mémoires/vidéos | `0` = brouillon · `1` = publié · `2` = archivé |
| `status` presse | `draft` · `published` · `archived` |
| `status` contacts | `pending` · `read` · `replied` · `archived` |
| `status` soumissions | `pending` · `reviewing` · `accepted` · `rejected` · `archived` |
