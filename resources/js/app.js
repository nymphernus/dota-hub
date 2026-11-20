import Alpine from 'alpinejs';
import './hero-wheel';

window.Alpine = Alpine;

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
