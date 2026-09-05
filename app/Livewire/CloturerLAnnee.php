<?php

namespace App\Livewire;

use App\Models\AnneeAcademique;
use App\Services\BasculeDAnnee;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * La clôture d'année, en deux temps.
 *
 * On lit d'abord ce que la bascule ferait — combien de promotions
 * reconduites, lesquelles s'arrêtent, ce qui reste en attente — puis on
 * confirme. L'opération est trop lourde de conséquences pour tenir en un
 * seul bouton.
 */
class CloturerLAnnee extends Component
{
    public string $libelle = '';

    public string $debut = '';

    public string $fin = '';

    public bool $confirmation = false;

    public function mount(): void
    {
        $courante = AnneeAcademique::courante();

        if (! $courante) {
            return;
        }

        $this->libelle = $this->libelleSuivant($courante->libelle, $courante->date_fin->year);
        $this->debut = $courante->date_debut->copy()->addYear()->toDateString();
        $this->fin = $courante->date_fin->copy()->addYear()->toDateString();
    }

    /**
     * Déduit le libellé de l'année suivante de celui de l'année courante :
     * « 2025-2026 » appelle « 2026-2027 ». C'est ce libellé que le
     * secrétariat lit et écrit, et il ne coïncide pas toujours avec les
     * dates saisies. À défaut de forme reconnaissable, on retombe sur
     * l'année de clôture.
     */
    private function libelleSuivant(string $courant, int $anneeDeCloture): string
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', $courant, $trouve)) {
            return ((int) $trouve[1] + 1).'-'.((int) $trouve[2] + 1);
        }

        return $anneeDeCloture.'-'.($anneeDeCloture + 1);
    }

    public function basculer(BasculeDAnnee $bascule): void
    {
        $this->validate([
            'libelle' => ['required', 'string', 'max:20'],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date'],
        ], attributes: ['libelle' => 'libellé', 'debut' => 'rentrée', 'fin' => 'clôture']);

        $courante = AnneeAcademique::courante();

        if (! $courante) {
            return;
        }

        try {
            $resultat = $bascule->basculer(auth()->user(), $courante, $this->libelle, $this->debut, $this->fin);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ === 'annee' ? 'libelle' : $champ, $messages[0]);
            }

            return;
        }

        session()->flash('succes', sprintf(
            'Année %s ouverte : %d promotion(s) reconduites, %d cours recopiés.',
            $resultat['annee']->libelle,
            $resultat['promotions'],
            $resultat['cours'],
        ));

        $this->confirmation = false;
        $this->redirectRoute('annees', navigate: false);
    }

    public function render(BasculeDAnnee $bascule): View
    {
        $courante = AnneeAcademique::courante();

        return view('livewire.cloturer-l-annee', [
            'courante' => $courante,
            'apercu' => $courante ? $bascule->apercu($courante) : null,
            'historique' => AnneeAcademique::withCount('promotions')->latest('date_debut')->get(),
        ]);
    }
}
