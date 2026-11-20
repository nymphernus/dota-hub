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

const RECENT_STORAGE_KEY = 'dotahub.recent.v1';

/**
 * Simple horizontal navigation. The current page is added to a small
 * session-scoped "Недавние" menu; there are no closeable browser-style tabs.
 */
Alpine.data('recentLinks', () => ({
    open: false,
    items: [],
    current: null,

    init() {
        this.current = this.readCurrent();
        this.items = this.readStored();

        if (this.current) {
            this.addCurrent(this.current);
        }
    },

    readCurrent() {
        const node = document.getElementById('dotahub-recent-data');

        if (! node) {
            return null;
        }

        try {
            const item = JSON.parse(node.textContent);
            return item && item.url && item.label ? item : null;
        } catch (error) {
            return null;
        }
    },

    readStored() {
        try {
            const stored = JSON.parse(sessionStorage.getItem(RECENT_STORAGE_KEY) || '[]');
            return Array.isArray(stored)
                ? stored.filter((item) => item && typeof item.url === 'string' && typeof item.label === 'string')
                : [];
        } catch (error) {
            return [];
        }
    },

    persist() {
        try {
            sessionStorage.setItem(RECENT_STORAGE_KEY, JSON.stringify(this.items));
        } catch (error) {
            // Storage can be disabled; the dropdown still works for this view.
        }
    },

    addCurrent(current) {
        const items = [current, ...this.items.filter((item) => item.url !== current.url)].slice(0, 6);

        if (JSON.stringify(items) !== JSON.stringify(this.items)) {
            this.items = items;
            this.persist();
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
