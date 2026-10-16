<?php

namespace App\Http\Controllers;

use App\Models\Appareil;
use App\Support\AdresseReseau;
use Laravel\Sanctum\PersonalAccessToken;

class AppareilController extends Controller
{
    public function index()
    {
        $appareils = Appareil::with('user')->orderByDesc('derniere_synchro')->get();
        $connectes = PersonalAccessToken::where('name', 'like', 'appareil:%')->pluck('name')
            ->map(fn ($n) => substr($n, strlen('appareil:')))->flip();

        $adresses = AdresseReseau::urls(request()->getPort() ?: 8200);

        return view('appareils.index', compact('appareils', 'connectes', 'adresses'));
    }

    // Téléphone perdu ou employé parti : l'appareil devra se reconnecter
    public function revoquer(Appareil $appareil)
    {
        PersonalAccessToken::where('name', 'appareil:'.$appareil->uuid)->delete();

        return back()->with('succes', "L'appareil {$appareil->code} est déconnecté.");
    }
}
