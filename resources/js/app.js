import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Hero wheel data and the ranked-only toggle. Sector geometry is prepared by
 * Blade; Alpine only swaps between the all-games and ranked-only datasets.
 */
Alpine.data('heroWheel', () => ({
    rankedOnly: false,
    allSectors: [],
    rankedSectors: [],
    allWinrate: 0,
    rankedWinrate: 0,
    heroUrlTemplate: '',

    init() {
        this.allSectors = this.readJson('hero-wheel-all-data');
        this.rankedSectors = this.readJson('hero-wheel-ranked-data');

        const config = document.getElementById('hero-wheel-data');

        if (! config) {
            return;
        }

        this.heroUrlTemplate = config.dataset.urlTemplate || '';
        this.allWinrate = Number(config.dataset.allWinrate) || 0;
        this.rankedWinrate = Number(config.dataset.rankedWinrate) || 0;
    },

    readJson(id) {
        const node = document.getElementById(id);

        if (! node) {
            return [];
        }

        try {
            const value = JSON.parse(node.textContent);
            return Array.isArray(value) ? value : [];
        } catch (error) {
            return [];
        }
    },

    get sectors() {
        return this.rankedOnly ? this.rankedSectors : this.allSectors;
    },

    get overallWinrate() {
        return this.rankedOnly ? this.rankedWinrate : this.allWinrate;
    },

    heroUrl(heroId) {
        return this.heroUrlTemplate.replace('__HERO__', heroId);
    },
}));

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
            console.warn('Не удалось обновить избранное', error);
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.start();
