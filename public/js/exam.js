/**
 * exam.js — EntryEase Exam Analytics Charts
 *
 * Renders pass/fail doughnut and score distribution bar chart
 * on the score-analytics page using Chart.js.
 *
 * Reads data from #analyticsData data attributes injected by Blade.
 */

(function () {
    'use strict';

    var dataEl = document.getElementById('analyticsData');
    if (!dataEl) return;

    var passed    = parseInt(dataEl.dataset.passed, 10)  || 0;
    var failed    = parseInt(dataEl.dataset.failed, 10)  || 0;
    var scores    = JSON.parse(dataEl.dataset.scores     || '[]');

    // ── Pass / Fail doughnut ──────────────────────────────────────────────────
    var passFailCtx = document.getElementById('passFailChart');
    if (passFailCtx) {
        new Chart(passFailCtx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed'],
                datasets: [{
                    data: [passed, failed],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderColor: ['#fff', '#fff'],
                    borderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 13 }, padding: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = passed + failed;
                                var pct   = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '65%',
            }
        });
    }

    // ── Score distribution bar chart ──────────────────────────────────────────
    var distCtx = document.getElementById('distributionChart');
    if (distCtx && scores.length > 0) {
        // Bucket into 10% ranges: 0-9, 10-19, ..., 90-100
        var buckets = ['0–9', '10–19', '20–29', '30–39', '40–49',
                       '50–59', '60–69', '70–79', '80–89', '90–100'];
        var counts  = new Array(10).fill(0);

        scores.forEach(function (pct) {
            var idx = Math.min(Math.floor(pct / 10), 9);
            counts[idx]++;
        });

        // Colour bars: green if bucket midpoint >= 75, red otherwise
        var colours = buckets.map(function (_, i) {
            return (i * 10 + 5) >= 75 ? 'rgba(34,197,94,.75)' : 'rgba(239,68,68,.65)';
        });

        new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: buckets,
                datasets: [{
                    label: 'Number of Students',
                    data: counts,
                    backgroundColor: colours,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) { return 'Score range: ' + items[0].label + '%'; },
                            label: function (ctx) { return ' ' + ctx.parsed.y + ' student(s)'; }
                        }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Score Range (%)', font: { size: 12 } },
                        grid: { display: false }
                    },
                    y: {
                        title: { display: true, text: 'Students', font: { size: 12 } },
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }

})();
