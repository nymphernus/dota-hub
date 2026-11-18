import {
    CategoryScale,
    Chart,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    CategoryScale,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

Chart.defaults.color = '#e2e8f0';
Chart.defaults.borderColor = '#1e293b';
Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";

const payload = document.getElementById('stats-radar-match-chart-data');

if (payload) {
    const data = JSON.parse(payload.textContent);
    const compactNumber = new Intl.NumberFormat('ru-RU', {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    const renderNetWorthChart = (canvasId, datasets) => {
        const canvas = document.getElementById(canvasId);

        if (!canvas || ! Array.isArray(datasets) || datasets.length === 0) {
            return null;
        }

        return new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels ?? [],
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                elements: {
                    point: {
                        radius: 0,
                        hoverRadius: 4,
                    },
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.08)',
                        },
                        border: {
                            display: false,
                        },
                        ticks: {
                            color: '#657b99',
                            font: {
                                size: 10,
                            },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                            callback(value, index, ticks) {
                                const minute = data.labels?.[index] ?? value;

                                return `${minute}′`;
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.08)',
                        },
                        border: {
                            display: false,
                        },
                        ticks: {
                            color: '#657b99',
                            font: {
                                size: 10,
                            },
                            callback(value) {
                                return compactNumber.format(value);
                            },
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#8ba0bd',
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                            font: {
                                size: 10,
                            },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            title(items) {
                                return items[0] ? `${items[0].label} минута` : '';
                            },
                            label(context) {
                                return ` ${context.dataset.label}: ${new Intl.NumberFormat('ru-RU').format(context.parsed.y ?? 0)}`;
                            },
                        },
                    },
                },
            },
        });
    };

    const charts = [
        renderNetWorthChart('radiantNetWorthChart', data.radiant?.datasets),
        renderNetWorthChart('direNetWorthChart', data.dire?.datasets),
    ].filter(Boolean);

    window.addEventListener('resize', () => {
        charts.forEach((chart) => chart.resize());
    });
}
