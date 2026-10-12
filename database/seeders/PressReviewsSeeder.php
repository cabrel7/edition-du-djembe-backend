<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed de démonstration pour les Cahiers du Djembé (revue de presse)
 * et les vidéos publiques. Idempotent (updateOrInsert par slug).
 */
class PressReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'title' => 'Rencontre avec Dr Nono Gaël Tanguy : « Faire résonner les voix de l’ici et de l’ailleurs »',
                'slug' => 'rencontre-nono-gael-tanguy-voix-ici-ailleurs',
                'subtitle' => 'Le fondateur des Éditions du Djembé d’Ébène dévoile les coulisses de la maison',
                'summary' => 'À quelques mois du premier anniversaire de la maison, le fondateur revient sur le parcours qui l’a mené de la recherche universitaire à l’édition indépendante, et sur son ambition : sortir les idées des tiroirs.',
                'content' => "Depuis son bureau du boulevard Saint Claude, à Québec, Dr Nono Gaël Tanguy veille sur une maison d'édition encore jeune mais déjà déterminée. Née en 2025, Les Éditions du Djembé d'Ébène sont nées d'un constat : trop de mémoires et de thèses remarquables finissent oubliés dans les rayonnages universitaires.\n\n« Nous avons décidé de faire éclore ces idées pertinentes », résume-t-il. La maison publie autant des travaux de recherche que des romans engagés et de la poésie militante, avec un accompagnement complet : relecture, mise en page, ISBN, diffusion.\n\nLe nom même de la maison résume sa philosophie : le djembé, instrument d'appel et de transmission ; l'ébène, symbole de noblesse et de résistance.",
                'author' => 'Rédaction',
                'source' => 'Le Fil des lettres',
                'source_url' => 'https://example.com/entretien-tanguy',
                'publication_date' => '2026-09-15',
                'category' => 'interview',
                'tags' => json_encode(['entretien', 'fondateur', 'édition']),
                'views' => 124,
                'is_featured' => 1,
                'status' => 'published',
                'published_at' => '2026-09-15',
            ],
            [
                'title' => 'Une maison québécoise qui donne voix aux mémoires oubliés',
                'slug' => 'maison-quebecoise-voix-memoires-oublies',
                'subtitle' => 'Reportage au cœur des Éditions du Djembé d’Ébène',
                'summary' => 'Entre deux corrections d’épreuves, l’équipe de la maison nous a ouvert ses portes. Portrait d’une structure qui veut relier la recherche universitaire au grand public.',
                'content' => "Dans ce local discret de la rue Saint Claude, on croise aussi bien des manuscrits de doctorat que des recueils de poésie. La particularité de la maison : traiter les deux avec la même exigence.\n\nLe comité de lecture, composé d'universitaires et d'hommes de lettres, examine chaque proposition en six à huit semaines. Puis commence le travail de fond : correction universitaire, mise en page, dépôt légal, diffusion en librairie et sur les plateformes numériques.\n\nUne grammaire éditoriale assumée, celle de faire résonner des voix trop souvent inaudibles.",
                'author' => 'Camille Fortin',
                'source' => 'Presse littéraire',
                'source_url' => null,
                'publication_date' => '2026-08-22',
                'category' => 'reportage',
                'tags' => json_encode(['reportage', 'Québec', 'maison d’édition']),
                'views' => 87,
                'is_featured' => 0,
                'status' => 'published',
                'published_at' => '2026-08-22',
            ],
            [
                'title' => 'Critique : quand la recherche devient récit',
                'slug' => 'critique-quand-recherche-devient-recit',
                'subtitle' => 'Retour sur un mémoire transformé en ouvrage',
                'summary' => 'La maison a publié un travail universitaire de plus de 400 pages sans le trahir : démonstration qu’un mémoire bien édité peut se lire comme un essai.',
                'content' => "Publier un mémoire, c'est risquer la prose académique aussi aride que rigoureuse. Le pari de la maison est réussi : le texte, retravaillé avec l'auteur, gagne en rythme sans rien céder à l'appareil critique.\n\nNotes de bas de page, bibliographie Chicago, index : tout y est. Et pourtant, on tourne les pages comme celles d'un essai narratif. La leçon est claire : la vulgarisation n'est pas une trahison, c'est un art.",
                'author' => 'J. Mbarga',
                'source' => 'Cahiers de lecture',
                'source_url' => null,
                'publication_date' => '2026-07-30',
                'category' => 'critique',
                'tags' => json_encode(['critique', 'mémoire', 'essai']),
                'views' => 63,
                'is_featured' => 0,
                'status' => 'published',
                'published_at' => '2026-07-30',
            ],
            [
                'title' => 'Appel à manuscrits : la maison cherche des voix engagées',
                'slug' => 'appel-a-manuscrits-voix-engagees',
                'subtitle' => 'Romans, essais et poésie : le comité de lecture ouvre sa session',
                'summary' => 'Les Éditions du Djembé d’Ébène lancent un appel à manuscrits pour les prochains mois. Critères : écriture forte, regard neuf, projets qui font résonner les expériences afro-descendantes et diasporiques.',
                'content' => "Le comité de lecture examine les propositions tout au long de l'année. Romans engagés, essais issus de la recherche, poésie militante : tous les genres sont bienvenus, pourvu que l'écriture assume une voix et une exigence.\n\nLe protocole de présentation (Times New Roman 12, double interligne, bibliographie Chicago pour les travaux de recherche) est détaillé sur la page « Soumettre mon travail ». Réponse garantie sous six à huit semaines.",
                'author' => 'Les Éditions du Djembé d’Ébène',
                'source' => 'Communiqué',
                'source_url' => null,
                'publication_date' => '2026-06-18',
                'category' => 'article',
                'tags' => json_encode(['appel à manuscrits', 'comité de lecture']),
                'views' => 210,
                'is_featured' => 0,
                'status' => 'published',
                'published_at' => '2026-06-18',
            ],
            [
                'title' => 'La poésie militante au salon du livre de Québec',
                'slug' => 'poesie-militante-salon-livre-quebec',
                'subtitle' => 'La maison présente son catalogue au public québécois',
                'summary' => 'Trois jours de rencontres, de dédicaces et de lectures publiques : le stand de la maison a attiré curieux, chercheurs et amoureux de littérature.',
                'content' => "Sur le stand, les couvertures du catalogue attirent l'œil. Les visiteurs viennent d'abord pour les titres, repartent avec des idées : celui de publier leur mémoire, celui d'offrir un recueil, celui de rejoindre le cercle du Djembé.\n\nLes séances de signature ont été l'occasion d'échanges riches sur la place des voix afro-descendantes dans la littérature québécoise.",
                'author' => 'Rédaction',
                'source' => 'Agenda culturel',
                'source_url' => null,
                'publication_date' => '2026-05-04',
                'category' => 'reportage',
                'tags' => json_encode(['salon du livre', 'Québec', 'événement']),
                'views' => 45,
                'is_featured' => 0,
                'status' => 'published',
                'published_at' => '2026-05-04',
            ],
            [
                'title' => 'Interview : publier sa thèse sans la trahir',
                'slug' => 'interview-publier-these-sans-la-trahir',
                'subtitle' => 'Une autrice témoigne de son parcours du mémoire à l’ouvrage',
                'summary' => 'De la soutenance à la librairie : une autrice raconte comment son travail de recherche a trouvé un second souffle en librairie.',
                'content' => "« Je pensais que ma thèse resterait au fond d'une bibliothèque. L'équipe de la maison m'a aidée à la rendre lisible sans l'appauvrir. » Le témoignage de cette autrice illustre la vocation de la maison : rendre accessibles des travaux de haute qualité.\n\nLe travail éditorial : relecture universitaire, restructuration des chapitres, harmonisation typographique, puis un vrai plan de diffusion.",
                'author' => 'Entretien réalisé par la rédaction',
                'source' => null,
                'source_url' => null,
                'publication_date' => '2026-04-12',
                'category' => 'interview',
                'tags' => json_encode(['entretien', 'thèse', 'parcours']),
                'views' => 78,
                'is_featured' => 0,
                'status' => 'published',
                'published_at' => '2026-04-12',
            ],
        ];

        foreach ($items as $item) {
            DB::table('press_reviews')->updateOrInsert(
                ['slug' => $item['slug']],
                $item + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $videos = [
            [
                'title' => 'Rencontre avec Dr Nono Gaël Tanguy, fondateur',
                'slug' => 'rencontre-nono-gael-tanguy-fondateur',
                'description' => 'Le fondateur des Éditions du Djembé d’Ébène revient sur la genèse de la maison, sa mission et sa vision de l’édition francophone engagée.',
                'thumbnail' => '',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'status' => 1,
            ],
            [
                'title' => 'Lecture : extraits du dernier recueil',
                'slug' => 'lecture-extraits-dernier-recueil',
                'description' => 'Lecture publique d’extraits du dernier titre paru, enregistrée lors du lancement à Québec.',
                'thumbnail' => '',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'status' => 1,
            ],
            [
                'title' => 'Le djembé et la parole : symbolique de la maison',
                'slug' => 'djembe-parole-symbolique-maison',
                'description' => 'Un court documentaire sur la symbolique du djembé et de l’ébène, deux piliers de l’identité de la maison d’édition.',
                'thumbnail' => '',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'status' => 1,
            ],
        ];

        foreach ($videos as $video) {
            DB::table('videos')->updateOrInsert(
                ['slug' => $video['slug']],
                $video + ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
