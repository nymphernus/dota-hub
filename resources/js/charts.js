import {
    ArcElement,
    Chart,
    DoughnutController,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    DoughnutController,
    Tooltip,
);

Chart.defaults.color = '#e2e8f0';
Chart.defaults.borderColor = '#1e293b';
Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";

const payload = document.getElementById('stats-radar-chart-data');

if (payload) {
    const data = JSON.parse(payload.textContent);
    const charts = [];

    const winrateCanvas = document.getElementById('winrateChart');

    if (winrateCanvas) {
        const wins = Number(data.wins) || 0;
        const losses = Number(data.losses) || 0;
        const total = wins + losses;
        const hasResults = total > 0;

        charts.push(new Chart(winrateCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Победы', 'Поражения'],
                datasets: [{
                    data: hasResults ? [wins, losses] : [1],
                    backgroundColor: hasResults ? ['#10b981', '#ef4444'] : ['#1e293b'],
                    borderColor: '#0b1220',
                    borderWidth: 3,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: hasResults,
                        callbacks: {
                            label(context) {
                                const value = context.parsed;
                                const share = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';

                                return ` ${context.label}: ${value} (${share}%)`;
                            },
                        },
                    },
                },
            },
        }));
    }

    const heroUsageCanvas = document.getElementById('heroUsageChart');

    if (heroUsageCanvas) {
        const heroes = Array.isArray(data.heroUsage) ? data.heroUsage : [];
        const total = heroes.reduce((sum, hero) => sum + (Number(hero.games) || 0), 0);

        charts.push(new Chart(heroUsageCanvas, {
            type: 'doughnut',
            data: {
                labels: heroes.map((hero) => hero.name || 'Неизвестный герой'),
                datasets: [{
                    data: heroes.map((hero) => Number(hero.games) || 0),
                    backgroundColor: heroes.map((hero) => hero.color || '#64748b'),
                    borderColor: '#0b1220',
                    borderWidth: 2,
                    hoverOffset: 5,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const hero = heroes[context.dataIndex] || {};
                                const share = total > 0
                                    ? (((Number(hero.games) || 0) / total) * 100).toFixed(1)
                                    : '0.0';
                                const winrate = hero.winrate !== null && hero.winrate !== undefined
                                    ? ` · ${hero.winrate}% WR`
                                    : '';

                                return ` ${context.label}: ${hero.games || 0} игр (${share}%)${winrate}`;
                            },
                        },
                    },
                },
            },
        }));
    }

    // The overview panel is hidden when the page is opened with #heroes/#matches.
    // Chart.js recalculates its canvas size after the Alpine transition finishes.
    const resizeCharts = () => window.setTimeout(() => {
        if (document.getElementById('panel-overview')?.offsetParent !== null) {
            charts.forEach((chart) => chart.resize());
        }
    }, 240);

    document.querySelectorAll('[role="tab"]').forEach((tab) => {
        tab.addEventListener('click', resizeCharts);
    });

    window.addEventListener('hashchange', resizeCharts);
}
