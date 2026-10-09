<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #333;
            font-size: 14px;
            line-height: 1.6;
            margin: 20px;
        }

        h1, h2, h3 {
            color: #8f2500;
            margin-bottom: 5px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header img {
            width: 90px;
            margin-bottom: 10px;
        }

        .section {
            margin-top: 20px;
            page-break-inside: avoid; /* Évite de couper les sections entre les pages */
        }

        ul, ol {
            padding-left: 20px;
        }

        .details ul li {
            margin-bottom: 3px;
        }

        .ingredients li, .etapes li {
            margin-bottom: 6px;
        }

        .images-container {
            display: grid;
            grid-column: 2;
            gap: 10px;
            width:100%;
            margin-top: 15px;
        }

        .image-wrapper {
            width: 120px;
            height: 120px;
            border: 2px solid #dfbf45;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .signature {
            margin-top: 40px;
            font-style: italic;
            text-align: right;
            color: #8f2500;
        }

        .highlight {
            background-color: #dfbf45;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 5px;
        }

        .affiche_cat {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- Utilisation du chemin absolu pour le logo -->
        <img src="/logo/logo.jpg" alt="Logo MGASTRO">
        <h1>{{ $titre }}</h1>
        <div class="affiche_cat">
            @foreach ($categorie as $cat)
                <p class="highlight">{{ $cat }}</p>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h3>Description</h3>
        <p>{{ $description }}</p>
    </div>

    <div class="section details">
        <h3>Détails</h3>
        <ul>
            <li><strong>Difficulté :</strong> {{ $difficulte }}</li>
            <li><strong>Temps de préparation :</strong> {{ $temps_preparation }} min</li>
            <li><strong>Temps de cuisson :</strong> {{ $temps_cuisson }} min</li>
            <li><strong>Nombre de personnes :</strong> {{ $nb_personnes }}</li>
        </ul>
    </div>

    <div class="section">
        <h3>Ingrédients</h3>
        <ul class="ingredients">
            @foreach ($ingredients as $ingredient)
                <li>{{ $ingredient['quantite'] }} {{ $ingredient['unite'] }} de {{ $ingredient['nom'] }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h3>Étapes</h3>
        <ol class="etapes">
            @foreach ($etapes as $etape)
                <li>{{ $etape }}</li>
            @endforeach
        </ol>
    </div>

    @if(!empty($images))
    <div class="section">
        <h3>Images de la recette</h3>
        <div class="images-container">
            @foreach ($images as $img)
                <div class="image-wrapper">
                    <!-- Utilisation du chemin absolu pour les images -->
                    <img src="/recette_images/$img" alt="Image de la recette">

                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="signature">
        Recette réalisée par <strong>{{ $auteur }}</strong>
    </div>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                if ($PAGE_COUNT > 1) {
                    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                    $size = 10;
                    $pageText = "Page " . $PAGE_NUM . " / " . $PAGE_COUNT;
                    $x = 520;
                    $y = 820;
                    $pdf->text($x, $y, $pageText, $font, $size);
                }
            ');
        }
    </script>
</body>
</html>
