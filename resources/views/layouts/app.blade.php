<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Accueil') — {{ $vicariatNom }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --vert-950:#0f2a22; --vert-900:#163a2f; --vert-800:#1f4d3f; --vert-600:#2f7a5f;
            --ambre-500:#e0a030; --ambre-100:#fbf0d9;
            --fond:#f5f3ee; --carte:#fff; --ligne:#e4e0d6; --encre:#1e2420; --doux:#6b716c;
            --rouge-600:#b6452c; --rouge-100:#fbe5df; --vert-100:#e1f1ea;
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{ font-family:'Inter', Arial, sans-serif; background:var(--fond); color:var(--encre); font-size:14px; }
        a{ color:var(--vert-800); }

        .app{ display:flex; min-height:100vh; }
        .sidebar{
            width:220px; flex-shrink:0; background:var(--vert-950); color:#fff; padding:18px 12px;
            position:sticky; top:0; height:100vh; overflow-y:auto;
        }
        .marque{ padding:6px 8px 16px; border-bottom:1px solid var(--vert-800); margin-bottom:10px; }
        .marque .nom{ font-weight:700; font-size:16px; }
        .marque .sous{ font-size:11px; color:var(--ambre-500); margin-top:2px; }
        .menu-label{ font-size:10px; text-transform:uppercase; letter-spacing:.6px; color:#86a397; margin:14px 8px 6px; }
        .menu-item{ display:block; color:#d5e2dc; text-decoration:none; font-weight:600; padding:8px 10px; border-radius:7px; margin-bottom:2px; }
        .menu-item:hover{ background:var(--vert-900); color:#fff; }
        .menu-item.actif{ background:var(--ambre-500); color:var(--vert-950); }
        .compte{ margin-top:18px; padding-top:14px; border-top:1px solid var(--vert-800); font-size:12px; color:#9db5aa; }
        .compte b{ color:#fff; display:block; font-size:13px; }
        .compte button{ width:100%; margin-top:10px; background:transparent; color:#fff; border:1px solid #4b6b5e; padding:7px; border-radius:6px; cursor:pointer; font-weight:600; }

        .barre-mobile{ display:none; }
        main{ flex:1; min-width:0; padding:26px 30px 60px; }
        h1{ font-size:24px; margin-bottom:18px; color:var(--vert-950); }
        h2{ font-size:16px; margin-bottom:12px; color:var(--vert-900); }
        .entete{ display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
        .entete h1{ margin:0; }

        .carte{ background:var(--carte); border:1px solid var(--ligne); border-radius:10px; padding:18px; margin-bottom:18px; }
        .grille{ display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); margin-bottom:18px; }
        .deux-col{ display:grid; gap:18px; grid-template-columns:1fr 1fr; }
        .stat{ background:var(--carte); border:1px solid var(--ligne); border-radius:10px; padding:14px 16px; }
        .stat .lib{ font-size:12px; color:var(--doux); font-weight:600; }
        .stat .val{ font-size:22px; font-weight:700; margin-top:4px; font-variant-numeric:tabular-nums; }
        .stat .det{ font-size:12px; color:var(--doux); margin-top:2px; }
        .positif{ color:var(--vert-600); } .negatif{ color:var(--rouge-600); }

        .tableau{ overflow-x:auto; }
        table{ width:100%; border-collapse:collapse; background:var(--carte); }
        th{ text-align:left; font-size:12px; color:var(--doux); font-weight:600; padding:9px 10px; border-bottom:2px solid var(--ligne); white-space:nowrap; }
        td{ padding:9px 10px; border-bottom:1px solid var(--ligne); vertical-align:middle; }
        td.n, th.n{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        tr.barre td{ color:var(--doux); text-decoration:line-through; }

        .btn{ display:inline-block; background:var(--vert-800); color:#fff; border:none; padding:8px 14px; border-radius:7px; font-weight:600; text-decoration:none; cursor:pointer; font-size:13px; font-family:inherit; }
        .btn:hover{ background:var(--vert-900); }
        .btn-ambre{ background:var(--ambre-500); color:var(--vert-950); } .btn-ambre:hover{ background:#c98b1f; }
        .btn-danger{ background:var(--rouge-600); } .btn-danger:hover{ background:#963721; }
        .btn-leger{ background:transparent; color:var(--vert-800); border:1px solid var(--ligne); }
        .btn-leger:hover{ background:var(--vert-100); }
        .btn-petit{ padding:4px 9px; font-size:12px; }

        .badge{ display:inline-block; padding:2px 8px; border-radius:20px; font-size:11.5px; font-weight:600; }
        .badge-rouge{ background:var(--rouge-100); color:var(--rouge-600); }
        .badge-vert{ background:var(--vert-100); color:var(--vert-800); }
        .badge-ambre{ background:var(--ambre-100); color:#8a5a08; }
        .badge-gris{ background:#ecebe6; color:var(--doux); }

        .alerte{ padding:11px 14px; border-radius:7px; margin-bottom:16px; }
        .alerte-succes{ background:var(--vert-100); color:var(--vert-800); }
        .alerte-erreur{ background:var(--rouge-100); color:var(--rouge-600); }
        .alerte-erreur ul{ margin-left:18px; }

        form.formulaire{ max-width:620px; }
        label{ display:block; font-weight:600; font-size:12.5px; margin:12px 0 4px; }
        input[type=text], input[type=email], input[type=password], input[type=number], input[type=date], input[type=search], input[type=tel], select, textarea{
            width:100%; padding:8px 10px; border:1px solid var(--ligne); border-radius:7px; font-family:inherit; font-size:14px; background:#fff;
        }
        input:focus, select:focus, textarea:focus{ outline:2px solid var(--ambre-500); border-color:transparent; }
        .case{ display:flex; align-items:center; gap:8px; font-weight:500; }
        .case input{ width:auto; }
        .ligne-champs{ display:flex; gap:12px; flex-wrap:wrap; }
        .ligne-champs > div{ flex:1; min-width:140px; }
        .filtres{ display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px; }
        .filtres label{ margin-top:0; }
        .aide{ font-size:12px; color:var(--doux); margin-top:4px; }
        .vide{ text-align:center; color:var(--doux); padding:24px; }
        .actions{ display:flex; gap:6px; flex-wrap:wrap; }
        .actions form{ display:inline; }
        .pagination{ display:flex; gap:8px; list-style:none; margin-top:12px; }
        .pagination li > *{ display:inline-block; padding:6px 12px; border:1px solid var(--ligne); border-radius:6px; text-decoration:none; }
        .pagination .disabled{ color:var(--doux); }

        @media (max-width:900px){
            .app{ display:block; }
            .sidebar{ position:fixed; left:-240px; top:0; z-index:20; transition:left .2s; width:230px; }
            .sidebar.ouvert{ left:0; box-shadow:0 0 0 100vmax rgba(0,0,0,.35); }
            .barre-mobile{ display:flex; align-items:center; gap:12px; background:var(--vert-950); color:#fff; padding:10px 16px; position:sticky; top:0; z-index:10; }
            .barre-mobile button{ background:none; border:1px solid #4b6b5e; color:#fff; border-radius:6px; padding:5px 10px; font-size:16px; }
            main{ padding:18px 16px 50px; }
            .deux-col{ grid-template-columns:1fr; }
            h1{ font-size:20px; }
        }
        @media print{ .sidebar, .barre-mobile, .pas-imprimer{ display:none !important; } main{ padding:0; } }
        @yield('styles')
    </style>
</head>
<body>
@php
    $u = auth()->user();
    $lien = fn ($route, $texte, $motif = null) => '<a href="'.route($route).'" class="menu-item'.(request()->routeIs($motif ?? $route) ? ' actif' : '').'">'.e($texte).'</a>';
@endphp
<div class="barre-mobile">
    <button type="button" onclick="document.querySelector('.sidebar').classList.toggle('ouvert')" aria-label="Menu">☰</button>
    <b>{{ $vicariatNom }}</b>
</div>
<div class="app">
    <nav class="sidebar" onclick="if(event.target===this) this.classList.remove('ouvert')">
        <div class="marque">
            <div class="nom">{{ $vicariatNom }}</div>
            <div class="sous">Gestion paroissiale</div>
        </div>

        {!! $lien('dashboard', 'Tableau de bord') !!}

        @php
            $entree = function ($cle, $texte = null) use ($u, $lien) {
                return \App\Support\Ressources::peutLire($u, $cle) ? $lien("$cle.index", $texte ?? \App\Support\Ressources::get($cle)['libelle'], "$cle.*") : '';
            };
        @endphp
        @if(\App\Support\Ressources::peutLire($u, 'fideles'))
            <div class="menu-label">Communauté</div>
            {!! $entree('fideles') !!}
            {!! $entree('cebs') !!}
            {!! $entree('mouvements', 'Mouvements & services') !!}
            {!! $entree('sacrements') !!}
        @endif

        <div class="menu-label">Vie vicariale</div>
        {!! $entree('annonces') !!}
        {!! $entree('activites', 'Programme') !!}

        @if(\App\Support\Ressources::peutLire($u, 'operations'))
            <div class="menu-label">Finances</div>
            {!! $entree('operations', 'Quêtes & dépenses') !!}
        @endif
        {!! $lien('rapports.index', 'Rapports') !!}

        @if($u->estAdmin())
            <div class="menu-label">Administration</div>
            {!! $entree('paroisses') !!}
            {!! $lien('users.index', 'Utilisateurs', 'users.*') !!}
            {!! $lien('appareils.index', 'Appareils') !!}
            {!! $lien('parametres.edit', 'Paramètres') !!}
        @endif

        <div class="compte">
            <b>{{ $u->name }}</b>
            {{ $u->libelleRole() }}
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">Se déconnecter</button>
            </form>
        </div>
    </nav>

    <main>
        @if(session('succes'))
            <div class="alerte alerte-succes">{{ session('succes') }}</div>
        @endif
        @if($errors->any())
            <div class="alerte alerte-erreur">
                <ul>@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
