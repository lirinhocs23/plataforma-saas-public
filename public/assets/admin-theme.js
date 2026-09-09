(() => {
    const key = 'mjdev-admin-theme';
    let preference = 'system';

    try {
        preference = localStorage.getItem(key) || 'system';
    } catch (_) {
        preference = 'system';
    }

    const light = preference === 'light'
        || (preference === 'system' && window.matchMedia('(prefers-color-scheme: light)').matches);
    document.documentElement.dataset.adminTheme = light ? 'light' : 'dark';
})();
