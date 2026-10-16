<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extrait — {{ $sacrement->fidele->nomComplet() }}</title>
    <style>
        body{ font-family:Georgia, 'Times New Roman', serif; color:#1e2420; max-width:720px; margin:30px auto; padding:0 24px; line-height:1.6; }
        .entete{ text-align:center; border-bottom:2px solid #1e2420; padding-bottom:12px; margin-bottom:26px; }
        .entete .petit{ font-size:13px; text-transform:uppercase; letter-spacing:1px; }
        .entete .nom{ font-size:22px; font-weight:bold; margin:2px 0; }
        h1{ text-align:center; font-size:20px; text-transform:uppercase; margin:20px 0 26px; }
        dl{ display:grid; grid-template-columns:230px 1fr; gap:6px 14px; }
        dt{ color:#555; } dd{ margin:0; font-weight:bold; }
        .pied{ margin-top:50px; display:flex; justify-content:space-between; }
        .mention{ margin-top:30px; font-size:12.5px; color:#555; text-align:center; }
        .imprimer{ text-align:center; margin-bottom:20px; }
        @media print{ .imprimer{ display:none; } body{ margin:0 auto; } }
    </style>
</head>
<body>
@php $f = $sacrement->fidele; @endphp
<div class="imprimer"><button onclick="window.print()">Imprimer</button></div>
<div class="entete">
    @if($vicariat->archidiocese)<div class="petit">Archidiocèse de {{ $vicariat->archidiocese }}</div>@endif
    @if($vicariat->doyenne)<div class="petit">Doyenné de {{ $vicariat->doyenne }}</div>@endif
    <div class="nom">Vicariat {{ $vicariat->nom }}</div>
    <div class="petit">Secteur {{ $sacrement->secteur?->nom }}@if($f->ceb) — {{ $f->ceb->nom }}@endif</div>
</div>

<h1>Extrait du registre — {{ \App\Support\Ressources::SACREMENTS[$sacrement->type] ?? $sacrement->type }}</h1>

<dl>
    <dt>Nom et prénoms</dt><dd>{{ $f->nomComplet() }}</dd>
    <dt>Sexe</dt><dd>{{ $f->sexe === 'M' ? 'Masculin' : 'Féminin' }}</dd>
    @if($f->date_naissance)<dt>Né(e) le</dt><dd>{{ $f->jour('date_naissance')->translatedFormat('d F Y') }}</dd>@endif
    <dt>Date du sacrement</dt><dd>{{ $sacrement->jour('date_sacrement')->translatedFormat('d F Y') }}</dd>
    @if($sacrement->lieu)<dt>Lieu</dt><dd>{{ $sacrement->lieu }}</dd>@endif
    @if($sacrement->ministre)<dt>Ministre</dt><dd>{{ $sacrement->ministre }}</dd>@endif
    @if($sacrement->parrain)<dt>Parrain / marraine / témoins</dt><dd>{{ $sacrement->parrain }}</dd>@endif
    @if($sacrement->conjoint)<dt>Conjoint</dt><dd>{{ $sacrement->conjoint }}</dd>@endif
    @if($sacrement->numero_registre)<dt>Registre n°</dt><dd>{{ $sacrement->numero_registre }}</dd>@endif
</dl>

<div class="pied">
    <div>Fait à {{ $vicariat->nom }}, le {{ now()->translatedFormat('d F Y') }}</div>
    <div>Le curé{{ $vicariat->responsable ? ' : '.$vicariat->responsable : '' }}</div>
</div>
<div class="mention">{{ $vicariat->pied_extrait ?: 'Pour copie conforme au registre.' }}</div>
</body>
</html>
