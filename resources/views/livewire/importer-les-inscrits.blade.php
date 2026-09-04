<div>
    <h1 class="text-2xl font-semibold tracking-tight">Liste des inscrits</h1>
    <p class="mt-1 text-sm text-slate-500">
        @if ($annee)
            Année académique {{ $annee->libelle }}. Sans ligne dans cette liste, aucun compte ne peut être ouvert.
        @else
            Aucune année académique n'est ouverte.
        @endif
    </p>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <label for="fichier" class="block text-sm font-medium text-slate-700">
            Déposer un fichier CSV
        </label>

        <input
            id="fichier"
            type="file"
            wire:model="fichier"
            accept=".csv,text/csv"
            class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-kelasi-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-kelasi-700 hover:file:bg-kelasi-100"
        >

        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            Colonnes attendues, dans n'importe quel ordre :
            <span class="font-medium text-slate-700">{{ implode(', ', $colonnes) }}</span>.
            Seuls <span class="font-medium text-slate-700">matricule</span> et
            <span class="font-medium text-slate-700">nom</span> sont obligatoires.
            Le point-virgule comme la virgule sont acceptés.
        </p>

        @error('fichier') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror

        <div wire:loading wire:target="fichier" class="mt-3 text-sm text-slate-500">Lecture du fichier...</div>
    </div>

    @if ($erreurs)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-medium text-amber-900">
                {{ count($erreurs) }} ligne(s) ne seront pas importées
            </p>
            <ul class="mt-2 space-y-1 text-sm text-amber-800">
                @foreach (array_slice($erreurs, 0, 15) as $erreur)
                    <li>&middot; {{ $erreur }}</li>
                @endforeach
                @if (count($erreurs) > 15)
                    <li class="text-amber-700">... et {{ count($erreurs) - 15 }} autre(s).</li>
                @endif
            </ul>
        </div>
    @endif

    @if ($apercu && $apercu->isNotEmpty())
        <div class="mt-4 rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <p class="text-sm">
                    <span class="font-semibold text-emerald-700">{{ $retenues }}</span> ligne(s) prêtes à importer
                    @if ($rejetees > 0)
                        &middot; <span class="font-semibold text-amber-700">{{ $rejetees }}</span> rejetée(s)
                    @endif
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        wire:click="annuler"
                        class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        wire:click="confirmer"
                        @disabled($retenues === 0)
                        class="rounded-lg bg-kelasi-600 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-kelasi-700 disabled:opacity-50"
                    >
                        Importer {{ $retenues }} inscription(s)
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2.5 font-medium">Matricule</th>
                            <th class="px-5 py-2.5 font-medium">Nom</th>
                            <th class="px-5 py-2.5 font-medium">Promotion</th>
                            <th class="px-5 py-2.5 font-medium">Rôle</th>
                            <th class="px-5 py-2.5 font-medium">État</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($apercu->take(50) as $ligne)
                            <tr @class(['bg-amber-50/50' => ! $ligne['valide']])>
                                <td class="whitespace-nowrap px-5 py-2.5 font-medium">{{ $ligne['matricule'] }}</td>
                                <td class="px-5 py-2.5">{{ $ligne['nom'] }} {{ $ligne['postnom'] }} {{ $ligne['prenom'] }}</td>
                                <td class="px-5 py-2.5 text-slate-600">{{ $ligne['promotion_libelle'] ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-slate-600">
                                    {{ \App\Models\User::ROLES[$ligne['role']] ?? $ligne['role'] }}
                                </td>
                                <td class="px-5 py-2.5">
                                    @if ($ligne['valide'])
                                        <span class="text-emerald-700">Prête</span>
                                    @else
                                        <span class="text-amber-800">{{ $ligne['motif'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($apercu->count() > 50)
                <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                    Les 50 premières lignes sont affichées ; l'import portera sur les {{ $apercu->count() }}.
                </p>
            @endif
        </div>
    @endif

    @if ($deposees && $deposees->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Inscriptions déposées</h2>

            <ul class="mt-3 space-y-2">
                @foreach ($deposees as $inscription)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">
                        <span>
                            <span class="font-medium">{{ $inscription->matricule }}</span>
                            <span class="text-slate-600">
                                &middot; {{ $inscription->nom }} {{ $inscription->prenom }}
                            </span>
                            @if ($inscription->promotion)
                                <span class="block text-xs text-slate-500">{{ $inscription->promotion->nom_complet }}</span>
                            @endif
                        </span>

                        @if ($inscription->activee_at)
                            <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                Compte ouvert
                            </span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                En attente d'activation
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $deposees->links() }}</div>
        </section>
    @endif
</div>
