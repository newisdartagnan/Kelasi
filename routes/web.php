<?php

use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\SynchronisationController;
use App\Livewire\ActiverMonCompte;
use App\Livewire\DemandesDeModification;
use App\Livewire\ImporterLesInscrits;
use App\Livewire\JournalDesSeances;
use App\Livewire\SaisirSeance;
use App\Livewire\SeancesAValider;
use App\Livewire\TableauDeBord;
use Illuminate\Support\Facades\Route;

Route::view('/hors-ligne', 'hors-ligne')->name('hors-ligne');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'formulaire'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter']);

    // On n'ouvre pas un compte, on active une ligne déposée par le secrétariat.
    Route::get('/activation', ActiverMonCompte::class)->name('activation');
});

Route::middleware('auth')->group(function () {
    Route::post('/déconnexion', [ConnexionController::class, 'deconnecter'])->name('deconnexion');

    Route::get('/', TableauDeBord::class)->name('tableau-de-bord');
    Route::get('/seances/saisir', SaisirSeance::class)->name('seances.saisir');
    Route::get('/seances/a-valider', SeancesAValider::class)->name('seances.a-valider');
    Route::get('/seances/journal', JournalDesSeances::class)->name('seances.journal');

    Route::post('/seances/synchroniser', SynchronisationController::class)->name('seances.synchroniser');

    Route::get('/demandes', DemandesDeModification::class)->name('demandes');

    Route::get('/inscrits', ImporterLesInscrits::class)
        ->middleware('can:inscription.deposer')
        ->name('inscrits');
});
