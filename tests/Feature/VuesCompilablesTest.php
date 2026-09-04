<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Toutes les vues doivent compiler.
 *
 * Une erreur de syntaxe dans un gabarit ne se voit qu'en ouvrant la page
 * concernée -- et certaines pages ne s'ouvrent que pour un rôle particulier,
 * dans un état particulier. Ce test les compile toutes d'un coup.
 */
class VuesCompilablesTest extends TestCase
{
    public function test_chaque_gabarit_compile(): void
    {
        $fichiers = Finder::create()
            ->files()
            ->in(resource_path('views'))
            ->name('*.blade.php');

        $echecs = [];

        foreach ($fichiers as $fichier) {
            $compile = Blade::compileString($fichier->getContents());
            $erreur = $this->erreurDeSyntaxe($compile);

            if ($erreur) {
                $chemin = str_replace(resource_path('views').'/', '', $fichier->getPathname());
                $echecs[] = "{$chemin} : {$erreur}";
            }
        }

        $this->assertSame([], $echecs, "Des gabarits ne compilent pas :\n".implode("\n", $echecs));
    }

    /**
     * PHP n'expose pas de vérificateur de syntaxe pour une chaîne. On passe
     * donc par `php -l`, qui lit un fichier.
     */
    private function erreurDeSyntaxe(string $code): ?string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'blade').'.php';
        file_put_contents($chemin, $code);

        exec(escapeshellcmd(PHP_BINARY).' -l '.escapeshellarg($chemin).' 2>&1', $sortie, $statut);
        unlink($chemin);

        if ($statut === 0) {
            return null;
        }

        return trim(preg_replace('/ in .*$/m', '', implode(' ', $sortie)));
    }
}
