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
Chart.defaults.borderColor = '#1b1f2e';
Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";

const HERO_SECTOR_COLOR = '#1e293b';
const TOP_HERO_SECTOR_COLOR = '#475569';

const payload = document.getElementById('dotahub-chart-data');

if (payload) {
    const data = JSON.parse(payload.textContent);
    const heroes = Array.isArray(data.heroUsage) ? data.heroUsage : [];
    const totalGames = heroes.reduce((sum, hero) => sum + (Number(hero.games) || 0), 0);
    const canvas = document.getElementById('heroUsageChart');

    if (canvas) {
        const parent = canvas.parentElement;
        const tooltipElement = document.createElement('div');
        tooltipElement.className = 'dotahub-hero-tooltip pointer-events-none absolute z-[9999] hidden w-52 rounded-lg bg-ink-950/95 p-2.5 shadow-2xl backdrop-blur';
        parent.appendChild(tooltipElement);

        const imageCache = new Map();
        let chart = null;

        const heroImage = (src) => {
            if (!src) return null;

            if (!imageCache.has(src)) {
                const image = new Image();
                image.onload = () => chart?.update('none');
                image.src = src;
                imageCache.set(src, image);
            }

            return imageCache.get(src);
        };

        /**
         * Icons are redrawn on every animation frame. Chart.js moves the whole
         * slice on hover through the animated arc.x / arc.y offset and updates
         * startAngle / endAngle, so reading those live values keeps each icon
         * glued to the centre of its (possibly expanding) sector.
         */
        const heroIcons = {
            id: 'heroIcons',

            afterDatasetsDraw(instance) {
                const meta = instance.getDatasetMeta(0);

                if (!meta?.data) return;

                const maxGames = Math.max(...heroes.map((hero) => Number(hero.games) || 0), 1);
                const chartCentreX = (instance.chartArea.left + instance.chartArea.right) / 2;
                const chartCentreY = (instance.chartArea.top + instance.chartArea.bottom) / 2;

                meta.data.forEach((arc, index) => {
                    const hero = heroes[index] || {};
                    const games = Number(hero.games) || 0;
                    const source = hero.icon || hero.image;
                    const image = heroImage(source);

                    if (!image?.complete || !image.naturalWidth || games <= 0) return;

                    // Live, animated geometry of the slice.
                    const startAngle = arc.startAngle;
                    const endAngle = arc.endAngle;
                    const middleAngle = (startAngle + endAngle) / 2;
                    const centreX = Number.isFinite(arc.x) ? arc.x : chartCentreX;
                    const centreY = Number.isFinite(arc.y) ? arc.y : chartCentreY;
                    const radius = arc.outerRadius;
                    const band = radius - arc.innerRadius;
                    const middleRadius = (arc.innerRadius + radius) / 2;
                    const hoverOffset = Number(arc.options?.offset ?? arc.offset ?? 0);
                    // Chart.js translates the drawn arc by hoverOffset / 4.
                    const offset = hoverOffset / 4;
                    const iconRadius = middleRadius + offset;
                    const arcLength = Math.max((endAngle - startAngle) * middleRadius, 1);

                    // Expose the current geometry so the animation can be
                    // verified from the DOM (and so hover redraws are observable).
                    if (index === 0) {
                        instance.canvas.dataset.iconAngle = middleAngle.toFixed(5);
                        instance.canvas.dataset.iconCentreX = centreX.toFixed(2);
                        instance.canvas.dataset.iconCentreY = centreY.toFixed(2);
                        instance.canvas.dataset.iconRadius = radius.toFixed(2);
                        instance.canvas.dataset.iconOffset = offset.toFixed(2);
                        instance.canvas.dataset.hoverOffset = hoverOffset.toFixed(2);
                    }

                    // Square minimap-style icons sized to the played share but
                    // always clamped inside the doughnut band and sector width.
                    const size = Math.max(0, Math.min(
                        radius * (games / maxGames),
                        band - 8,
                        arcLength - 4,
                    ));

                    if (size < 6) return;

                    const x = centreX + Math.cos(middleAngle) * iconRadius;
                    const y = centreY + Math.sin(middleAngle) * iconRadius;

                    instance.ctx.save();
                    instance.ctx.drawImage(
                        image,
                        x - (size / 2),
                        y - (size / 2),
                        size,
                        size,
                    );
                    instance.ctx.restore();
                });
            },

            // Hover changes sometimes settle without a fresh animation frame;
            // a direct draw keeps the icons in sync with the highlighted arc.
            afterEvent(instance, args) {
                const type = args?.event?.type;

                if (type === 'mousemove' || type === 'mouseout' || type === 'click') {
                    instance.draw();
                }
            },
        };

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

        chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: heroes.map((hero) => hero.name || 'Неизвестный герой'),
                datasets: [{
                    data: heroes.map((hero) => Number(hero.games) || 0),
                    backgroundColor: heroes.map((hero, index) => index < 3
                        ? TOP_HERO_SECTOR_COLOR
                        : HERO_SECTOR_COLOR),
                    borderColor: '#0f111a',
                    borderWidth: 2,
                    hoverOffset: 14,
                }],
            },
            plugins: [heroIcons],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '40%',
                animation: {
                    duration: 450,
                },
                transitions: {
                    active: {
                        animation: {
                            duration: 220,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        zIndex: 9999,
                        enabled: false,
                        external: renderHeroTooltip,
                    },
                },
            },
        });

        window.setTimeout(() => chart.resize(), 240);
    }
}

/**
 * Activity heatmap. Native Date is the single source of truth for weekday and
 * month placement: rows are Monday..Sunday, columns are consecutive weeks and
 * a month label is attached to the first column that contains that month.
 */
const heatmapPayload = document.getElementById('dotahub-heatmap-data');
const heatmapRoot = document.getElementById('activity-heatmap');

if (heatmapPayload && heatmapRoot) {
    const heatmap = JSON.parse(heatmapPayload.textContent);
    const weekdaysNode = document.getElementById('heatmap-weekdays');
    const monthsNode = document.getElementById('heatmap-months');
    const totalNode = document.getElementById('activity-total');
    const weekdayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const monthLabels = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сент', 'Окт', 'Ноя', 'Дек'];
    const tones = {
        future: 'bg-transparent text-transparent',
        empty: 'bg-white/[0.04] ring-1 ring-inset ring-white/[0.03] text-transparent',
        win: 'bg-emerald-500 ring-1 ring-inset ring-emerald-300/40 text-emerald-950',
        loss: 'bg-rose-500 ring-1 ring-inset ring-rose-300/40 text-rose-950',
        loss_majority: 'bg-orange-500 ring-1 ring-inset ring-orange-300/40 text-orange-950',
        win_majority: 'bg-yellow-400 ring-1 ring-inset ring-yellow-200/50 text-yellow-950',
        mixed: 'bg-amber-400 ring-1 ring-inset ring-amber-200/50 text-amber-950',
    };
    const DAY_MS = 86400000;

    const parseDay = (value) => {
        const [year, month, day] = String(value).split('-').map(Number);
        return new Date(Date.UTC(year, month - 1, day));
    };
    const weekdayOf = (date) => (date.getUTCDay() + 6) % 7;
    const days = (Array.isArray(heatmap.weeks) ? heatmap.weeks.flat() : [])
        .filter((day) => day && day.date)
        .sort((left, right) => left.date.localeCompare(right.date));

    if (days.length > 0) {
        const firstDate = parseDay(days[0].date);
        const gridStart = new Date(firstDate);
        gridStart.setUTCDate(firstDate.getUTCDate() - weekdayOf(firstDate));

        const lastDate = parseDay(days[days.length - 1].date);
        const columnCount = Math.floor(Math.round((lastDate - gridStart) / DAY_MS) / 7) + 1;
        const columns = Array.from({ length: columnCount }, () => Array(7).fill(null));

        days.forEach((day) => {
            const date = parseDay(day.date);
            const offset = Math.round((date - gridStart) / DAY_MS);
            const column = Math.floor(offset / 7);
            const row = weekdayOf(date);

            if (column >= 0 && column < columnCount && row >= 0 && row < 7) {
                columns[column][row] = day;
            }
        });

        if (weekdaysNode) {
            weekdaysNode.replaceChildren(...weekdayLabels.map((label) => {
                const span = document.createElement('span');
                span.className = 'flex items-center justify-end';
                span.textContent = label;
                return span;
            }));
        }

        if (monthsNode) {
            monthsNode.style.gridTemplateColumns = `repeat(${columnCount}, minmax(0, 1fr))`;
            let previousMonth = -1;

            columns.forEach((column, index) => {
                const date = new Date(gridStart);
                date.setUTCDate(gridStart.getUTCDate() + index * 7);
                const month = date.getUTCMonth();

                if (month === previousMonth) return;
                previousMonth = month;

                const label = document.createElement('span');
                label.className = 'whitespace-nowrap';
                label.style.gridColumn = String(index + 1);
                label.textContent = monthLabels[month];
                monthsNode.appendChild(label);
            });
        }

        const cells = [];

        columns.forEach((column) => {
            column.forEach((day, row) => {
                if (! day) {
                    const empty = document.createElement('span');
                    empty.className = 'aspect-square rounded-[2px] bg-white/[0.04] ring-1 ring-inset ring-white/[0.03]';
                    empty.setAttribute('aria-hidden', 'true');
                    cells.push(empty);
                    return;
                }

                const count = Number(day.count) || 0;
                const title = `${day.label}: ${count} игр, ${Number(day.wins) || 0} побед, ${Number(day.losses) || 0} поражений`;
                const cell = document.createElement('div');
                cell.className = `flex aspect-square items-center justify-center rounded-[2px] text-[11px] font-black leading-none transition hover:scale-125 hover:ring-white/30 ${tones[day.level] || tones.empty}`;
                cell.dataset.date = day.date;
                cell.dataset.count = String(count);
                cell.dataset.weekday = weekdayLabels[row];
                cell.setAttribute('title', title);
                cell.setAttribute('aria-label', title);
                cell.textContent = count > 0 ? String(count) : '';
                cells.push(cell);
            });
        });

        heatmapRoot.style.gridTemplateColumns = `repeat(${columnCount}, minmax(0, 1fr))`;
        heatmapRoot.style.gridAutoFlow = 'column';
        heatmapRoot.style.gridTemplateRows = 'repeat(7, minmax(0, 1fr))';
        heatmapRoot.replaceChildren(...cells);

        if (totalNode) {
            totalNode.textContent = `${days.reduce((sum, day) => sum + (Number(day.count) || 0), 0).toLocaleString('ru-RU')} игр`;
        }
    }
}
