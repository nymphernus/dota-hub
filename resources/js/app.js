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
 * Net worth chart on a match page. The built-in tooltip is disabled: a static
 * legend beside the canvas shows the same values and lets players toggle lines.
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

Alpine.data('netWorthChart', (config) => ({
    chart: null,
    legend: null,
    legendRows: [],

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

        this.legend = document.getElementById('chart-legend');
        this.legendRows = datasets.map((dataset, index) => ({
            index,
            label: dataset.label ?? '',
            heroKey: dataset.hero_key ?? this.heroKeyFromIcon(dataset.icon),
            color: dataset.borderColor,
            values: (Array.isArray(dataset.data) ? dataset.data : []).map((value) => Number(value) || 0),
        }));

        if (this.legend) {
            this.legend.addEventListener('change', (event) => {
                const input = event.target.closest('input.toggle-dataset');

                if (! input || ! this.chart) {
                    return;
                }

                this.chart.setDatasetVisibility(Number(input.dataset.index), input.checked);
                this.chart.update();
            });
        }

        Chart.defaults.color = '#8b94a5';
        Chart.defaults.font.family = 'Inter, sans-serif';

        this.chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: config.labels ?? [],
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                onHover: (event, activeElements) => {
                    this.renderLegend(activeElements.length > 0 ? activeElements[0].index : null);
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: false,
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

        canvas.addEventListener('mouseleave', () => this.renderLegend(null));
        this.renderLegend(null);
    },

    heroKeyFromIcon(icon) {
        const match = String(icon ?? '').match(/\/([a-z0-9_]+)\.png(?:[?#]|$)/i);
        return match ? match[1] : '';
    },

    valueAt(row, minuteIndex) {
        if (minuteIndex === null || minuteIndex === undefined) {
            return row.values.length > 0 ? Number(row.values[row.values.length - 1]) || 0 : 0;
        }

        return Number(row.values[minuteIndex]) || 0;
    },

    formatValue(value) {
        return Math.round(value).toLocaleString();
    },

    escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[char]);
    },

    renderLegend(minuteIndex) {
        if (! this.legend) {
            return;
        }

        const rows = [...this.legendRows].sort(
            (a, b) => this.valueAt(b, minuteIndex) - this.valueAt(a, minuteIndex),
        );
        const minute = minuteIndex === null || minuteIndex === undefined
            ? Math.max(0, (this.legendRows[0]?.values.length ?? 1) - 1)
            : minuteIndex;
        const heading = `<div class="mb-1 font-semibold text-mist-300">${this.escapeHtml(config.minute_label ?? 'Minute')} ${minute}</div>`;

        const items = rows.map((row) => {
            const icon = row.heroKey
                ? `/images/heroes/icons/${encodeURIComponent(row.heroKey)}.png`
                : '';
            const checked = this.chart?.isDatasetVisible(row.index) !== false;
            const checkedAttribute = checked ? 'checked data-index' : 'data-index';
            const image = icon
                ? `<img src="${icon}" class="w-5 h-5 object-cover rounded" alt="">`
                : '';

            return `<div class="flex items-center gap-2 py-1 hover:bg-ink-700/50 rounded px-1">
    <input type="checkbox" ${checkedAttribute}="${row.index}" class="toggle-dataset cursor-pointer">
    <span class="w-2 h-2 rounded-full" style="background-color: ${row.color}"></span>
    ${image}
    <span class="flex-1 truncate">${this.escapeHtml(row.label)}</span>
    <span class="font-bold text-white">${this.formatValue(this.valueAt(row, minuteIndex))}</span>
</div>`;
        }).join('');

        this.legend.innerHTML = heading + items;
    },

    destroy() {
        this.chart?.destroy();
    },
}));

Alpine.start();
