<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Le rappel du matin.
 *
 * Une seule notification par personne et par jour, qui rassemble ce qui la
 * concerne. Une par séance en attente noierait le destinataire et il
 * cesserait de les lire — ce qui reviendrait à n'en envoyer aucune.
 *
 * La notification n'est pas envoyée si elle n'a rien à dire : c'est le
 * service qui décide, pas le destinataire qui filtre.
 */
class RappelQuotidien extends Notification
{
    use Queueable;

    /** @param  list<array{titre: string, detail: string, route: string}>  $points */
    public function __construct(
        public readonly array $points,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rappel_quotidien',
            'titre' => $this->titre(),
            'points' => $this->points,
        ];
    }

    /** La charge envoyée au navigateur. */
    public function toPush(): array
    {
        return [
            'titre' => $this->titre(),
            'corps' => collect($this->points)->pluck('detail')->implode(' · '),
            'url' => $this->points[0]['route'] ?? '/',
        ];
    }

    private function titre(): string
    {
        return count($this->points) === 1
            ? $this->points[0]['titre']
            : 'Kelasi — '.count($this->points).' points du jour';
    }
}
