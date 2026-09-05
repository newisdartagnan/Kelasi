<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Année académique</h1>
    <p class="mt-1 text-sm text-slate-500">
        Clôturer l'année en cours et ouvrir la suivante.
    </p>

    @if (! $courante)
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
            Aucune année académique ouverte.
        </p>
    @else
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-lg font-semibold">{{ $courante->libelle }}</p>
            <p class="mt-0.5 text-sm text-slate-500">
                Du {{ $courante->date_debut->translatedFormat('j F Y') }}
                au {{ $courante->date_fin->translatedFormat('j F Y') }}
                &middot; {{ $apercu['promotions'] }} promotion(s)
            </p>

            @if ($apercu['seances_en_attente'] > 0)
                {{-- Clôturer alors que des séances attendent leur contreseing
                     les figerait sans qu'elles comptent jamais. --}}
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                    <span class="font-medium">{{ $apercu['seances_en_attente'] }} séance(s)</span>
                    attendent encore un contreseing. La clôture est bloquée tant qu'elles
                    n'ont pas été tranchées : les figer ainsi reviendrait à perdre
                    définitivement les heures correspondantes.
                </p>
            @endif
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-700">
                    Reconduites ({{ $apercu['promotions_reconduites']->count() }})
                </p>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @forelse ($apercu['promotions_reconduites'] as $ligne)
                        <li class="truncate">{{ $ligne['depuis'] }} &rarr; <span class="font-medium">{{ $ligne['vers'] }}</span></li>
                    @empty
                        <li class="text-slate-400">Aucune</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-700">
                    Terminales ({{ $apercu['promotions_terminales']->count() }})
                </p>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @forelse ($apercu['promotions_terminales'] as $intitule)
                        <li class="truncate">{{ $intitule }}</li>
                    @empty
                        <li class="text-slate-400">Aucune</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
            <p class="font-medium">Année suivante</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="libelle" class="block text-sm font-medium text-slate-700">Libellé</label>
                    <input id="libelle" type="text" wire:model="libelle"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('libelle') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="debut" class="block text-sm font-medium text-slate-700">Rentrée</label>
                    <input id="debut" type="date" wire:model="debut"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('debut') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fin" class="block text-sm font-medium text-slate-700">Clôture</label>
                    <input id="fin" type="date" wire:model="fin"
                           class="mt-1 w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-kelasi-500 focus:ring-kelasi-500">
                    @error('fin') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-slate-600">
                Le programme sera recopié tel quel dans les promotions reconduites,
                attributions d'enseignants comprises. Les séances, relevés de présence
                et documents de l'année close restent consultables : rien n'est détruit.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Le passage des étudiants au niveau supérieur ne se fait pas ici : il
                dépend des délibérations. Le secrétariat déposera la nouvelle liste
                d'inscrits, et chacun rejoindra la promotion que le jury lui a assignée.
            </p>

            @if ($confirmation)
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-medium text-rose-900">
                        Clôturer {{ $courante->libelle }} et ouvrir {{ $libelle }} ?
                    </p>
                    <p class="mt-1 text-sm text-rose-800">
                        Cette opération ne s'annule pas depuis l'application.
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="basculer"
                                class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                            Confirmer la clôture
                        </button>
                        <button type="button" wire:click="$set('confirmation', false)"
                                class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">
                            Annuler
                        </button>
                    </div>
                </div>
            @else
                <button type="button" wire:click="$set('confirmation', true)"
                        @disabled($apercu['seances_en_attente'] > 0)
                        class="mt-4 rounded-lg bg-kelasi-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-kelasi-700 disabled:opacity-50">
                    Clôturer et ouvrir l'année suivante
                </button>
            @endif
        </div>
    @endif

    <section class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Historique</h2>
        <ul class="mt-3 space-y-2">
            @foreach ($historique as $annee)
                <li class="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">
                    <span>
                        <span class="font-medium">{{ $annee->libelle }}</span>
                        <span class="text-slate-500">
                            &middot; {{ $annee->promotions_count }} promotion(s)
                        </span>
                    </span>
                    @if ($annee->active)
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            En cours
                        </span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                            Clôturée
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
