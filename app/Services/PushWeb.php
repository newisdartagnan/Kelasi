<?php

namespace App\Services;

use App\Models\AbonnementPush;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush as Client;

/**
 * Le push web, pour la PWA installée.
 *
 * Sur Android, la notification arrive comme celle d'une application native.
 * Sur iOS, elle ne fonctionne qu'une fois la page ajoutée à l'écran
 * d'accueil : c'est une limite de Safari, pas un défaut de configuration, et
 * l'interface le dit plutôt que de laisser croire à une panne.
 *
 * L'envoi est silencieux en cas d'échec : une notification perdue ne doit
 * jamais faire échouer l'action qui l'a déclenchée. Un abonnement que le
 * service de push déclare mort est supprimé -- il correspond à un navigateur
 * désinstallé ou à des droits révoqués.
 */
class PushWeb
{
    public function configure(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    /** @param  array<string, mixed>  $charge */
    public function envoyerA(User $destinataire, array $charge): int
    {
        $abonnements = AbonnementPush::where('user_id', $destinataire->id)->get();

        if ($abonnements->isEmpty() || ! $this->configure()) {
            return 0;
        }

        $client = $this->client();
        $envoyes = 0;

        foreach ($abonnements as $abonnement) {
            try {
                $client->queueNotification(
                    Subscription::create([
                        'endpoint' => $abonnement->endpoint,
                        'publicKey' => $abonnement->cle_publique,
                        'authToken' => $abonnement->jeton_auth,
                    ]),
                    json_encode($charge, JSON_UNESCAPED_UNICODE),
                );
                $envoyes++;
            } catch (\Throwable $e) {
                Log::warning('Abonnement push illisible', ['id' => $abonnement->id, 'erreur' => $e->getMessage()]);
            }
        }

        foreach ($client->flush() as $rapport) {
            if ($rapport->isSuccess()) {
                continue;
            }

            $this->traiterEchec($rapport);
            $envoyes--;
        }

        return max(0, $envoyes);
    }

    private function traiterEchec($rapport): void
    {
        $endpoint = $rapport->getRequest()->getUri()->__toString();
        $abonnement = AbonnementPush::where('empreinte', hash('sha256', $endpoint))->first();

        if (! $abonnement) {
            return;
        }

        // 404 et 410 : le navigateur a été désinstallé ou l'autorisation
        // retirée. Garder l'abonnement ne servirait qu'à réessayer en vain.
        if ($rapport->isSubscriptionExpired()) {
            $abonnement->delete();

            return;
        }

        $abonnement->update(['derniere_erreur_at' => now()]);
    }

    private function client(): Client
    {
        return new Client([
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }
}
