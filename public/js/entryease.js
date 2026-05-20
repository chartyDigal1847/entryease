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

    function bootFromPortalUser(detail) {
        console.log('[entryease] module:ready received.');

        var user  = (detail && detail.user) || window.PORTAL_USER || {};
        var role  = user.role  || 'student';
        var name  = user.name  || '';
        var email = user.email || '';
        var id    = user.id    || '';

        console.log('[entryease] User role:', role, '— submitting to Laravel.');

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) { showInitError('CSRF token missing.'); return; }

        // Legacy server-rendered handoff. Role comes from the portal identity
        // event and is clamped server-side. Replace this with direct UI boot
        // as EntryEase screens move fully to memory-only identity.
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/sso/redirect';

        [
            { name: '_token',   value: csrfMeta.getAttribute('content') },
            { name: 'role',     value: role  },
            { name: 'name',     value: name  },
            { name: 'email',    value: email },
            { name: 'id',       value: id    },
            { name: 'embedded', value: (detail && detail.embedded) ? '1' : '0' },
        ].forEach(function (f) {
            var input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = f.name;
            input.value = f.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);

        // If embedded, prefer XHR/Fetch POST so we can consume JSON redirect
        // and update the iframe location without rendering server views.
        if ((detail && detail.embedded) || (window.PORTAL_USER && window.PORTAL_USER.embedded)) {
            var fd = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: fd,
            }).then(function (res) {
                if (!res.ok) throw new Error('SSO POST failed: ' + res.status);
                return res.json().catch(function () { throw new Error('Invalid JSON response'); });
            }).then(function (json) {
                if (json && json.redirect) {
                    // If running inside a portal iframe, navigate the iframe to the dashboard
                    window.location.href = json.redirect;
                    return;
                }
                showInitError('SSO succeeded but no redirect provided.');
            }).catch(function (err) {
                console.error('[entryease] SSO redirect exchange failed:', err);
                showInitError('Authentication failed during handoff.');
            });
        } else {
            form.submit();
        }
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
