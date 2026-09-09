<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Derrière Caddy, la requête arrive en clair sur le réseau interne :
        // sans cette confiance, Laravel fabriquerait des URL en http:// sur un
        // site servi en https, et le navigateur refuserait de les charger. Le
        // service worker, lui, ne s'installerait pas du tout.
        //
        // On ne reconnaît que les adresses privées -- celles des conteneurs et
        // du réseau de l'université. Un visiteur venu d'Internet ne peut donc
        // pas prétendre arriver en HTTPS.
        //
        // La déclaration tient ici plutôt que dans bootstrap/app.php : les
        // intergiciels s'y configurent avant que la configuration soit lue, et
        // la plage de confiance doit rester réglable par le .env du serveur.
        TrustProxies::at(config('kelasi.mandataires'));
    }
}
