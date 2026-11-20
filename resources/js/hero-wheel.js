import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

const imageCache = new Map();

const getImage = (src, onLoad) => {
    if (!src) {
        return null;
    }

    const cached = imageCache.get(src);
    if (cached) {
        if (cached.loaded && onLoad) {
            // already loaded
        }
        return cached;
    }

    const img = new Image();
    const entry = { img, loaded: false };
    img.onload = () => {
        entry.loaded = true;
        if (typeof onLoad === 'function') {
            onLoad();
        }
    };
    img.onerror = () => {
        entry.loaded = false;
    };
    img.src = src;
    imageCache.set(src, entry);

    return entry;
};

const heroImagesPlugin = {
    id: 'heroImages',
    afterDatasetsDraw(chart, _args, pluginOptions) {
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) {
            return;
        }

        const heroes = (pluginOptions && pluginOptions.heroes) || [];

        meta.data.forEach((arc, index) => {
            const hero = heroes[index];
            if (!hero) {
                return;
            }

            const src = hero.icon || hero.image;
            if (!src) {
                return;
            }

            const cached = imageCache.get(src);
            if (!cached || !cached.loaded) {
                // ensure image is requested and chart will redraw when ready
                getImage(src, () => chart.draw());
                return;
            }

            const props = arc.getProps(['x', 'y', 'startAngle', 'endAngle', 'innerRadius', 'outerRadius'], true);
            const angle = (props.startAngle + props.endAngle) / 2;
            const radius = (props.innerRadius + props.outerRadius) / 2;
            const x = props.x + Math.cos(angle) * radius;
            const y = props.y + Math.sin(angle) * radius;

            const arcSpan = Math.max(0, props.endAngle - props.startAngle);
            const ring = Math.max(0, props.outerRadius - props.innerRadius);
            const size = Math.min(ring - 6, arcSpan * radius * 0.55, 30);

            if (size < 8) {
                return;
            }

            ctx.save();
            ctx.beginPath();
            ctx.arc(x, y, size / 2, 0, Math.PI * 2);
            ctx.closePath();
            ctx.clip();
            ctx.drawImage(cached.img, x - size / 2, y - size / 2, size, size);
            ctx.restore();
        });
    },
};

const positionTooltip = (chart, tooltipEl, event) => {
    const native = event.native || event;
    const offset = 12;
    let left = (native.clientX ?? 0) + offset;
    let top = (native.clientY ?? 0) + offset;

    tooltipEl.style.left = `${left}px`;
    tooltipEl.style.top = `${top}px`;

    // keep within viewport
    const rect = tooltipEl.getBoundingClientRect();
    if (rect.right > window.innerWidth - 8) {
        left = Math.max(8, (native.clientX ?? 0) - rect.width - offset);
    }
    if (rect.bottom > window.innerHeight - 8) {
        top = Math.max(8, (native.clientY ?? 0) - rect.height - offset);
    }
    tooltipEl.style.left = `${left}px`;
    tooltipEl.style.top = `${top}px`;
};

const htmlTooltipPlugin = {
    id: 'heroHtmlTooltip',
    afterEvent(chart, args, pluginOptions) {
        const { event } = args;
        const tooltipEl = document.getElementById('hero-tooltip');
        if (!tooltipEl) {
            return;
        }

        const heroes = (pluginOptions && pluginOptions.heroes) || [];

        if (event.type === 'mouseout') {
            tooltipEl.classList.add('hidden');
            tooltipEl.classList.remove('flex');
            return;
        }

        if (event.type !== 'mousemove' && event.type !== 'click') {
            return;
        }

        const elements = chart.getElementsAtEventForMode(
            event,
            'nearest',
            { intersect: true },
            true
        );

        if (!elements.length) {
            tooltipEl.classList.add('hidden');
            tooltipEl.classList.remove('flex');
            return;
        }

        const index = elements[0].index;
        const hero = heroes[index];
        if (!hero) {
            tooltipEl.classList.add('hidden');
            tooltipEl.classList.remove('flex');
            return;
        }

        const imgSrc = hero.image || hero.icon || '';
        const winrate = Number(hero.winrate || 0).toFixed(1).replace(/\.0$/, '');
        const imgHtml = imgSrc
            ? `<img src="${imgSrc}" class="w-16 h-9 object-cover rounded shadow" alt="">`
            : '';
        tooltipEl.innerHTML = `${imgHtml}<div><div class="font-bold text-mist-200 text-sm leading-tight">${hero.name ?? 'Герой'}</div><div class="text-xs text-mist-400 mt-0.5">${hero.games} игр, ${winrate}% побед</div></div>`;

        tooltipEl.classList.remove('hidden');
        tooltipEl.classList.add('flex');
        positionTooltip(chart, tooltipEl, event);
    },
};

const initHeroWheelChart = () => {
    const canvas = document.getElementById('heroWheelChart');
    const dataNode = document.getElementById('hero-wheel-chart-data');
    if (!canvas || !dataNode) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch {
        return;
    }

    const heroes = Array.isArray(payload.heroes) ? payload.heroes : [];
    if (heroes.length === 0) {
        return;
    }

    const heroNames = heroes.map((h) => h.name);
    const heroGames = heroes.map((h) => h.games);
    const heroColors = heroes.map((h) => h.color || '#2d313e');

    // Preload icons/images
    heroes.forEach((hero) => {
        const src = hero.icon || hero.image;
        if (src) {
            getImage(src);
        }
    });

    const config = {
        type: 'doughnut',
        data: {
            labels: heroNames,
            datasets: [{
                data: heroGames,
                backgroundColor: heroColors,
                borderColor: '#1c1e26',
                borderWidth: 2,
                hoverOffset: 15,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '40%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
                heroImages: { heroes },
                heroHtmlTooltip: { heroes },
            },
            onClick: (_event, elements) => {
                if (!elements.length) {
                    return;
                }
                const hero = heroes[elements[0].index];
                if (hero && hero.url) {
                    window.location.href = hero.url;
                }
            },
            onHover: (event, elements) => {
                const target = event.native?.target || canvas;
                if (target) {
                    target.style.cursor = elements.length ? 'pointer' : 'default';
                }
            },
        },
        plugins: [heroImagesPlugin, htmlTooltipPlugin],
    };

    // eslint-disable-next-line no-new
    new Chart(canvas, config);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroWheelChart);
} else {
    initHeroWheelChart();
}
