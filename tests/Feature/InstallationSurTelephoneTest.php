<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ce qui décide de la présence de Kelasi sur un téléphone.
 *
 * Rien ici ne touche à la base : ce sont le manifeste, les fichiers qu'il
 * annonce, et le lien entre le domaine et le paquet Android. Autant de choses
 * qui ne cassent jamais une page — elles cassent l'installation, six semaines
 * plus tard, sur un téléphone qu'on n'a pas sous la main.
 */
class InstallationSurTelephoneTest extends TestCase
{
    public function test_le_manifeste_declare_une_identite_stable(): void
    {
        $manifeste = $this->manifeste();

        // Sans identifiant, une application déjà installée devient une seconde
        // application le jour où start_url change : deux icônes, deux
        // sessions, et l'ancienne qui ne se met plus à jour.
        $this->assertSame('/', $manifeste['id']);
        $this->assertSame('/', $manifeste['start_url']);
        $this->assertSame('standalone', $manifeste['display']);
        $this->assertSame('Kelasi', $manifeste['short_name']);
    }

    public function test_le_manifeste_est_ecrit_en_francais_accentue(): void
    {
        $manifeste = $this->manifeste();

        $this->assertStringContainsString('déroulement', $manifeste['description']);
        $this->assertStringContainsString('faculté', $manifeste['description']);
    }

    public function test_chaque_fichier_annonce_par_le_manifeste_existe(): void
    {
        $manifeste = $this->manifeste();

        $annonces = array_merge(
            array_column($manifeste['icons'], 'src'),
            array_column($manifeste['screenshots'], 'src'),
        );

        $this->assertNotEmpty($annonces);

        foreach ($annonces as $chemin) {
            // Une icône manquante dégrade la boîte d'installation d'Android
            // sans le moindre message : elle se contente d'être plus pauvre.
            $this->assertFileExists(public_path(ltrim($chemin, '/')), "Le manifeste annonce {$chemin}, absent de public/.");
        }
    }

    public function test_les_captures_ont_la_taille_qu_elles_annoncent(): void
    {
        foreach ($this->manifeste()['screenshots'] as $capture) {
            [$largeur, $hauteur] = getimagesize(public_path(ltrim($capture['src'], '/')));

            $this->assertSame(
                $capture['sizes'],
                "{$largeur}x{$hauteur}",
                "La capture {$capture['src']} ne fait pas la taille annoncée.",
            );
        }
    }

    public function test_sans_empreinte_configuree_le_lien_android_est_absent(): void
    {
        config(['kelasi.android.empreintes' => []]);

        // Mieux vaut pas de fichier qu'un fichier vide : une vérification qui
        // échoue sur une liste vide ressemble à un bug du navigateur.
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_le_lien_android_declare_le_paquet_et_ses_empreintes(): void
    {
        config([
            'kelasi.android.paquet' => 'cd.kelasi.app',
            'kelasi.android.empreintes' => ['AA:BB:CC', '11:22:33'],
        ]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJson([[
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'cd.kelasi.app',
                    'sha256_cert_fingerprints' => ['AA:BB:CC', '11:22:33'],
                ],
            ]]);
    }

    public function test_le_lien_android_s_ouvre_sans_session(): void
    {
        config(['kelasi.android.empreintes' => ['AA:BB:CC']]);

        // Chrome le lit au tout premier lancement, avant toute connexion.
        // Derrière l'authentification, il ne verrait qu'une redirection.
        $this->get('/.well-known/assetlinks.json')->assertOk();
    }

    public function test_l_application_croit_le_mandataire_qui_termine_le_https(): void
    {
        $plages = config('kelasi.mandataires');

        // Sans cette confiance, une page servie en https fabriquerait des URL
        // en http, et le navigateur refuserait de les charger.
        $this->assertContains('172.16.0.0/12', $plages, 'Le réseau de Docker doit être reconnu.');
        $this->assertNotContains('*', $plages, 'Un visiteur venu d\'Internet ne doit pas pouvoir se dire en HTTPS.');
    }

    private function manifeste(): array
    {
        return json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
