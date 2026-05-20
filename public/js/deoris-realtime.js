/**
 * EntryEase realtime — Echo subscriptions; notifications go to the header bell only.
 */
(function () {
    'use strict';

    var cfg = window.DEORIS_REALTIME || {};

    function notifyBell(eventName, payload) {
        document.dispatchEvent(
            new CustomEvent('deoris:realtime', {
                detail: { event: eventName, data: payload },
            })
        );
    }

    function bindChannel(channel, events) {
        if (!window.Echo) return;

        var ch = window.Echo.private(channel);

        events.forEach(function (eventName) {
            ch.listen('.' + eventName, function (payload) {
                notifyBell(eventName, payload);
            });
        });
    }

    function subscribe() {
        if (!window.Echo) return;

        if (cfg.admissionsChannel) {
            bindChannel(cfg.admissionsChannel, cfg.admissionEvents || []);
        }

        if (cfg.studentChannel) {
            bindChannel(cfg.studentChannel, cfg.studentEvents || []);
        }
    }

    document.addEventListener('deoris:echo-ready', subscribe);

    if (window.Echo) {
        subscribe();
    }
})();
