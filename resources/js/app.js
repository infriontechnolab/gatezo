// Public pages: Alpine for small interactions. The scanner registers its own component.
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Register the service worker on every public page so the pass and scanner
// shells are cached before the venue Wi-Fi dies.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

// Defer start so scanner.js (loaded after this) can register its component first.
queueMicrotask(() => Alpine.start());
