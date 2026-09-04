<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
            <p class="mt-1 text-sm text-slate-500">Les rappels qui vous ont été adressés.</p>
        </div>

        @if ($notifications->isNotEmpty())
            <button type="button" wire:click="marquerToutLu"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Tout marquer comme lu
            </button>
        @endif
    </div>

    {{-- Le réglage du push. L'état de l'appareil décide du message affiché :
         proposer un bouton qui échouerait sur iOS ne rendrait service à personne. --}}
    <div
        x-data="reglagePush(@js($clePublique), @js($pushDisponible))"
        x-cloak
        class="mt-6 rounded-xl border border-slate-200 bg-white p-5"
    >
        <p class="font-medium">Notifications sur cet appareil</p>

        <template x-if="!disponible">
            <p class="mt-2 text-sm text-slate-600">
                Le push n'est pas configuré sur ce serveur. Les rappels restent consultables sur cette page.
            </p>
        </template>

        <template x-if="disponible && etat === 'ios-a-installer'">
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Sur iPhone et iPad, les notifications ne fonctionnent qu'une fois Kelasi ajouté à l'écran
                d'accueil : touchez <span class="font-medium">Partager</span>, puis
                <span class="font-medium">Sur l'écran d'accueil</span>, et rouvrez l'application depuis l'icône.
            </p>
        </template>

        <template x-if="disponible && etat === 'refuse'">
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Vous avez refusé les notifications pour ce site. Il faut les réautoriser dans les réglages
                du navigateur pour les recevoir.
            </p>
        </template>

        <template x-if="disponible && etat === 'indisponible'">
            <p class="mt-2 text-sm text-slate-600">
                Ce navigateur ne prend pas en charge les notifications poussées.
            </p>
        </template>

        <template x-if="disponible && (etat === 'a-demander' || etat === 'accorde')">
            <div class="mt-3">
                <button
                    type="button"
                    x-show="etat !== 'accorde' || !abonne"
                    x-on:click="activer()"
                    x-bind:disabled="occupe"
                    class="rounded-lg bg-kelasi-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-kelasi-700 disabled:opacity-60"
                >
                    <span x-show="!occupe">Recevoir les rappels sur cet appareil</span>
                    <span x-show="occupe">Activation...</span>
                </button>

                <div x-show="etat === 'accorde' && abonne" class="flex flex-wrap items-center gap-3">
                    <p class="text-sm text-emerald-700">Les rappels arrivent sur cet appareil.</p>
                    <button type="button" x-on:click="desactiver()"
                            class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                        Ne plus recevoir
                    </button>
                </div>

                <p x-show="erreur" x-text="erreur" class="mt-2 text-sm text-rose-600"></p>
            </div>
        </template>
    </div>

    @if ($notifications->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune notification.
        </p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($notifications as $notification)
                @php($donnees = $notification->data)
                <li @class([
                    'rounded-xl border bg-white p-4',
                    'border-kelasi-200 bg-kelasi-50/40' => $notification->unread(),
                    'border-slate-200' => ! $notification->unread(),
                ])>
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="font-medium">{{ $donnees['titre'] ?? 'Rappel' }}</p>
                        <span class="shrink-0 text-xs text-slate-400">
                            {{ $notification->created_at->translatedFormat('d/m à H\hi') }}
                        </span>
                    </div>

                    @foreach ($donnees['points'] ?? [] as $point)
                        <a href="{{ $point['route'] ?? '/' }}"
                           class="mt-2 block rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                            <span class="font-medium text-slate-800">{{ $point['titre'] }}</span>
                            <span class="block text-slate-600">{{ $point['detail'] }}</span>
                        </a>
                    @endforeach

                    <button type="button" wire:click="supprimer('{{ $notification->id }}')"
                            class="mt-2 text-xs text-slate-400 hover:underline">
                        Supprimer
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<script>
    function reglagePush(clePublique, disponible) {
        return {
            disponible,
            etat: 'indisponible',
            abonne: false,
            occupe: false,
            erreur: '',

            async init() {
                this.etat = window.KelasiPush.etatDuPush();
                this.abonne = await this.dejaAbonne();
            },

            async dejaAbonne() {
                if (!('serviceWorker' in navigator)) return false;

                const worker = await navigator.serviceWorker.ready;
                return (await worker.pushManager.getSubscription()) !== null;
            },

            async activer() {
                this.occupe = true;
                this.erreur = '';

                const resultat = await window.KelasiPush.activerLePush(clePublique);

                this.occupe = false;
                this.etat = window.KelasiPush.etatDuPush();
                this.abonne = resultat.ok;

                if (!resultat.ok) {
                    this.erreur = resultat.raison === 'refuse'
                        ? "L'autorisation a été refusée."
                        : "L'abonnement n'a pas pu être enregistré.";
                }
            },

            async desactiver() {
                await window.KelasiPush.desactiverLePush();
                this.abonne = false;
            },
        };
    }
</script>
