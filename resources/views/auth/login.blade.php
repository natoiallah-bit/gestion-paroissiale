<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $boutiqueNom }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box; margin:0; padding:0;}
        body{ font-family:'Inter', Arial, sans-serif; background:#0f2a22; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; }
        .carte{ background:#f5f3ee; border-radius:14px; padding:34px 32px; width:100%; max-width:370px; }
        h1{ font-size:21px; color:#0f2a22; text-align:center; }
        .sous{ text-align:center; font-size:12.5px; color:#6b716c; margin:4px 0 22px; }
        label{ display:block; font-weight:600; font-size:12.5px; margin-top:14px; }
        input[type=email], input[type=password]{ width:100%; padding:10px; border:1px solid #e4e0d6; border-radius:7px; margin-top:5px; font-family:inherit; font-size:14px; }
        .case{ display:flex; align-items:center; gap:8px; margin-top:14px; font-size:13px; }
        button{ width:100%; background:#e0a030; color:#0f2a22; border:none; padding:11px; border-radius:7px; font-weight:700; margin-top:20px; cursor:pointer; font-size:14px; }
        .erreur{ background:#fbe5df; color:#b6452c; padding:10px; border-radius:6px; font-size:13px; margin-bottom:6px; }
    </style>
</head>
<body>
    <div class="carte">
        <h1>{{ $boutiqueNom }}</h1>
        <div class="sous">Connexion à la gestion du commerce</div>

        @if ($errors->any())
            <div class="erreur">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('login.submit') }}" method="POST">
            @csrf
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
            <label class="case"><input type="checkbox" name="remember"> Rester connecté</label>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>
