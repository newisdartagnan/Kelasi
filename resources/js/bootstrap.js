// Alpine arrive avec Livewire ; on ne le charge pas une seconde fois.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Un service worker indisponible ne doit jamais empecher l'application
            // de s'afficher. On degrade en mode connecte, sans bruit.
        });
    });
}
