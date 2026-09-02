/**
 * Le service worker de Kelasi.
 *
 * Deux strategies, choisies selon ce que la page doit garantir :
 *
 *   - les fichiers statiques (CSS, JS, polices, icones) sont servis depuis le
 *     cache en priorite. Ils ne changent qu'a chaque deploiement, et le nom du
 *     cache porte la version ;
 *   - les pages sont demandees au reseau d'abord, avec repli sur le cache.
 *     Un avancement de cours perime induirait un doyen en erreur ; mieux vaut
 *     une page un peu plus lente qu'un chiffre faux.
 *
 * Les requetes d'ecriture ne sont jamais mises en cache : la file hors ligne
 * de resources/js/offline.js s'en charge, elle seule sait les rejouer sans
 * creer de doublon.
 */

const VERSION = 'kelasi-v1';
const CACHE_STATIQUE = `${VERSION}-statique`;
const CACHE_PAGES = `${VERSION}-pages`;

const SOCLE = ['/', '/hors-ligne', '/manifest.json'];

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(
        caches
            .open(CACHE_STATIQUE)
            .then((cache) => cache.addAll(SOCLE))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches
            .keys()
            .then((noms) =>
                Promise.all(
                    noms
                        .filter((nom) => !nom.startsWith(VERSION))
                        .map((nom) => caches.delete(nom)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evenement) => {
    const requete = evenement.request;

    if (requete.method !== 'GET') return;

    const url = new URL(requete.url);
    if (url.origin !== self.location.origin) return;

    if (estStatique(url)) {
        evenement.respondWith(cacheDAbord(requete, CACHE_STATIQUE));
        return;
    }

    if (requete.mode === 'navigate') {
        evenement.respondWith(reseauDAbord(requete));
    }
});

function estStatique(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icones/') ||
        url.pathname === '/manifest.json'
    );
}

async function cacheDAbord(requete, nomDuCache) {
    const cache = await caches.open(nomDuCache);
    const enCache = await cache.match(requete);

    if (enCache) return enCache;

    const reponse = await fetch(requete);

    if (reponse.ok) cache.put(requete, reponse.clone());

    return reponse;
}

async function reseauDAbord(requete) {
    const cache = await caches.open(CACHE_PAGES);

    try {
        const reponse = await fetch(requete);

        if (reponse.ok) cache.put(requete, reponse.clone());

        return reponse;
    } catch {
        return (
            (await cache.match(requete)) ??
            (await caches.match('/hors-ligne')) ??
            new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain' } })
        );
    }
}
