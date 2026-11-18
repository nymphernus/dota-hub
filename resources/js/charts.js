import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
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

    const recentMatchesCanvas = document.getElementById('recentMatchesChart');

    if (recentMatchesCanvas) {
        const matches = Array.isArray(data.matches) ? data.matches : [];
        const colors = matches.map((match) => match.win ? '#10b981' : '#ef4444');

        charts.push(new Chart(recentMatchesCanvas, {
            type: 'bar',
            data: {
                labels: matches.map((match) => match.label || '—'),
                datasets: [{
                    label: 'Результат',
                    data: matches.map(() => 1),
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 0,
                    borderRadius: 4,
                    maxBarThickness: 24,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        border: {
                            display: false,
                        },
                        ticks: {
                            color: '#8ba0bd',
                            font: {
                                size: 10,
                            },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10,
                        },
                    },
                    y: {
                        display: false,
                        beginAtZero: true,
                        max: 1.15,
                    },
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            title(items) {
                                const match = matches[items[0]?.dataIndex];

                                return match ? match.label : '';
                            },
                            label(context) {
                                const match = matches[context.dataIndex];

                                return match
                                    ? `${match.win ? 'Победа' : 'Поражение'} · ${match.hero}`
                                    : '';
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
