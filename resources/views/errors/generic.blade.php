<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur {{ $status ?? 500 }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f8f9fa;
            color: #343a40;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 2rem;
        }
        .wrap { max-width: 480px; }
        .code {
            font-size: 6rem;
            font-weight: 700;
            line-height: 1;
            color: #dc3545;
        }
        h1 { font-size: 1.5rem; margin: 1rem 0 0.5rem; }
        p  { color: #6c757d; margin: 0.5rem 0; }
        a  { color: #0d6efd; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="code">{{ $status ?? 500 }}</p>
        <h1>Une erreur s'est produite</h1>
        @if (!empty($reason))
            <p>{{ $reason }}</p>
        @endif
        <p>Veuillez réessayer ou <a href="/">retourner à l'accueil</a>.</p>
    </div>
</body>
</html>
