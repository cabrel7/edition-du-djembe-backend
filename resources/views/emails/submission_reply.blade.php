<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $customSubject }}</title>
  <style>
    body {
      background-color: #f4f5f7;
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      padding: 24px 12px;
      color: #1f2937;
    }
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #e5e7eb;
    }
    .header {
      background-color: #7c2d12;
      padding: 28px 24px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 22px;
      color: #ffffff;
    }
    .content {
      padding: 28px 24px;
    }
    .content p {
      font-size: 15px;
      line-height: 1.6;
      margin: 0 0 16px;
    }
    .message {
      background-color: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 16px;
      margin: 0 0 20px;
      font-size: 15px;
      line-height: 1.6;
      white-space: pre-line;
    }
    .reference {
      font-size: 13px;
      color: #6b7280;
      margin: 0 0 16px;
    }
    .footer {
      background-color: #f9fafb;
      border-top: 1px solid #e5e7eb;
      padding: 20px;
      text-align: center;
      font-size: 12px;
      color: #6b7280;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">
      <h1>Les Éditions du Djembé</h1>
    </div>
    <div class="content">
      <p>Bonjour {{ $submission->first_name }} {{ $submission->last_name }},</p>

      <div class="message">{{ $customMessage }}</div>

      <p class="reference">
        Concernant votre soumission « {{ $submission->title }} » (référence #{{ $submission->id }}).
      </p>

      <p>L'équipe des Éditions du Djembé</p>
    </div>
    <div class="footer">
      © {{ date('Y') }} Les Éditions du Djembé — Tous droits réservés.
    </div>
  </div>
</body>
</html>
