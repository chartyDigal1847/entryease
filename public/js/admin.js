(function () {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────────────────────
    function p(v) {
        try { return JSON.parse(v); } catch { return null; }
    }

    // ── Charts (dashboard only) ───────────────────────────────────────────────
    function initCharts() {
        var el = document.getElementById('adminDashboardData');
        if (!el) return;
        var score  = p(el.dataset.score || '');
        var distribution = p(el.dataset.distribution || '');
        var pf = document.getElementById('passFailChart');
        var sd = document.getElementById('scoreDistChart');
        if (!window.Chart || !pf || !sd) return;

        // If dataset parsing failed, try to extract numbers from the fallback DOM
        if (!score) {
            try {
                var pfList = pf.parentElement.querySelectorAll('.chart-fallback ul li');
                if (pfList && pfList.length >= 2) {
                    var passed = Number(pfList[0].querySelectorAll('span')[1].textContent.trim()) || 0;
                    var failed = Number(pfList[1].querySelectorAll('span')[1].textContent.trim()) || 0;
                    score = { passed: passed, failed: failed };
                }
            } catch (e) { console.debug('admin.js: could not parse pass/fail fallback', e); }
        }

        if (!distribution) {
            try {
                var distItems = sd.parentElement.querySelectorAll('.chart-fallback ul li');
                if (distItems && distItems.length) {
                    distribution = Array.prototype.slice.call(distItems).map(function (li) {
                        return Number(li.querySelectorAll('span')[1].textContent.trim()) || 0;
                    });
                }
            } catch (e) { console.debug('admin.js: could not parse distribution fallback', e); }
        }

        if (!score || !distribution) return;

        var pfWrapper = pf.parentElement;
        var sdWrapper = sd.parentElement;

        try {
                new Chart(pf.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Passed', 'Failed'],
                        datasets: [{
                            data: [score.passed || 0, score.failed || 0],
                            backgroundColor: ['#27AE60', '#E74C3C'],
                            borderColor: 'white',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#3D2817', font: { size: 12, weight: 'bold' }, padding: 15 },
                            },
                        },
                    },
                });

                new Chart(sd.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['0–9', '10–19', '20–29', '30–39', '40–49', '50–59', '60–69', '70–79', '80–89', '90–100'],
                        datasets: [{
                            label: 'Students',
                            data: distribution,
                            backgroundColor: 'rgba(39,174,96,0.75)',
                            borderColor: '#27AE60',
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#3D2817' },
                                grid: { color: 'rgba(0,0,0,.08)' },
                            },
                            x: {
                                ticks: { color: '#3D2817' },
                                grid: { display: false },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                        },
                    },
                });

            if (pfWrapper) pfWrapper.classList.add('chart-loaded');
            if (sdWrapper) sdWrapper.classList.add('chart-loaded');
        } catch (error) {
            console.error('Admin chart initialization failed:', error);
        }
    }

    // ── Applications filter (monitoring only — no edit) ───────────────────────
    function initApplications() {
        var root = document.getElementById('adminApplications');
        if (!root) return;

        // Status dropdown auto-submits the form on change for quick filtering
        var status = document.getElementById('status-filter');
        if (status) {
            status.addEventListener('change', function () {
                var form = document.getElementById('adminFilterForm');
                if (form) form.submit();
            });
        }
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initCharts();
        initApplications();
    });

})();
