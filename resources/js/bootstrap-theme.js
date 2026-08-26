/**
 * Toggle de tema claro/oscuro persistido en cookie (para que Blade lo lea en
 * el primer render y no haya parpadeo).
 */
const KEY = 'sf-theme';

function currentTheme() {
    const m = document.cookie.match(/(?:^|;\s*)sf-theme=(\w+)/);
    return m ? m[1] : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = `${KEY}=${theme}; path=/; max-age=31536000; SameSite=Lax`;
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(currentTheme());
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    });
});

export { applyTheme, currentTheme };
