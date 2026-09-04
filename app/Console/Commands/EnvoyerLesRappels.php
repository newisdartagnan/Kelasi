<?php

namespace App\Console\Commands;

use App\Services\RappelsQuotidiens;
use Illuminate\Console\Command;

class EnvoyerLesRappels extends Command
{
    protected $signature = 'kelasi:rappels';

    protected $description = 'Envoie les rappels quotidiens aux chefs de promotion, enseignants et autorités.';

    public function handle(RappelsQuotidiens $rappels): int
    {
        $touches = $rappels->envoyer();

        $this->info("Rappels envoyés à {$touches} personne(s).");

        return self::SUCCESS;
    }
}
