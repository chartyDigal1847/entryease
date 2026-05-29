/**
 * entryease.js — Boot script
 *
 * Listens for the centralized "module:ready" event from
 * https://deoris.test/module-bridge.js after portal SSO resolves.
 *
 * SECURITY: This script never reads localStorage/sessionStorage/cookies for
 * identity. The portal-owned bridge keeps the single-use SSO token in memory,
 * exchanges it with the portal, then exposes only the resolved user identity
 * as event.detail.user and window.PORTAL_USER for this iframe runtime.
 *
 * Fires AFTER module-bridge.js — always the second <script> in the blade shell.
 */

if (window.__ENTRYEASE_LOADED__) {
    console.warn('[entryease] Already loaded — skipping.');
} else {
    window.__ENTRYEASE_LOADED__ = true;

    console.log('[entryease] Script loaded.');

    function showInitError(message) {
        console.error('[entryease] Init error:', message);
        var el = document.getElementById('entryease-loader-error');
        if (el) { el.style.display = 'block'; el.textContent = message; }
    }

    function delay(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function isRetryableExchangeError(err) {
        var message = String((err && err.message) || '');
        return message === 'Failed to fetch' ||
            message.indexOf('SSO exchange failed: 419') === 0 ||
            message.indexOf('SSO exchange failed: 429') === 0 ||
            message.indexOf('SSO exchange failed: 5') === 0;
    }

    function bootFromPortalUser(detail) {
        console.log('[entryease] module:ready received.');

        var user = (detail && detail.user) || window.PORTAL_USER || null;
        var token = (detail && detail.token) || window.SSO_TOKEN || null;

        console.log(user && user.id
            ? '[entryease] Hydrating module session from portal user.'
            : '[entryease] Exchanging SSO token with module backend.');

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) { showInitError('CSRF token missing.'); return; }
        if (!token && !(user && user.id)) { showInitError('SSO identity missing.'); return; }

        function redirectAfter(json) {
            if (json && json.redirect) {
                window.location.href = json.redirect;
                return;
            }
            showInitError('SSO succeeded but no redirect provided.');
        }

        if (user && user.id) {
            fetch('/sso/redirect', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    id: user.id,
                    name: user.name || '',
                    email: user.email || '',
                    role: user.role || 'student',
                    embedded: !!(detail && detail.embedded) ? '1' : '0',
                }),
            }).then(function (res) {
                if (!res.ok) throw new Error('SSO session hydration failed: ' + res.status);
                return res.json().catch(function () { throw new Error('Invalid JSON response'); });
            }).then(redirectAfter).catch(function (err) {
                console.error('[entryease] SSO hydration failed:', err);
                showInitError('Authentication failed during exchange.');
            });
            return;
        }

        function exchange(attempt) {
            attempt = attempt || 1;

            return fetch('/sso/exchange', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                token: token,
                embedded: !!(detail && detail.embedded),
            }),
            }).then(function (res) {
                if (!res.ok) throw new Error('SSO exchange failed: ' + res.status);
                return res.json().catch(function () { throw new Error('Invalid JSON response'); });
            }).catch(function (err) {
                if (attempt < 3 && isRetryableExchangeError(err)) {
                    return delay(250 * attempt).then(function () {
                        return exchange(attempt + 1);
                    });
                }

                throw err;
            });
        }

        exchange(1).then(redirectAfter).catch(function (err) {
            console.error('[entryease] SSO exchange failed:', err);
            showInitError('Authentication failed during exchange.');
        });
    }

    // "module:ready" is the only boot signal modules should trust. It is
    // emitted by the portal bridge only after origin-checked token exchange.
    window.addEventListener('module:ready', function (event) {
        bootFromPortalUser(event.detail || {});
    });

    // "module:error" lets the iframe fail closed without top-level redirects
    // that would break the portal shell or strand the user on a module page.
    window.addEventListener('module:error', function (event) {
        var reason = (event.detail && event.detail.error) || 'Unknown error';
        showInitError('Authentication failed: ' + reason);
    });

    // The portal bridge is intentionally loaded before this script. On fast
    // local HTTPS it may resolve SSO before this listener is attached, so boot
    // from the bridge's memory-only ready detail if it already exists.
    if (window.__DEORIS_MODULE_READY_DETAIL__ || (window.PORTAL_USER && window.PORTAL_USER.id)) {
        bootFromPortalUser(window.__DEORIS_MODULE_READY_DETAIL__ || { user: window.PORTAL_USER, embedded: true });
    } else if (window.__DEORIS_MODULE_ERROR_DETAIL__) {
        showInitError('Authentication failed: ' + (window.__DEORIS_MODULE_ERROR_DETAIL__.error || 'Unknown error'));
    }
}
