<?php

use App\Models\User;
use App\Support\Navigation;

if (! function_exists('navigationDe')) {
    /** @return list<array{route: string, libelle: string, icone: string, pastille?: int}> */
    function navigationDe(User $utilisateur): array
    {
        return Navigation::pour($utilisateur);
    }
}
