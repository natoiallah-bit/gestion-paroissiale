<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Http\Request;

class VenteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $du = $request->date('du') ?? today();
        $au = $request->date('au') ?? today();

        $requete = Vente::with('client', 'user')
            ->whereBetween('created_at', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])
            ->latest();

        // Un vendeur ne voit que ses propres ventes
        if (! $user->estGerant()) {
            $requete->where('user_id', $user->id);
        } elseif ($request->filled('vendeur')) {
            $requete->where('user_id', $request->integer('vendeur'));
        }

        $ventes = $requete->get();
        $validees = $ventes->where('statut', 'validee');

        return view('ventes.index', [
            'ventes' => $ventes,
            'du' => $du,
            'au' => $au,
            'totalVentes' => $validees->sum('total'),
            'totalEncaisse' => $validees->sum('montant_paye'),
            'vendeurs' => $user->estGerant() ? User::orderBy('name')->get() : collect(),
        ]);
    }

    public function show(Request $request, Vente $vente)
    {
        $this->verifierAcces($request, $vente);
        $vente->load('lignes', 'client', 'user', 'annuleePar');

        return view('ventes.show', compact('vente'));
    }

    public function ticket(Request $request, Vente $vente)
    {
        $this->verifierAcces($request, $vente);
        $vente->load('lignes', 'client', 'user');

        return view('ventes.ticket', ['vente' => $vente, 'boutique' => Boutique::courante()]);
    }

    public function annuler(Request $request, Vente $vente)
    {
        $donnees = $request->validate(['motif' => 'required|string|max:255']);

        if ($vente->estAnnulee()) {
            return back()->withErrors(['motif' => 'Cette vente est déjà annulée.']);
        }

        $vente->annuler($request->user()->id, $donnees['motif']);

        return redirect()->route('ventes.show', $vente)->with('succes', 'Vente annulée, articles remis en stock.');
    }

    private function verifierAcces(Request $request, Vente $vente): void
    {
        $user = $request->user();
        abort_unless($user->estGerant() || $vente->user_id === $user->id, 403);
    }
}
