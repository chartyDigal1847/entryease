/**
 * DEORIS realtime — Laravel Echo bootstrap for EntryEase.
 * Requires window.DEORIS_BROADCAST config from Blade layout.
 */
(function () {
    'use strict';

    if (!window.DEORIS_BROADCAST || !window.DEORIS_BROADCAST.enabled) {
        return;
    }

    var cfg = window.DEORIS_BROADCAST;

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function initEcho() {
        if (typeof window.Pusher === 'undefined' || typeof window.Echo === 'undefined') {
            console.warn('[DEORIS] Echo or Pusher not loaded — realtime disabled.');
            return;
        }

        window.Pusher.logToConsole = cfg.debug === true;

        window.Echo = new window.Echo({
            broadcaster: 'pusher',
            key: cfg.key,
            cluster: cfg.cluster || 'mt1',
            wsHost: cfg.host,
            wsPort: cfg.port,
            wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https',
            encrypted: cfg.scheme === 'https',
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: cfg.authEndpoint,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            },
        });

        document.dispatchEvent(new CustomEvent('deoris:echo-ready', { detail: { echo: window.Echo } }));
    }

    Promise.all([
        loadScript('https://js.pusher.com/8.4.0-rc2/pusher.min.js'),
        loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js'),
    ])
        .then(initEcho)
        .catch(function (err) {
            console.warn('[DEORIS] Failed to load Echo scripts', err);
        });
})();
