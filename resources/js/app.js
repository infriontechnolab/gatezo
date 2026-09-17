// Public pages: Alpine for small interactions. The scanner registers its own component.
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Register the service worker on every public page so the pass and scanner
// shells are cached before the venue Wi-Fi dies.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

// Start Alpine only after every deferred module (scanner.js registers its component
// in one of them) has run. A microtask here would fire too early.
document.addEventListener('DOMContentLoaded', () => Alpine.start());
