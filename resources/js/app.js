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
 * Net worth chart on a match page. Chart data is injected by Blade; matches
 * without parsed networth_t keep the section hidden and spend no chart work.
 */
Alpine.data('netWorthChart', (config) => ({
    chart: null,

    init() {
        if (! config || ! config.has_data) {
            return;
        }

        const canvas = document.getElementById('netWorthChart');

        if (! canvas) {
            return;
        }

        const datasets = [
            ...(config.radiant?.datasets ?? []),
            ...(config.dire?.datasets ?? []),
        ];

        if (datasets.length === 0) {
            return;
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
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            color: '#d1d5db',
                            font: { size: 10 },
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1c1e26',
                        borderColor: '#2d313e',
                        borderWidth: 1,
                        titleColor: '#f3f4f6',
                        bodyColor: '#d1d5db',
                        callbacks: {
                            title: (items) => items.length ? `Min ${items[0].label}` : '',
                            label: (item) => `${item.dataset.label}: ${Number(item.parsed.y).toLocaleString()}`,
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
    },

    destroy() {
        this.chart?.destroy();
    },
}));

Alpine.start();
