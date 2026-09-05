<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // L'application parle francais jusque dans ses URL : un visiteur non
        // connecte arrive sur /connexion, pas sur /login.
        $middleware->redirectGuestsTo('/connexion');
        $middleware->redirectUsersTo('/');

        // Un mot de passe provisoire est connu de celui qui l'a remis :
        // l'application ne laisse rien faire d'autre que d'en choisir un.
        $middleware->web(append: [
            \App\Http\Middleware\ExigerUnMotDePasseChoisi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
