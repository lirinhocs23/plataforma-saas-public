document.querySelector('[data-copy-referral]')?.addEventListener('click', async (event) => {
  const button = event.currentTarget;
  const url = button.dataset.referralUrl || '';
  if (!/^https?:\/\//.test(url)) return;
  try {
    await navigator.clipboard.writeText(url);
    button.textContent = 'Link copiado';
  } catch {
    window.prompt('Copie seu link de indicação:', url);
  }
});
