<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation de votre compte</title>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .email-header {
            padding: 30px;
            text-align: center;
            background-color: #8f2500;
        }

        .email-header img {
            max-height: 60px;
        }

        .email-body {
            padding: 30px;
        }

        .email-body h1 {
            font-size: 24px;
            color: #333;
        }

	    .code {
            display: inline-block;
            margin-top: 20px;
            padding: 14px 28px;
            background-color: #8f2500;
            color: #fff;
            border-radius: 8px;
            font-weight: bold;
        }

        .email-body p {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
        }



        .footer {
            text-align: center;
            font-size: 13px;
            color: #aaa;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <!-- Ton logo ici -->
            <img src="{{ $message->embed(public_path().'/logo/logo.jpg') }}" alt="Logo de l'app">
        </div>

        <div class="email-body">
            <h1>Bienvenue dans l'univers gastronomique !</h1>
            <p>
                Merci <span style="color:#8f2500;font-weight: 600;">{{$data['nom']}}</span>vous être inscrit sur notre réseau social dédié aux passionnés de cuisine.
                Pour finaliser votre inscription, veuillez confirmer votre adresse e-mail en entrant le code de validation ci dessous.
            </p>
	        <p class="code" style="color:white">{{$data['code']}}</p>
            <p>
                Si vous n'avez pas créé de compte, vous pouvez ignorer ce message.
            </p>
        </div>

        <div class="footer">
            &copy; {{ $data['date'] }} MGastro. Tous droits réservés.
        </div>
    </div>
</body>
</html>
