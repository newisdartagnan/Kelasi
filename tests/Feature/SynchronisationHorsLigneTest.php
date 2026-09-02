<?php

namespace Tests\Feature;

use App\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * La remontée d'un lot saisi sans réseau.
 *
 * Le point qui compte : la remontée doit être rejouable. Un chef de promotion
 * dont la connexion coupe en plein envoi relance, et rien ne se duplique.
 */
class SynchronisationHorsLigneTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireContexte();
    }

    public function test_un_lot_saisi_hors_ligne_remonte_et_attend_le_contreseing(): void
    {
        $reponse = $this->actingAs($this->chef)
            ->postJson(route('seances.synchroniser'), ['seances' => [$this->seance(), $this->seance()]]);

        $reponse->assertOk();
        $this->assertCount(2, $reponse->json('acceptees'));
        $this->assertSame(2, Seance::where('statut', Seance::STATUT_SOUMISE)->count());
        $this->assertSame('offline', Seance::first()->source);
    }

    public function test_renvoyer_le_meme_lot_ne_cree_pas_de_doublon(): void
    {
        $lot = ['seances' => [$this->seance(), $this->seance()]];

        $this->actingAs($this->chef)->postJson(route('seances.synchroniser'), $lot)->assertOk();
        $seconde = $this->actingAs($this->chef)->postJson(route('seances.synchroniser'), $lot);

        $seconde->assertOk();
        $this->assertCount(2, $seconde->json('ignorees'));
        $this->assertEmpty($seconde->json('acceptees'));
        $this->assertSame(2, Seance::count());
    }

    /**
     * Une ligne fautive ne doit pas faire tomber tout le lot : les autres
     * passent, et le client sait laquelle retirer de sa file.
     */
    public function test_une_seance_refusee_ne_bloque_pas_les_autres(): void
    {
        $valable = $this->seance();
        $future = $this->seance(['date_seance' => now()->addWeek()->toDateString()]);

        $reponse = $this->actingAs($this->chef)
            ->postJson(route('seances.synchroniser'), ['seances' => [$valable, $future]]);

        $reponse->assertOk();
        $this->assertSame([$valable['uuid']], $reponse->json('acceptees'));
        $this->assertArrayHasKey($future['uuid'], $reponse->json('refusees'));
        $this->assertSame(1, Seance::count());
    }

    public function test_un_enseignant_ne_peut_pas_remonter_des_seances(): void
    {
        $this->actingAs($this->enseignant)
            ->postJson(route('seances.synchroniser'), ['seances' => [$this->seance()]])
            ->assertOk();

        $this->assertSame(0, Seance::count());
    }

    public function test_la_synchronisation_exige_une_authentification(): void
    {
        $this->postJson(route('seances.synchroniser'), ['seances' => [$this->seance()]])
            ->assertUnauthorized();
    }

    /** @param  array<string, mixed>  $surcharges */
    private function seance(array $surcharges = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'cours_id' => $this->cours->id,
            'date_seance' => now()->subDays(3)->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre 2 : les sources du droit.',
            'saisie_locale_at' => now()->subDays(3)->toIso8601String(),
        ], $surcharges);
    }
}
