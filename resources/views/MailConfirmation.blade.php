<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  
  <style>
    body {
      background-color: #0f172a;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
      color: #f1f5f9;
    }
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #1e293b;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .header {
      background-color: #3b82f6;
      padding: 30px 20px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 26px;
      color: #fff;
    }
    .content {
      padding: 30px 20px;
    }
    .content p {
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    .button {
      display: inline-block;
      padding: 12px 24px;
      background-color: #8b5cf6;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: bold;
    }
    .footer {
      background-color: #0f172a;
      padding: 20px;
      text-align: center;
      font-size: 12px;
      color: #94a3b8;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">
      <h1></h1>
    </div>
    <div class="content">
      
     
      <p> Votre  Code de Verification est :</p>
      <p style="text-align:center;">
        <a class="button"> {{$data}}</a>
      </p>
    </div>
    <div class="footer">
      © 2026 les edition du djembe d'ebene — Tous droits réservés.
    </div>
  </div>
</body>
</html>
