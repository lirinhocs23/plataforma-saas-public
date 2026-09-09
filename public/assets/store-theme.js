(() => {
    try {
        const savedTheme = localStorage.getItem('mjdev-store-theme');
        if (savedTheme === 'light' || savedTheme === 'dark') {
            document.documentElement.dataset.theme = savedTheme;
        }
    } catch (_) {
        // A vitrine continua utilizável quando o armazenamento está indisponível.
    }
})();
