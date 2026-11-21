// Tema claro u oscuro, como en OriA: por defecto sigue al equipo y el botón de luna lo fija.
// El <head> (layouts/partials/theme-head) ya puso data-theme antes de pintar; aquí solo se cambia y se recuerda.

const STORAGE_KEY = 'benditio.theme';
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

const readChoice = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    } catch (e) {
        return 'system';
    }
};

const apply = (choice) => {
    const dark = choice === 'dark' || (choice === 'system' && systemDark.matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';

    return dark;
};

systemDark.addEventListener('change', () => {
    if (readChoice() === 'system') {
        apply('system');
        window.dispatchEvent(new CustomEvent('benditio:theme'));
    }
});

export const themeToggle = () => ({
    dark: document.documentElement.dataset.theme === 'dark',

    init() {
        window.addEventListener('benditio:theme', () => {
            this.dark = document.documentElement.dataset.theme === 'dark';
        });
    },

    get label() {
        return this.dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
    },

    toggle() {
        const choice = this.dark ? 'light' : 'dark';

        try {
            localStorage.setItem(STORAGE_KEY, choice);
        } catch (e) {
            // Sin almacenamiento el cambio dura hasta recargar.
        }

        this.dark = apply(choice);
    },
});
