<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmation de réception</title>
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
    .header p {
      margin: 8px 0 0;
      font-size: 14px;
      color: #fde7d3;
    }
    .content {
      padding: 28px 24px;
    }
    .content p {
      font-size: 15px;
      line-height: 1.6;
      margin: 0 0 16px;
    }
    .details {
      background-color: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 6px;
      padding: 16px;
      margin: 0 0 20px;
    }
    .details p {
      margin: 0 0 8px;
      font-size: 14px;
    }
    .details p:last-child {
      margin-bottom: 0;
    }
    .details strong {
      color: #374151;
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
      <p>Accusé de réception de votre soumission</p>
    </div>
    <div class="content">
      <p>Bonjour {{ $submission->first_name }} {{ $submission->last_name }},</p>

      <p>
        Nous vous confirmons la bonne réception de votre soumission.
        Notre comité éditorial l'examinera attentivement et nous reviendrons
        vers vous dans les plus brefs délais.
      </p>

      <div class="details">
        <p><strong>Type de document :</strong> {{ $typeName }}</p>
        <p><strong>Titre :</strong> {{ $submission->title }}</p>
        <p><strong>Date de soumission :</strong> {{ $submission->created_at->format('d/m/Y à H:i') }}</p>
        <p><strong>Référence :</strong> #{{ $submission->id }}</p>
      </div>

      <p>
        Si vous avez des questions entre-temps, répondez simplement à cet email.
      </p>

      <p>L'équipe des Éditions du Djembé</p>
    </div>
    <div class="footer">
      © {{ date('Y') }} Les Éditions du Djembé — Tous droits réservés.
    </div>
  </div>
</body>
</html>
