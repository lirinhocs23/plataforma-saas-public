(() => {
    if (document.body.dataset.ownerEditor === '1') return;
    const slug = document.body.dataset.store;
    if (!slug) return;
    const initiallyOpen = document.body.dataset.storeOpen === '1';
    let checking = false;
    async function checkStatus() {
        if (checking || document.hidden || !navigator.onLine) return;
        checking = true;
        try {
            const response = await fetch(`/api/store/${encodeURIComponent(slug)}/availability`, {cache: 'no-store', headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const status = await response.json();
            if (typeof status.store_open === 'boolean' && status.store_open !== initiallyOpen) location.reload();
        } catch (_) {
            // Keep the current page on network errors; the server validates every order.
        } finally { checking = false; }
    }
    setInterval(checkStatus, 30000);
    document.addEventListener('visibilitychange', checkStatus);
    window.addEventListener('online', checkStatus);
    window.addEventListener('pageshow', checkStatus);
})();

