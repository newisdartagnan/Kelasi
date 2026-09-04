<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messagerie;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La messagerie : la liste des fils à gauche, le fil ouvert à droite.
 *
 * Sur téléphone, les deux ne tiennent pas ensemble : la liste laisse la place
 * au fil dès qu'on en ouvre un, et un bouton ramène en arrière.
 */
class MessagerieInterne extends Component
{
    #[Url(as: 'fil')]
    public ?int $conversationId = null;

    public string $corps = '';

    public bool $rechercheOuverte = false;

    public string $recherche = '';

    public function ouvrir(int $conversationId, Messagerie $messagerie): void
    {
        $conversation = Conversation::find($conversationId);

        if (! $conversation?->compte(auth()->user())) {
            return;
        }

        $this->conversationId = $conversation->id;
        $this->rechercheOuverte = false;
        $messagerie->marquerLu(auth()->user(), $conversation);
    }

    public function fermer(): void
    {
        $this->reset(['conversationId', 'corps']);
    }

    public function ouvrirRecherche(): void
    {
        $this->rechercheOuverte = true;
        $this->recherche = '';
    }

    public function ecrireA(int $destinataireId, Messagerie $messagerie): void
    {
        $destinataire = User::find($destinataireId);

        if (! $destinataire) {
            return;
        }

        try {
            $conversation = $messagerie->ouvrirAvec(auth()->user(), $destinataire);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());

            return;
        }

        $this->conversationId = $conversation->id;
        $this->rechercheOuverte = false;
        $this->recherche = '';
    }

    public function envoyer(Messagerie $messagerie): void
    {
        $this->validate(
            ['corps' => ['required', 'string', 'min:1', 'max:4000']],
            attributes: ['corps' => 'message'],
        );

        $conversation = Conversation::find($this->conversationId);

        if (! $conversation) {
            return;
        }

        try {
            $messagerie->envoyer(auth()->user(), $conversation, $this->corps);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('corps');
    }

    public function render(Messagerie $messagerie): View
    {
        $utilisateur = auth()->user();
        $conversation = $this->conversationOuverte();

        if ($conversation) {
            $messagerie->marquerLu($utilisateur, $conversation);
        }

        return view('livewire.messagerie-interne', [
            'conversations' => $this->fils($utilisateur),
            'conversation' => $conversation,
            'messages' => $conversation
                ? $conversation->messages()->with('auteur')->oldest()->limit(200)->get()
                : collect(),
            'destinataires' => $this->rechercheOuverte
                ? $messagerie->destinatairesPossibles($utilisateur, $this->recherche)
                : collect(),
        ]);
    }

    private function conversationOuverte(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        $conversation = Conversation::with('membres')->find($this->conversationId);

        return $conversation?->compte(auth()->user()) ? $conversation : null;
    }

    /** @return Collection<int, Conversation> */
    private function fils(User $utilisateur): Collection
    {
        return Conversation::with(['membres', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->de($utilisateur)
            ->orderByDesc('dernier_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}
