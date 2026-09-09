(() => {
  'use strict';
  let deferredInstallPrompt = null;
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('mjdev:pwa-ready'));
  });
  const $ = (selector) => document.querySelector(selector);
  const storeSocialLinks = document.getElementById('storeSocialLinks');
  const storeFooter = document.querySelector('.rodape-vitrine');
  if (storeSocialLinks && storeFooter) storeFooter.insertBefore(storeSocialLinks, storeFooter.querySelector('nav'));
  const storeMain = document.querySelector('main');
  if (storeMain) {
    storeMain.id ||= 'conteudo-principal';
    storeMain.tabIndex = -1;
    const skipLink = document.createElement('a');
    skipLink.className = 'atalho-conteudo';
    skipLink.href = `#${storeMain.id}`;
    skipLink.textContent = 'Pular para o catálogo';
    document.body.prepend(skipLink);
  }
  const money = (cents) => (cents / 100).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
  const slug = document.body.dataset.store;
  const storeName = (document.body.dataset.storeName || slug || 'esta loja').trim();
  const tableToken = document.body.dataset.tableToken || '';
  const tableName = document.body.dataset.tableName || '';
  const storeOpen = document.body.dataset.storeOpen === '1';
  const ordersEnabled = document.body.dataset.ordersEnabled === '1';
  const hasDeliveryZones = document.body.dataset.hasDeliveryZones === '1';
  const whatsapp = document.body.dataset.whatsapp || '';
  const installStateKey = `mjdev-pwa-installed:${slug}`;
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
  const buildInstallHelp = () => {
    let dialog = $('#pwaInstallHelp');
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.id = 'pwaInstallHelp';
    dialog.className = 'modal-fluxo ajuda-instalacao-pwa';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'fechar-modal';
    close.setAttribute('aria-label', 'Fechar instruções');
    close.textContent = '×';
    close.addEventListener('click', () => dialog.close());
    const eyebrow = document.createElement('span');
    eyebrow.className = 'sobretitulo';
    eyebrow.textContent = 'ACESSO RÁPIDO';
    const title = document.createElement('h2');
    title.textContent = `Instalar ${storeName}`;
    const text = document.createElement('p');
    text.textContent = isIos
      ? 'No Safari, toque em Compartilhar e escolha “Adicionar à Tela de Início”.'
      : 'Abra o menu do navegador e escolha “Instalar aplicativo” ou “Adicionar à tela inicial”.';
    const steps = document.createElement('ol');
    (isIos
      ? ['Toque no botão Compartilhar do Safari.', 'Escolha “Adicionar à Tela de Início”.', 'Confirme o nome e toque em Adicionar.']
      : ['Abra o menu do navegador.', 'Escolha “Instalar aplicativo”.', 'Confirme a instalação.']
    ).forEach((label) => { const item = document.createElement('li'); item.textContent = label; steps.append(item); });
    dialog.append(close, eyebrow, title, text, steps);
    document.body.append(dialog);
    return dialog;
  };
  const requestPwaInstall = async () => {
    if (!deferredInstallPrompt) {
      buildInstallHelp().showModal();
      return;
    }
    const prompt = deferredInstallPrompt;
    deferredInstallPrompt = null;
    await prompt.prompt();
    const choice = await prompt.userChoice;
    if (choice.outcome !== 'accepted') window.dispatchEvent(new CustomEvent('mjdev:pwa-ready'));
  };
  const setupPwaInstall = () => {
    if (isStandalone || localStorage.getItem(installStateKey) === '1') return;
    document.body.classList.add('pwa-install-visible');
    const notice = document.createElement('aside');
    notice.className = 'aviso-instalacao-pwa';
    notice.setAttribute('aria-label', 'Instalar aplicativo da loja');
    const icon = document.createElement('img');
    icon.src = `/loja/${encodeURIComponent(slug)}/icon-192.png`;
    icon.alt = '';
    const copy = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = `Instalar ${storeName}`;
    const description = document.createElement('small');
    description.textContent = 'Acesse mais rápido, direto da tela inicial.';
    copy.append(title, description);
    const install = document.createElement('button');
    install.type = 'button';
    install.className = 'botao primario';
    install.textContent = 'Instalar';
    install.addEventListener('click', requestPwaInstall);
    const later = document.createElement('button');
    later.type = 'button';
    later.className = 'adiar-instalacao-pwa';
    later.setAttribute('aria-label', 'Agora não');
    later.textContent = '×';
    later.addEventListener('click', () => { notice.remove(); document.body.classList.remove('pwa-install-visible'); });
    notice.append(icon, copy, install, later);
    document.body.append(notice);
    const menuInstall = document.createElement('button');
    menuInstall.type = 'button';
    menuInstall.className = 'botao completo instalar-pwa-menu';
    menuInstall.textContent = 'Instalar aplicativo';
    menuInstall.addEventListener('click', requestPwaInstall);
    $('#mobileTrackButton')?.before(menuInstall);
    window.addEventListener('appinstalled', () => {
      localStorage.setItem(installStateKey, '1');
      notice.remove();
      menuInstall.remove();
      document.body.classList.remove('pwa-install-visible');
      deferredInstallPrompt = null;
    }, {once: true});
  };
  setupPwaInstall();
  const productOptions = JSON.parse($('#productOptionsData')?.textContent || '{}');
  const cartKey = `mjdev-cart:${slug}:${tableToken ? 'table' : 'store'}`;
  const couponKey = `mjdev-coupon:${slug}`;
  const referralKey = `mjdev-referral:${slug}`;
  let cart = [];
  let pendingProduct = null;
  const dialogOpeners = new WeakMap();
  const prepareDialog = (dialog) => {
    if (!dialog) return;
    const title = dialog.querySelector('h1,h2');
    if (title) {
      if (!title.id) title.id = `${dialog.id || 'modal'}-titulo`;
      if (!dialog.hasAttribute('aria-labelledby')) dialog.setAttribute('aria-labelledby', title.id);
    }
    dialog.querySelectorAll('button').forEach((button) => {
      if (button.textContent.trim() === '×' && !button.hasAttribute('aria-label')) button.setAttribute('aria-label', 'Fechar janela');
    });
    dialog.addEventListener('close', () => {
      const opener = dialogOpeners.get(dialog);
      if (opener instanceof HTMLElement && opener.isConnected) opener.focus();
    });
  };
  const openDialog = (dialog) => {
    if (!dialog) return;
    dialogOpeners.set(dialog, document.activeElement);
    dialog.showModal();
  };
  document.querySelectorAll('dialog').forEach(prepareDialog);
  document.querySelectorAll('.aviso').forEach((notice) => notice.setAttribute('role', notice.classList.contains('error') ? 'alert' : 'status'));
  const checkoutFormElement = $('#checkoutForm');
  const checkoutErrorElement = $('#checkoutError');
  if (checkoutFormElement && checkoutErrorElement) {
    checkoutFormElement.setAttribute('aria-describedby', 'checkoutError');
    checkoutErrorElement.setAttribute('role', 'alert');
    checkoutErrorElement.setAttribute('aria-live', 'assertive');
  }
  const publicFieldSemantics = {
    customer_name: ['name', 'text'],
    customer_phone: ['tel', 'tel'],
    customer_email: ['email', 'email'],
    address: ['shipping street-address', 'text']
  };
  Object.entries(publicFieldSemantics).forEach(([name, [autocomplete, inputMode]]) => {
    const field = document.querySelector(`[name="${name}"]`);
    if (!field) return;
    field.setAttribute('autocomplete', autocomplete);
    field.setAttribute('inputmode', inputMode);
  });
  const trackingUrlField = $('#trackingUrl');
  trackingUrlField?.setAttribute('aria-label', 'Link seguro de acompanhamento do pedido');
  trackingUrlField?.setAttribute('inputmode', 'url');

  const referralFromUrl = new URLSearchParams(location.search).get('ref') || '';
  if (/^[a-f0-9]{64}$/.test(referralFromUrl)) {
    try { localStorage.setItem(referralKey, referralFromUrl); } catch {}
  }

  const savedTheme = localStorage.getItem('mjdev-store-theme');
  if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;
  const floating = document.createElement('div');
  floating.className = 'flutuante';
  const themeButton = document.createElement('button');
  themeButton.type = 'button';
  themeButton.setAttribute('aria-label', 'Alternar tema');
  themeButton.textContent = document.documentElement.dataset.theme === 'light' ? '☾' : '☀';
  themeButton.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = next;
    localStorage.setItem('mjdev-store-theme', next);
    themeButton.textContent = next === 'light' ? '☾' : '☀';
  });
  floating.append(themeButton);
  if (whatsapp) {
    const whatsappLink = document.createElement('a');
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const whatsappIcon = document.createElementNS(svgNamespace, 'svg');
    const whatsappPath = document.createElementNS(svgNamespace, 'path');
    const normalizedWhatsapp = whatsapp.replace(/^0+/, '');
    whatsappLink.href = `https://wa.me/${normalizedWhatsapp.startsWith('55') ? normalizedWhatsapp : `55${normalizedWhatsapp}`}`;
    whatsappLink.target = '_blank';
    whatsappLink.rel = 'noopener';
    whatsappLink.setAttribute('aria-label', 'Falar pelo WhatsApp');
    whatsappIcon.setAttribute('viewBox', '0 0 32 32');
    whatsappIcon.setAttribute('aria-hidden', 'true');
    whatsappIcon.setAttribute('focusable', 'false');
    whatsappPath.setAttribute('fill', 'currentColor');
    whatsappPath.setAttribute('d', 'M16.004 3C8.822 3 3 8.82 3 16c0 2.29.596 4.527 1.729 6.494L3 29l6.674-1.75A12.94 12.94 0 0 0 16.004 29C23.184 29 29 23.18 29 16S23.184 3 16.004 3zm0 23.635a10.58 10.58 0 0 1-5.397-1.478l-.387-.23-3.96 1.038 1.057-3.86-.252-.397A10.56 10.56 0 0 1 5.37 16c0-5.864 4.77-10.635 10.634-10.635S26.635 10.136 26.635 16 21.868 26.635 16.004 26.635zm5.83-7.956c-.32-.16-1.895-.935-2.19-1.042-.293-.107-.507-.16-.72.16-.214.32-.827 1.042-1.014 1.255-.187.214-.374.24-.694.08-.32-.16-1.351-.498-2.574-1.588-.951-.848-1.593-1.895-1.78-2.215-.186-.32-.02-.493.14-.652.144-.143.32-.374.48-.56.16-.187.214-.32.32-.534.107-.213.054-.4-.027-.56-.08-.16-.72-1.735-.987-2.375-.26-.624-.524-.54-.72-.55l-.614-.01c-.213 0-.56.08-.854.4-.293.32-1.12 1.095-1.12 2.67s1.147 3.095 1.307 3.309c.16.213 2.258 3.447 5.47 4.834.764.33 1.36.527 1.825.675.767.244 1.465.21 2.017.127.615-.092 1.895-.775 2.162-1.522.267-.747.267-1.388.187-1.522-.08-.133-.294-.213-.614-.373z');
    whatsappIcon.append(whatsappPath);
    whatsappLink.append(whatsappIcon);
    floating.append(whatsappLink);
  }
  document.body.append(floating);

  const mobileNav = $('#mobileNav');
  const mobileBackdrop = $('#mobileNavBackdrop');
  const menuButton = $('#menuBtn');
  const cartDrawer = $('#cartDrawer');
  let mobileMenuOpener = null;
  let cartOpener = null;
  const setMobileMenu = (open) => {
    if (open) mobileMenuOpener = document.activeElement;
    mobileNav?.classList.toggle('aberto', open);
    mobileBackdrop?.classList.toggle('aberto', open);
    mobileNav?.setAttribute('aria-hidden', open ? 'false' : 'true');
    menuButton?.setAttribute('aria-expanded', String(open));
    if (open) $('#mobileNavClose')?.focus();
    else if (mobileMenuOpener instanceof HTMLElement) mobileMenuOpener.focus();
  };
  $('#menuBtn')?.addEventListener('click', () => setMobileMenu(!mobileNav?.classList.contains('aberto')));
  $('#mobileNavClose')?.addEventListener('click', () => setMobileMenu(false));
  mobileBackdrop?.addEventListener('click', () => setMobileMenu(false));
  mobileNav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMobileMenu(false)));
  $('#mobileTrackButton')?.addEventListener('click', () => { setMobileMenu(false); openDialog($('#trackingDialog')); });
  $('#mobileCartButton')?.addEventListener('click', () => { setMobileMenu(false); openCart(); });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (mobileNav?.classList.contains('aberto')) setMobileMenu(false);
    if (cartDrawer?.classList.contains('aberto')) closeCart();
  });

  document.querySelector('[data-featured-coupon]')?.addEventListener('click', async (event) => {
    const code = event.currentTarget.dataset.featuredCoupon || '';
    localStorage.setItem(couponKey, code);
    const coupon = $('#coupon');
    if (coupon) coupon.value = code;
    try { await navigator.clipboard.writeText(code); } catch (_) {}
    toast(`Cupom ${code} copiado e aplicado.`);
  });

  try {
    const stored = JSON.parse(localStorage.getItem(cartKey) || '[]');
    if (Array.isArray(stored)) cart = stored.filter((item) => Number.isInteger(item.id)
      && typeof item.name === 'string' && Number.isInteger(item.price)
      && Number.isInteger(item.quantity) && item.quantity > 0 && item.quantity <= 50
      && typeof item.key === 'string' && typeof item.notes === 'string'
      && Array.isArray(item.options) && item.options.every((option) => Number.isInteger(option.id)
        && typeof option.name === 'string' && Number.isInteger(option.price))).slice(0, 100);
  } catch {
    localStorage.removeItem(cartKey);
  }

  if (tableToken) {
    if (!$('#orderType option[value="dine_in"]')) {
      const option = document.createElement('option');
      option.value = 'dine_in';
      option.textContent = `Na mesa · ${tableName}`;
      $('#orderType').append(option);
    }
    $('#orderType').value = 'dine_in';
    $('#orderType').disabled = true;
    $('#zoneField').hidden = true;
    $('#addressField').hidden = true;
    document.querySelector('.estado-cabecalho small').textContent = `Pedido na ${tableName}`;
  } else if (!hasDeliveryZones) {
    const deliveryOption = $('#orderType option[value="delivery"]');
    if (deliveryOption) deliveryOption.disabled = true;
    $('#orderType').value = 'pickup';
    $('#zoneField').hidden = true;
    $('#addressField').hidden = true;
  }

  if (!storeOpen || !ordersEnabled) {
    $('#checkoutButton').disabled = true;
    $('#checkoutButton').textContent = storeOpen ? 'Pedidos indisponíveis' : 'Loja fechada';
  }

  const privacyLink = document.createElement('a');
  privacyLink.href = `/privacidade/solicitar/${encodeURIComponent(slug)}`;
  privacyLink.textContent = 'Privacidade';
  document.querySelector('.cabecalho-site nav')?.append(privacyLink);

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});

  const catalogSearch = $('#catalogSearch');
  let selectedCategory = '';
  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const filterCatalog = () => {
    const query = normalize(catalogSearch?.value || '');
    document.querySelectorAll('[data-catalog-product]').forEach((card) => {
      const matchesCategory = !selectedCategory || card.dataset.category === selectedCategory;
      const matchesSearch = !query || normalize(card.dataset.search || card.textContent).includes(query);
      card.hidden = !(matchesCategory && matchesSearch);
    });
    document.querySelectorAll('.grade-produtos').forEach((grid) => {
      const visible = [...grid.querySelectorAll('[data-catalog-product]')].some((card) => !card.hidden);
      grid.hidden = !visible;
      if (grid.previousElementSibling?.tagName === 'H3') grid.previousElementSibling.hidden = !visible;
    });
  };
  catalogSearch?.addEventListener('input', filterCatalog);
  document.querySelectorAll('[data-catalog-category]').forEach((button) => button.addEventListener('click', () => {
    selectedCategory = button.dataset.catalogCategory || '';
    document.querySelectorAll('[data-catalog-category]').forEach((candidate) => candidate.classList.toggle('ativo', candidate === button));
    filterCatalog();
  }));

  fetch(`/api/store/${encodeURIComponent(slug)}/availability`, {headers: {'Accept': 'application/json'}})
    .then((response) => response.ok ? response.json() : Promise.reject())
    .then((data) => data.products.forEach((product) => {
      if (product.stock_quantity === null || Number(product.stock_quantity) > 0) return;
      const button = document.querySelector(`.add-product[data-id="${Number(product.id)}"]`);
      if (!button) return;
      const replacement = button.cloneNode(true);
      replacement.disabled = false;
      replacement.textContent = 'Avise-me';
      replacement.setAttribute('aria-label', 'Entrar na lista de espera');
      replacement.addEventListener('click', () => { location.href = `/loja/${encodeURIComponent(slug)}/espera/${Number(product.id)}`; });
      button.replaceWith(replacement);
    })).catch(() => {});

  const toast = (message) => {
    const element = $('#toast');
    element.textContent = message;
    element.classList.add('visivel');
    setTimeout(() => element.classList.remove('visivel'), 2200);
  };

  const optionPriceTotal = (options) => {
    const groups = new Map();
    for (const option of options) {
      const group = Number.isInteger(option.group) ? option.group : `legacy-${option.id}`;
      const current = groups.get(group) || {strategy: option.strategy === 'highest' ? 'highest' : 'sum', prices: []};
      current.prices.push(Number(option.price) || 0);
      groups.set(group, current);
    }
    return [...groups.values()].reduce((total, group) => total + (group.strategy === 'highest' ? Math.max(0, ...group.prices) : group.prices.reduce((sum, price) => sum + price, 0)), 0);
  };

  function render() {
    const box = $('#cartItems');
    box.replaceChildren();
    let total = 0;
    let quantity = 0;
    for (const item of cart) {
      const optionPrice = optionPriceTotal(item.options);
      total += (item.price + optionPrice) * item.quantity;
      quantity += item.quantity;
      const row = document.createElement('div');
      row.className = 'item-carrinho';
      const text = document.createElement('div');
      const title = document.createElement('h4');
      title.textContent = item.name;
      const detail = document.createElement('small');
      detail.textContent = `${item.quantity} × ${money(item.price + optionPrice)}${item.options.length ? ` · ${item.options.map((option) => option.name).join(', ')}` : ''}`;
      text.append(title, detail);
      const actions = document.createElement('div');
      actions.className = 'acoes-item-carrinho';
      const decrease = document.createElement('button');
      decrease.type = 'button';
      decrease.textContent = '−';
      decrease.setAttribute('aria-label', `Diminuir quantidade de ${item.name}`);
      decrease.addEventListener('click', () => {
        item.quantity--;
        if (item.quantity < 1) cart = cart.filter((candidate) => candidate.key !== item.key);
        render();
      });
      const amount = document.createElement('b');
      amount.textContent = String(item.quantity);
      const increase = document.createElement('button');
      increase.type = 'button';
      increase.textContent = '+';
      increase.setAttribute('aria-label', `Aumentar quantidade de ${item.name}`);
      increase.disabled = item.quantity >= 50;
      increase.addEventListener('click', () => { item.quantity++; render(); });
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = 'Remover';
      remove.className = 'remover-item-carrinho';
      remove.addEventListener('click', () => {
        cart = cart.filter((candidate) => candidate.key !== item.key);
        render();
      });
      actions.append(decrease, amount, increase, remove);
      row.append(text, actions);
      box.append(row);
    }
    if (!cart.length) box.textContent = 'Sua sacola está vazia.';
    $('#cartQty').textContent = String(quantity);
    $('#cartTotal').textContent = money(total);
    if ($('#mobileCartQty')) $('#mobileCartQty').textContent = String(quantity);
    if ($('#mobileCartTotal')) $('#mobileCartTotal').textContent = money(total);
    $('#mobileCartBar')?.classList.toggle('tem-itens', quantity > 0);
    try { localStorage.setItem(cartKey, JSON.stringify(cart)); } catch {}
  }

  function addConfiguredProduct(product, options = [], notes = '') {
    const key = `${product.id}:${options.map((option) => option.id).sort((a, b) => a - b).join(',')}:${notes}`;
    const found = cart.find((item) => item.key === key);
    const quantity = Math.max(1, Math.min(50, Number(product.quantity) || 1));
    if (found && found.quantity + quantity > 50) {
      toast('O limite é de 50 unidades por item.');
      return false;
    }
    if (found) found.quantity += quantity;
    else cart.push({...product, key, options, notes, quantity});
    render();
    toast('Produto adicionado');
    return true;
  }

  function openProductDetails(button) {
    const product = {id: Number(button.dataset.id), name: button.dataset.name, price: Number(button.dataset.price)};
    const groups = productOptions[product.id] || [];
    $('#optionsForm').reset();
    pendingProduct = product;
    $('#optionsTitle').textContent = product.name;
    const source = document.querySelector(`[data-catalog-product][data-product-id="${product.id}"]`);
    let details = $('#productDetails');
    if (!details) {
      details = document.createElement('section');
      details.id = 'productDetails';
      $('#optionsTitle').after(details);
    }
    details.replaceChildren();
    if (source?.dataset.image) {
      const photo = document.createElement('img');
      photo.src = source.dataset.image;
      photo.alt = product.name;
      details.append(photo);
    }
    const description = document.createElement('p');
    description.textContent = source?.dataset.description || 'Consulte as opções e personalize seu pedido.';
    const price = document.createElement('strong');
    price.textContent = money(product.price);
    details.append(description, price);
    let quantity = $('#productQuantity');
    if (!quantity) {
      const label = document.createElement('label');
      label.className = 'campo quantidade-produto';
      label.textContent = 'Quantidade';
      quantity = document.createElement('input');
      quantity.id = 'productQuantity';
      quantity.type = 'number';
      quantity.min = '1';
      quantity.max = '50';
      quantity.step = '1';
      quantity.value = '1';
      quantity.required = true;
      label.append(quantity);
      $('#optionsForm button[type="submit"]').before(label);
    }
    quantity.value = '1';
    const fields = $('#optionsFields');
    fields.replaceChildren();
    for (const group of groups) {
      const fieldset = document.createElement('fieldset');
      fieldset.className = 'campo completo';
      const legend = document.createElement('legend');
      legend.textContent = `${group.name}${group.required ? ' *' : ''} · escolha ${group.min}–${group.max}${group.pricing === 'highest' ? ' · cobra o maior valor' : ''}`;
      fieldset.append(legend);
      for (const option of group.items) {
        const label = document.createElement('label');
        label.className = 'opcao-produto';
        const input = document.createElement('input');
        input.type = group.type === 'single' ? 'radio' : 'checkbox';
        input.name = `group_${group.id}`;
        input.value = String(option.id);
        input.dataset.name = option.name;
        input.dataset.price = String(option.price);
        input.dataset.min = String(group.min);
        input.dataset.max = String(group.max);
        input.dataset.group = String(group.id);
        input.dataset.strategy = group.pricing === 'highest' ? 'highest' : 'sum';
        input.required = group.required && group.type === 'single';
        label.append(input, document.createTextNode(` ${option.name}${option.price ? ` (+${money(option.price)})` : ''}`));
        fieldset.append(label);
      }
      fields.append(fieldset);
    }
    openDialog($('#optionsDialog'));
  }

  document.querySelectorAll('.add-product').forEach((button) => {
    button.addEventListener('click', () => openProductDetails(button));
    const card = button.closest('[data-catalog-product], .recurso');
    if (!card || document.body.dataset.ownerEditor === '1') return;
    const title = card.querySelector('h3');
    if (title) {
      const link = document.createElement('button');
      link.type = 'button';
      link.className = 'abrir-detalhes-produto';
      link.textContent = title.textContent;
      link.setAttribute('aria-haspopup', 'dialog');
      title.replaceChildren(link);
    }
    card.addEventListener('click', (event) => {
      if (event.target.closest('.add-product')) return;
      openProductDetails(button);
    });
  });

  $('#optionsClose')?.addEventListener('click', () => $('#optionsDialog').close());
  $('#optionsForm')?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!pendingProduct) return;
    const selected = [...event.currentTarget.querySelectorAll('#optionsFields input:checked')];
    const groups = productOptions[pendingProduct.id] || [];
    for (const group of groups) {
      const count = selected.filter((input) => Number(input.dataset.group) === group.id).length;
      if (count < group.min || count > group.max) return toast(`Revise as escolhas de ${group.name}.`);
    }
    const options = selected.map((input) => ({id: Number(input.value), name: input.dataset.name, price: Number(input.dataset.price), group: Number(input.dataset.group), strategy: input.dataset.strategy === 'highest' ? 'highest' : 'sum'}));
    const quantity = Number($('#productQuantity').value);
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 50) return toast('Escolha de 1 a 50 unidades.');
    if (!addConfiguredProduct({...pendingProduct, quantity}, options, String(new FormData(event.currentTarget).get('notes') || '').trim())) return;
    event.currentTarget.reset();
    $('#optionsDialog').close();
    pendingProduct = null;
  });

  const ownerEditorEnabled = document.body.dataset.ownerEditor === '1';
  const ownerProductDialog = $('#ownerProductEditor');
  const ownerProductForm = ownerProductDialog?.querySelector('[data-owner-product-form]');
  const openOwnerProductEditor = (product = null) => {
    if (!ownerEditorEnabled || !ownerProductDialog || !ownerProductForm) return;
    ownerProductForm.reset();
    ownerProductForm.elements.id.value = product?.dataset.productId || '';
    ownerProductForm.elements.name.value = product?.dataset.name || '';
    ownerProductForm.elements.description.value = product?.dataset.description || '';
    ownerProductForm.elements.price.value = product?.dataset.price || '';
    if (product?.dataset.categoryId) ownerProductForm.elements.category_id.value = product.dataset.categoryId;
    const title = ownerProductDialog.querySelector('[data-owner-editor-title]');
    if (title) title.textContent = product ? `Editar ${product.dataset.name || 'produto'}` : 'Adicionar produto';
    openDialog(ownerProductDialog);
  };
  document.querySelector('[data-owner-new-product]')?.addEventListener('click', () => openOwnerProductEditor());
  document.querySelector('[data-owner-editor-close]')?.addEventListener('click', () => ownerProductDialog?.close());
  document.querySelectorAll('[data-owner-product]').forEach((product) => {
    product.tabIndex = 0;
    product.setAttribute('role', 'button');
    product.setAttribute('aria-label', `Editar ${product.dataset.name || 'produto'}`);
    product.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      openOwnerProductEditor(product);
    }, true);
    product.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openOwnerProductEditor(product);
    });
  });
  if (ownerEditorEnabled) document.addEventListener('click', (event) => {
    const addButton = event.target.closest?.('.add-product[data-id]');
    if (!addButton) return;
    const product = document.querySelector(`[data-owner-product][data-product-id="${Number(addButton.dataset.id)}"]`);
    if (!product) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openOwnerProductEditor(product);
  }, true);

  const openCart = () => {
    cartOpener = document.activeElement;
    cartDrawer.classList.add('aberto');
    cartDrawer.setAttribute('aria-hidden', 'false');
    $('#backdrop').classList.add('aberto');
    $('#cartClose').focus();
  };
  const closeCart = () => {
    cartDrawer.classList.remove('aberto');
    cartDrawer.setAttribute('aria-hidden', 'true');
    $('#backdrop').classList.remove('aberto');
    if (cartOpener instanceof HTMLElement) cartOpener.focus();
  };

  $('#cartButton').addEventListener('click', openCart);
  $('#mobileCartBar')?.addEventListener('click', openCart);
  $('#cartClose').addEventListener('click', closeCart);
  $('#backdrop').addEventListener('click', closeCart);
  $('#catalogButton')?.addEventListener('click', () => { location.hash = 'cardapio'; });
  $('#checkoutButton').addEventListener('click', () => {
    if (!cart.length) return toast('Adicione um produto.');
    closeCart();
    showCheckoutStep(1);
    openDialog($('#checkoutDialog'));
  });
  $('#checkoutClose').addEventListener('click', () => $('#checkoutDialog').close());
  const syncReceivingFields = () => {
    const delivery = !tableToken && $('#orderType').value === 'delivery' && hasDeliveryZones;
    $('#zoneField').hidden = !delivery;
    $('#addressField').hidden = !delivery;
  };
  $('#orderType').addEventListener('change', syncReceivingFields);

  const checkoutForm = $('#checkoutForm');
  const checkoutFields = checkoutForm ? [...checkoutForm.querySelectorAll('.grade-formulario > label')] : [];
  const checkoutTitle = checkoutForm?.querySelector('h2');
  const checkoutSubmit = checkoutForm?.querySelector('button[type="submit"]');
  const checkoutSteps = [
    checkoutFields.filter((field) => field.querySelector('[name="customer_name"], [name="customer_phone"], [name="customer_email"]')),
    checkoutFields.filter((field) => field.querySelector('[name="order_type"], [name="delivery_zone_id"], [name="address"]')),
    checkoutFields.filter((field) => field.querySelector('[name="payment_method"], [name="privacy_accepted"]')),
    [],
  ];
  let checkoutStep = 1;
  const checkoutProgress = document.createElement('div');
  checkoutProgress.className = 'etapas';
  const checkoutReview = document.createElement('div');
  checkoutReview.className = 'caixa-revisao';
  checkoutReview.hidden = true;
  const checkoutActions = document.createElement('div');
  checkoutActions.className = 'acoes-checkout';
  const checkoutBack = document.createElement('button');
  checkoutBack.type = 'button';
  checkoutBack.className = 'botao';
  checkoutBack.textContent = 'Voltar';
  const checkoutNext = document.createElement('button');
  checkoutNext.type = 'button';
  checkoutNext.className = 'botao primario';
  checkoutNext.textContent = 'Continuar';
  checkoutActions.append(checkoutBack, checkoutNext);
  checkoutForm?.querySelector('.grade-formulario')?.before(checkoutProgress);
  checkoutForm?.querySelector('.grade-formulario')?.after(checkoutReview, checkoutActions);

  function renderCheckoutReview() {
    checkoutReview.replaceChildren();
    const form = new FormData(checkoutForm);
    const typeLabels = {delivery: 'Entrega', pickup: 'Retirada', dine_in: `Mesa · ${tableName}`};
    const paymentLabels = {pix: 'Pix', cash: 'Dinheiro', debit: 'Débito', credit: 'Crédito'};
    const heading = document.createElement('strong');
    heading.textContent = `${form.get('customer_name') || 'Cliente'} · ${typeLabels[tableToken ? 'dine_in' : form.get('order_type')] || 'Pedido'}`;
    checkoutReview.append(heading);
    for (const item of cart) {
      const line = document.createElement('p');
      const optionPrice = optionPriceTotal(item.options);
      line.textContent = `${item.quantity}× ${item.name} — ${money((item.price + optionPrice) * item.quantity)}`;
      checkoutReview.append(line);
    }
    const payment = document.createElement('p');
    payment.textContent = `Pagamento: ${paymentLabels[form.get('payment_method')] || 'No atendimento'}`;
    checkoutReview.append(payment);
    const warning = document.createElement('small');
    warning.textContent = 'Desconto, taxa e total final serão confirmados com segurança pelo servidor.';
    checkoutReview.append(warning);
  }

  function showCheckoutStep(step) {
    checkoutStep = Math.max(1, Math.min(4, step));
    const titles = ['Seus dados', 'Como deseja receber?', 'Pagamento no atendimento', 'Revise seu pedido'];
    if (checkoutTitle) checkoutTitle.textContent = titles[checkoutStep - 1];
    checkoutProgress.replaceChildren(...[1, 2, 3, 4].map((number) => {
      const marker = document.createElement('i');
      marker.classList.toggle('ativo', number <= checkoutStep);
      marker.setAttribute('aria-label', `Etapa ${number} de 4`);
      return marker;
    }));
    checkoutFields.forEach((field) => { field.hidden = !checkoutSteps[checkoutStep - 1].includes(field); });
    if (checkoutStep === 2) syncReceivingFields();
    checkoutReview.hidden = checkoutStep !== 4;
    checkoutBack.hidden = checkoutStep === 1;
    checkoutNext.hidden = checkoutStep === 4;
    if (checkoutSubmit) checkoutSubmit.hidden = checkoutStep !== 4;
    if (checkoutStep === 4) renderCheckoutReview();
  }

  checkoutBack.addEventListener('click', () => showCheckoutStep(checkoutStep - 1));
  checkoutNext.addEventListener('click', () => {
    const visibleInputs = checkoutSteps[checkoutStep - 1].flatMap((field) => [...field.querySelectorAll('input,select,textarea')]).filter((input) => !input.disabled && !input.closest('[hidden]'));
    const invalid = visibleInputs.find((input) => !input.reportValidity());
    if (!invalid) showCheckoutStep(checkoutStep + 1);
  });
  showCheckoutStep(1);

  $('#checkoutForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = Object.fromEntries(form.entries());
    payload.items = cart.map((item) => item.id < 0
      ? ({combo_id: Math.abs(item.id), quantity: item.quantity, notes: item.notes})
      : ({product_id: item.id, quantity: item.quantity, notes: item.notes, option_item_ids: item.options.map((option) => option.id)}));
    payload.coupon = $('#coupon').value.trim();
    const referralToken = localStorage.getItem(referralKey) || '';
    if (/^[a-f0-9]{64}$/.test(referralToken)) payload.referral_token = referralToken;
    if (tableToken) {
      payload.order_type = 'dine_in';
      payload.table_token = tableToken;
    }
    const error = $('#checkoutError');
    error.hidden = true;
    try {
      const response = await fetch(`/api/store/${encodeURIComponent(slug)}/orders`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Falha ao criar pedido.');
      cart = [];
      localStorage.removeItem(cartKey);
      localStorage.removeItem(couponKey);
      localStorage.removeItem(referralKey);
      render();
      location.href = data.tracking_url;
    } catch (exception) {
      error.textContent = exception.message;
      error.hidden = false;
    }
  });
  $('#trackButton').addEventListener('click', () => openDialog($('#trackingDialog')));
  $('#trackingClose').addEventListener('click', () => $('#trackingDialog').close());
  $('#trackingGo').addEventListener('click', () => {
    const value = $('#trackingUrl').value.trim();
    try {
      const url = new URL(value, location.origin);
      if (url.origin !== location.origin || !/^\/pedido\/[a-f0-9]{64}$/.test(url.pathname)) throw new Error();
      location.href = url.pathname;
    } catch {
      toast('Link de acompanhamento inválido.');
    }
  });
  const couponInput = $('#coupon');
  couponInput.value = localStorage.getItem(couponKey) || '';
  couponInput.addEventListener('input', () => {
    try { localStorage.setItem(couponKey, couponInput.value.trim().toUpperCase()); } catch {}
  });
  render();
})();
