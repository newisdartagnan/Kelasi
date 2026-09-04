<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Les clés VAPID identifient le serveur auprès des services de push des
 * navigateurs. Elles se génèrent une fois, à l'installation, et ne changent
 * plus : les remplacer invaliderait tous les abonnements existants.
 */
class GenererLesClesVapid extends Command
{
    protected $signature = 'kelasi:vapid';

    protected $description = 'Génère la paire de clés VAPID nécessaire au push web.';

    public function handle(): int
    {
        if (config('services.webpush.public_key')) {
            $this->warn('Des clés sont déjà configurées.');
            $this->line('Les remplacer invaliderait tous les abonnements existants :');
            $this->line('chaque personne devrait réactiver les notifications sur chacun de ses appareils.');

            if (! $this->confirm('Générer une nouvelle paire malgré tout ?', false)) {
                return self::SUCCESS;
            }
        }

        $cles = VAPID::createVapidKeys();

        $this->info('Paire générée. Reportez ces deux lignes dans votre fichier .env :');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$cles['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$cles['privateKey']);
        $this->newLine();
        $this->comment('La clé privée ne doit jamais être versionnée ni servie au navigateur.');

        return self::SUCCESS;
    }
}
