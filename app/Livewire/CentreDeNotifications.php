<?php

namespace App\Livewire;

use App\Services\PushWeb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Les notifications reçues, et le réglage du push.
 */
class CentreDeNotifications extends Component
{
    public function marquerToutLu(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function supprimer(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->delete();
    }

    public function render(PushWeb $push): View
    {
        return view('livewire.centre-de-notifications', [
            'notifications' => $this->recentes(),
            'clePublique' => config('services.webpush.public_key'),
            'pushDisponible' => $push->configure(),
        ]);
    }

    /** @return Collection<int, \Illuminate\Notifications\DatabaseNotification> */
    private function recentes(): Collection
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get();
    }
}
