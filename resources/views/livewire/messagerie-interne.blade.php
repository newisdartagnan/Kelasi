<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Messages</h1>
            <p class="mt-1 text-sm text-slate-500">
                Vos échanges avec les personnes que votre fonction vous permet de joindre.
            </p>
        </div>

        <button
            type="button"
            wire:click="ouvrirRecherche"
            class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700"
        >
            Nouveau message
        </button>
    </div>

    @if ($rechercheOuverte)
        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-5">
            <label for="recherche" class="block text-sm font-medium text-slate-700">À qui écrivez-vous ?</label>
            <input
                id="recherche"
                type="search"
                wire:model.live.debounce.300ms="recherche"
                placeholder="Nom, prénom ou matricule"
                class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2.5 shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
            >

            @if ($groupes->isNotEmpty())
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Fils de groupe</p>
                <ul class="mt-1 max-h-52 space-y-1 overflow-y-auto">
                    @foreach ($groupes as $groupe)
                        <li>
                            <button
                                type="button"
                                wire:click="ouvrirGroupe('{{ $groupe['type'] }}', {{ $groupe['cle'] }})"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-slate-100"
                            >
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-kelasi-100 text-sm">
                                    {{ $groupe['type'] === 'promotion' ? '👥' : '📘' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $groupe['libelle'] }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $groupe['detail'] }}</span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($destinataires->isEmpty())
                <p class="mt-3 text-sm text-slate-500">
                    Personne ne correspond dans les interlocuteurs que votre fonction autorise.
                </p>
            @else
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Personnes</p>
                <ul class="mt-3 max-h-72 space-y-1 overflow-y-auto">
                    @foreach ($destinataires as $destinataire)
                        <li>
                            <button
                                type="button"
                                wire:click="ecrireA({{ $destinataire->id }})"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-slate-100"
                            >
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                                    {{ $destinataire->initiales }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $destinataire->nom_complet }}</span>
                                    <span class="block truncate text-xs text-slate-500">
                                        {{ \App\Models\User::ROLES[$destinataire->getRoleNames()->first()] ?? '' }}
                                        &middot; {{ $destinataire->matricule }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button type="button" wire:click="$set('rechercheOuverte', false)"
                    class="mt-3 text-sm text-slate-500 hover:underline">
                Fermer
            </button>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-[20rem_1fr]">
        {{-- Sur téléphone, la liste s'efface dès qu'un fil est ouvert :
             les deux ne tiennent pas côte à côte. --}}
        <aside @class(['rounded-xl border border-slate-200 bg-white', 'hidden md:block' => $conversation])>
            @if ($conversations->isEmpty())
                <p class="px-4 py-12 text-center text-sm text-slate-500">
                    Aucune conversation.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($conversations as $fil)
                        @php($dernier = $fil->messages->first())
                        <li>
                            <button
                                type="button"
                                wire:click="ouvrir({{ $fil->id }})"
                                @class([
                                    'flex w-full items-start gap-3 px-4 py-3 text-left transition',
                                    'bg-kelasi-50' => $conversation?->id === $fil->id,
                                    'hover:bg-slate-50' => $conversation?->id !== $fil->id,
                                ])
                            >
                                <span @class([
                                    'grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-semibold',
                                    'bg-kelasi-100 text-base' => $fil->estDeGroupe(),
                                    'bg-slate-200 text-slate-700' => ! $fil->estDeGroupe(),
                                ])>
                                    {{ $fil->vignettePour(auth()->user()) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">
                                        {{ $fil->titrePour(auth()->user()) }}
                                    </span>
                                    @if ($dernier)
                                        <span class="block truncate text-xs text-slate-500">{{ $dernier->corps }}</span>
                                    @endif
                                </span>
                                @if ($fil->dernier_message_at)
                                    <span class="shrink-0 text-[11px] text-slate-400">
                                        {{ $fil->dernier_message_at->translatedFormat('d/m') }}
                                    </span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        <section @class(['flex min-h-[28rem] flex-col rounded-xl border border-slate-200 bg-white', 'hidden md:flex' => ! $conversation])>
            @if (! $conversation)
                <p class="m-auto px-4 text-center text-sm text-slate-500">
                    Choisissez une conversation, ou écrivez à quelqu'un.
                </p>
            @else
                @php($autre = $conversation->interlocuteur(auth()->user()))

                <header class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
                    <button type="button" wire:click="fermer"
                            class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 md:hidden">
                        &larr;
                    </button>
                    <span @class([
                        'grid h-9 w-9 place-items-center rounded-full text-xs font-semibold',
                        'bg-kelasi-100 text-base' => $conversation->estDeGroupe(),
                        'bg-slate-200 text-slate-700' => ! $conversation->estDeGroupe(),
                    ])>
                        {{ $conversation->vignettePour(auth()->user()) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $conversation->titrePour(auth()->user()) }}</span>
                        <span class="block truncate text-xs text-slate-500">
                            @if ($conversation->estDeGroupe())
                                {{ $conversation->membres->count() }} participants
                            @elseif ($autre)
                                {{ \App\Models\User::ROLES[$autre->getRoleNames()->first()] ?? '' }}
                            @endif
                        </span>
                    </span>
                </header>

                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                    @foreach ($messages as $message)
                        @php($aMoi = $message->auteur_id === auth()->id())
                        <div @class(['flex', 'justify-end' => $aMoi])>
                            <div @class([
                                'max-w-[85%] rounded-2xl px-3.5 py-2',
                                'bg-kelasi-600 text-white' => $aMoi,
                                'bg-slate-100 text-slate-800' => ! $aMoi,
                            ])>
                                @if ($conversation->estDeGroupe() && ! $aMoi)
                                    <p class="text-xs font-semibold text-kelasi-700">{{ $message->auteur->nom_complet }}</p>
                                @endif
                                <p class="whitespace-pre-line text-sm leading-relaxed">{{ $message->corps }}</p>
                                <p @class([
                                    'mt-1 text-[11px]',
                                    'text-white/70' => $aMoi,
                                    'text-slate-500' => ! $aMoi,
                                ])>
                                    {{ $message->created_at->translatedFormat('d/m à H\hi') }}
                                </p>
                            </div>
                        </div>
                    @endforeach

                    @if ($messages->isEmpty())
                        <p class="py-12 text-center text-sm text-slate-500">
                            Aucun message. Écrivez le premier.
                        </p>
                    @endif
                </div>

                <form wire:submit="envoyer" class="flex items-end gap-2 border-t border-slate-200 p-3">
                    <label for="corps" class="sr-only">Message</label>
                    <textarea
                        id="corps"
                        wire:model="corps"
                        rows="1"
                        placeholder="Votre message"
                        class="flex-1 resize-none rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500"
                    ></textarea>
                    <button type="submit"
                            class="shrink-0 rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700">
                        Envoyer
                    </button>
                </form>

                @error('corps') <p class="px-4 pb-3 text-sm text-rose-600">{{ $message }}</p> @enderror
            @endif
        </section>
    </div>
</div>
