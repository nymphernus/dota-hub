import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;

const TAB_STORAGE_KEY = 'dotahub.tabs.v1';

/**
 * Browser-like tabs. The current page is injected by the layout as JSON and is
 * added to a session-scoped list; closing the active tab navigates to a sibling
 * tab or back to the home page.
 */
Alpine.data('tabs', () => ({
    tabs: [],
    current: null,

    init() {
        this.current = this.readCurrentTab();
        this.tabs = this.readStoredTabs();

        if (this.current) {
            this.addTab(this.current);
        }
    },

    readCurrentTab() {
        const node = document.getElementById('dotahub-tab-data');

        if (! node) {
            return null;
        }

        try {
            const tab = JSON.parse(node.textContent);
            return tab && tab.url && tab.label ? tab : null;
        } catch (error) {
            return null;
        }
    },

    readStoredTabs() {
        try {
            const stored = JSON.parse(sessionStorage.getItem(TAB_STORAGE_KEY) || '[]');
            return Array.isArray(stored)
                ? stored.filter((tab) => tab && typeof tab.url === 'string' && typeof tab.label === 'string')
                : [];
        } catch (error) {
            return [];
        }
    },

    persist() {
        try {
            sessionStorage.setItem(TAB_STORAGE_KEY, JSON.stringify(this.tabs));
        } catch (error) {
            // Storage can be disabled; tabs still work for this page view.
        }
    },

    addTab(tab) {
        const index = this.tabs.findIndex((item) => item.url === tab.url);

        if (index === -1) {
            this.tabs = [...this.tabs, tab];
            this.persist();
            return;
        }

        if (this.tabs[index].label !== tab.label) {
            const tabs = this.tabs.slice();
            tabs[index] = { ...tabs[index], label: tab.label };
            this.tabs = tabs;
            this.persist();
        }
    },

    isActive(tab) {
        return Boolean(this.current && this.current.url === tab.url);
    },

    close(tab, event) {
        event.preventDefault();
        event.stopPropagation();

        const index = this.tabs.findIndex((item) => item.url === tab.url);

        if (index === -1) {
            return;
        }

        const remaining = this.tabs.filter((item) => item.url !== tab.url);
        this.tabs = remaining;
        this.persist();

        if (this.isActive(tab)) {
            const fallback = remaining[index] || remaining[index - 1];
            window.location.href = fallback ? fallback.url : '/';
        }
    },
}));

/**
 * Outline/filled star beside the player nickname. Toggling stays on the page:
 * the favourite is stored in Redis and only the star's state changes.
 */
Alpine.data('favoriteStar', (config) => ({
    active: config.active,
    loading: false,

    async toggle() {
        if (this.loading) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(this.active ? config.destroyUrl : config.storeUrl, {
                method: this.active ? 'DELETE' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: this.active
                    ? undefined
                    : JSON.stringify({
                        account_id: config.accountId,
                        nickname: config.nickname,
                        avatar: config.avatar,
                    }),
            });

            if (response.status === 401) {
                const payload = await response.json().catch(() => ({}));
                if (payload.login_url) {
                    window.location.href = payload.login_url;
                }

                return;
            }

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            if (payload.ok === true) {
                this.active = payload.favorite === true;
            }
        } catch (error) {
            console.warn('Favorite toggle failed', error);
        } finally {
            this.loading = false;
        }
    },
}));

/**
 * Net worth chart on a match page. The native tooltip owns minute labels while
 * the side legend keeps per-hero values and lets players toggle lines.
 */
const NET_WORTH_COLORS = [
    '#ef4444',
    '#3b82f6',
    '#22c55e',
    '#eab308',
    '#a855f7',
    '#f97316',
    '#14b8a6',
    '#ec4899',
    '#a16207',
    '#94a3b8',
];

const verticalHoverLine = {
    id: 'verticalHoverLine',
    afterDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements?.() ?? [];

        if (active.length === 0) {
            return;
        }

        const x = active[0].element.x;
        const { ctx, chartArea } = chart;

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(234, 179, 8, 0.45)';
        ctx.stroke();
        ctx.restore();
    },
};

Alpine.data('netWorthChart', (config) => {
    // Chart.js instances and DOM nodes must stay outside Alpine's reactive
    // proxy, otherwise chart.update() recurses through the proxied plugin cache.
    const state = {
        chart: null,
        legend: null,
        legendRows: [],
        hoveredMinute: null,
    };

    const heroKeyFromIcon = (icon) => {
        const match = String(icon ?? '').match(/\/([a-z0-9_]+)\.png(?:[?#]|$)/i);
        return match ? match[1] : '';
    };

    const valueAt = (row, minuteIndex) => {
        if (minuteIndex === null || minuteIndex === undefined) {
            return row.values.length > 0 ? Number(row.values[row.values.length - 1]) || 0 : 0;
        }

        return Number(row.values[minuteIndex]) || 0;
    };

    const formatValue = (value) => Math.round(value).toLocaleString('ru-RU');

    const updateLegendValues = (minuteIndex) => {
        state.hoveredMinute = minuteIndex;

        const rows = state.legendRows.map((row) => {
            const value = valueAt(row, minuteIndex);
            row.currentValue = value;
            row.value.textContent = formatValue(value);

            return row;
        });

        rows.sort((a, b) => b.currentValue - a.currentValue)
            .forEach((row) => state.legend.appendChild(row.row));
    };

    const buildLegend = (datasets) => {
        if (! state.legend) {
            return;
        }

        state.legend.innerHTML = '';
        state.legendRows = datasets.map((dataset, index) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 py-1 hover:bg-ink-700/50 rounded px-1';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = true;
            checkbox.dataset.index = String(index);
            checkbox.className = 'toggle-dataset cursor-pointer';
            checkbox.addEventListener('change', (event) => {
                if (! state.chart) {
                    return;
                }

                state.chart.setDatasetVisibility(index, event.target.checked);
                state.chart.update();
                updateLegendValues(state.hoveredMinute);
            });

            const dot = document.createElement('span');
            dot.className = 'w-2 h-2 rounded-full';
            dot.style.backgroundColor = dataset.borderColor;

            row.append(checkbox, dot);

            const heroKey = dataset.hero_key ?? heroKeyFromIcon(dataset.icon);

            if (heroKey) {
                const image = document.createElement('img');
                image.src = `/images/heroes/icons/${encodeURIComponent(heroKey)}.png`;
                image.className = 'w-5 h-5 object-cover rounded';
                image.alt = '';
                row.append(image);
            }

            const name = document.createElement('span');
            name.className = 'flex-1 truncate';
            name.textContent = dataset.label ?? '';

            const value = document.createElement('span');
            value.className = 'hero-networth font-bold text-white';
            value.dataset.index = String(index);

            row.append(name, value);
            state.legend.appendChild(row);

            return {
                index,
                row,
                value,
                values: (Array.isArray(dataset.data) ? dataset.data : []).map((item) => Number(item) || 0),
            };
        });
    };

    return {
        init() {
            if (! config || ! config.has_data) {
                return;
            }

            const canvas = document.getElementById('netWorthChart');

            if (! canvas) {
                return;
            }

            const rawDatasets = [
                ...(config.radiant?.datasets ?? []),
                ...(config.dire?.datasets ?? []),
            ];

            if (rawDatasets.length === 0) {
                return;
            }

            const datasets = rawDatasets.map((dataset, index) => {
                const color = NET_WORTH_COLORS[index % NET_WORTH_COLORS.length];

                return {
                    ...dataset,
                    borderColor: color,
                    backgroundColor: color,
                };
            });

            state.legend = document.getElementById('chart-legend');
            buildLegend(datasets);

            Chart.defaults.color = '#8b94a5';
            Chart.defaults.font.family = 'Inter, sans-serif';

            state.chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: config.labels ?? [],
                    datasets,
                },
                plugins: [verticalHoverLine],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    hover: {
                        mode: 'index',
                        intersect: false,
                    },
                    onHover: (event, activeElements) => {
                        const elements = activeElements ?? [];
                        updateLegendValues(elements.length > 0 ? elements[0].index : null);
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: (context) => context.length > 0
                                    ? `${config.minute_label ?? 'Minute'} ${context[0].parsed.x}`
                                    : '',
                                label: () => null,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { color: '#2d313e', drawBorder: false },
                            ticks: { color: '#8b94a5', maxTicksLimit: 10 },
                        },
                        y: {
                            grid: { color: '#2d313e', drawBorder: false },
                            ticks: {
                                color: '#8b94a5',
                                callback: (value) => `${Math.round(value / 1000)}k`,
                            },
                        },
                    },
                    elements: {
                        line: { borderWidth: 2, tension: 0.28 },
                        point: { radius: 0, hoverRadius: 4 },
                    },
                },
            });

            canvas.addEventListener('mouseleave', () => updateLegendValues(null));
            updateLegendValues(null);
        },

        destroy() {
            state.chart?.destroy();
        },
    };
});

Alpine.start();
