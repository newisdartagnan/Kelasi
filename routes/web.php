<?php

use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\SynchronisationController;
use App\Livewire\JournalDesSeances;
use App\Livewire\SaisirSeance;
use App\Livewire\SeancesAValider;
use App\Livewire\TableauDeBord;
use Illuminate\Support\Facades\Route;

Route::view('/hors-ligne', 'hors-ligne')->name('hors-ligne');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'formulaire'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter']);
});

Route::middleware('auth')->group(function () {
    Route::post('/déconnexion', [ConnexionController::class, 'deconnecter'])->name('deconnexion');

    Route::get('/', TableauDeBord::class)->name('tableau-de-bord');
    Route::get('/seances/saisir', SaisirSeance::class)->name('seances.saisir');
    Route::get('/seances/a-valider', SeancesAValider::class)->name('seances.a-valider');
    Route::get('/seances/journal', JournalDesSeances::class)->name('seances.journal');

    Route::post('/seances/synchroniser', SynchronisationController::class)->name('seances.synchroniser');
});
