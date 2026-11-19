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
    const heroes = Array.isArray(data.heroUsage) ? data.heroUsage : [];
    const totalGames = heroes.reduce((sum, hero) => sum + (Number(hero.games) || 0), 0);
    const canvas = document.getElementById('heroUsageChart');

    if (canvas) {
        const parent = canvas.parentElement;
        const tooltipElement = document.createElement('div');
        tooltipElement.className = 'radar-hero-tooltip pointer-events-none absolute z-20 hidden w-52 rounded-xl border border-white/10 bg-ink-950/95 p-2.5 shadow-2xl backdrop-blur';
        parent.appendChild(tooltipElement);

        const renderHeroTooltip = (context) => {
            const tooltip = context.tooltip;

            if (tooltip.opacity === 0 || ! tooltip.dataPoints?.length) {
                tooltipElement.classList.add('hidden');
                return;
            }

            const hero = heroes[tooltip.dataPoints[0].dataIndex] || {};
            const games = Number(hero.games) || 0;
            const winrate = hero.winrate === null || hero.winrate === undefined
                ? '—'
                : `${hero.winrate}%`;
            const share = totalGames > 0 ? ((games / totalGames) * 100).toFixed(1) : '0.0';

            tooltipElement.replaceChildren();

            const header = document.createElement('div');
            header.className = 'flex items-center gap-2.5';

            const image = document.createElement('img');
            image.className = 'h-8 w-14 shrink-0 rounded object-cover ring-1 ring-white/10';
            image.alt = hero.name || '';
            if (hero.image) image.src = hero.image;
            header.appendChild(image);

            const title = document.createElement('div');
            title.className = 'min-w-0';
            const name = document.createElement('p');
            name.className = 'truncate text-xs font-bold text-white';
            name.textContent = hero.name || 'Неизвестный герой';
            const subtitle = document.createElement('p');
            subtitle.className = 'text-[10px] text-mist-500';
            subtitle.textContent = `${games} игр · ${share}%`;
            title.append(name, subtitle);
            header.appendChild(title);
            tooltipElement.appendChild(header);

            const footer = document.createElement('div');
            footer.className = 'mt-2 flex items-center justify-between border-t border-white/[0.06] pt-2 text-[11px]';
            const label = document.createElement('span');
            label.className = 'text-mist-500';
            label.textContent = 'Винрейт';
            const value = document.createElement('span');
            value.className = 'font-mono font-bold text-emerald-300';
            value.textContent = winrate;
            footer.append(label, value);
            tooltipElement.appendChild(footer);

            tooltipElement.style.left = `${tooltip.caretX}px`;
            tooltipElement.style.top = `${tooltip.caretY}px`;
            tooltipElement.classList.remove('hidden');
        };

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: heroes.map((hero) => hero.name || 'Неизвестный герой'),
                datasets: [{
                    data: heroes.map((hero) => Number(hero.games) || 0),
                    backgroundColor: heroes.map((hero) => hero.color || '#64748b'),
                    borderColor: '#0b1220',
                    borderWidth: 2,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: false,
                        external: renderHeroTooltip,
                    },
                },
            },
        });

        window.setTimeout(() => chart.resize(), 240);
    }
}
