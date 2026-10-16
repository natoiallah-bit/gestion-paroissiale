<?php

namespace App\Http\Controllers;

use App\Models\Secteur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('secteur')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'secretaire', 'actif' => true]), 'secteurs' => Secteur::orderBy('nom')->get()]);
    }

    public function store(Request $request)
    {
        User::create($this->valider($request));

        return redirect()->route('users.index')->with('succes', 'Compte créé.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'secteurs' => Secteur::orderBy('nom')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $donnees = $this->valider($request, $user);
        if (empty($donnees['password'])) {
            unset($donnees['password']);
        }

        // L'administrateur ne peut pas se retirer lui-même ses droits ni se bloquer
        if ($user->is($request->user()) && ($donnees['role'] !== 'admin' || ! $donnees['actif'])) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre accès administrateur.'])->withInput();
        }

        $user->update($donnees);

        return redirect()->route('users.index')->with('succes', 'Compte modifié.');
    }

    private function valider(Request $request, ?User $user = null): array
    {
        $donnees = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'secteur_id' => ['nullable', 'integer', 'exists:secteurs,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
        $donnees['actif'] = $request->boolean('actif');
        // Le secrétaire travaille dans un secteur ; le trésorier sans secteur tient la caisse centrale du vicariat
        if ($donnees['role'] === 'secretaire' && empty($donnees['secteur_id'])) {
            throw ValidationException::withMessages(['secteur_id' => 'Choisissez le secteur de ce secrétaire.']);
        }
        if ($donnees['role'] === 'admin') {
            $donnees['secteur_id'] = null;
        }

        return $donnees;
    }
}
