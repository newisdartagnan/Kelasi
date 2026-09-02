<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * L'entree dans l'application se fait par le matricule, pas par l'adresse
 * electronique : c'est l'identifiant que l'université delivre, celui que tout
 * le monde connait par coeur, et beaucoup d'étudiants n'ont pas de courriel.
 */
class ConnexionController extends Controller
{
    public function formulaire(): View
    {
        return view('connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'matricule' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
        ]);

        $identifiants = [
            'matricule' => trim($donnees['matricule']),
            'password' => $donnees['password'],
        ];

        if (! Auth::attempt($identifiants, $request->boolean('memoriser'))) {
            throw ValidationException::withMessages([
                'matricule' => 'Matricule ou mot de passe incorrect.',
            ]);
        }

        $utilisateur = Auth::user();

        if (! $utilisateur->actif || $utilisateur->estSuspendu()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'matricule' => $utilisateur->motif_suspension
                    ? "Compte suspendu : {$utilisateur->motif_suspension}"
                    : 'Ce compte est désactivé. Adressez-vous au secrétariat académique.',
            ]);
        }

        $request->session()->regenerate();
        $utilisateur->forceFill(['derniere_connexion_at' => now()])->saveQuietly();

        return redirect()->intended(route('tableau-de-bord'));
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
