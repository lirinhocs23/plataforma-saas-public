const sidebar = document.querySelector('#sidebar');
const openMenu = document.querySelector('#openMenu');
const closeMenu = document.querySelector('#closeMenu');
const menuBackdrop = document.querySelector('#menuBackdrop');

const adminMain = document.querySelector('main.estrutura,main');
if (adminMain) {
    adminMain.id ||= 'conteudo-principal';
    adminMain.tabIndex = -1;
    const skipLink = document.createElement('a');
    skipLink.className = 'atalho-conteudo';
    skipLink.href = `#${adminMain.id}`;
    skipLink.textContent = 'Pular para o conteúdo principal';
    document.body.prepend(skipLink);
}

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
document.querySelectorAll('.envoltorio-tabela').forEach((wrapper) => {
    wrapper.tabIndex = 0;
    wrapper.setAttribute('role', 'region');
    if (!wrapper.hasAttribute('aria-label')) wrapper.setAttribute('aria-label', 'Tabela com rolagem horizontal quando necessário');
});

const adminThemeKey = 'mjdev-admin-theme';
const systemTheme = window.matchMedia('(prefers-color-scheme: light)');
const applyAdminTheme = (preference = localStorage.getItem(adminThemeKey) || 'system') => {
    const resolved = preference === 'system' ? (systemTheme.matches ? 'light' : 'dark') : preference;
    document.documentElement.dataset.adminTheme = resolved;
    document.querySelectorAll('button[data-admin-theme]').forEach((button) => button.classList.toggle('ativo', button.dataset.adminTheme === preference));
};
applyAdminTheme();
document.querySelectorAll('button[data-admin-theme]').forEach((button) => button.addEventListener('click', () => {
    localStorage.setItem(adminThemeKey, button.dataset.adminTheme);
    applyAdminTheme(button.dataset.adminTheme);
}));
systemTheme.addEventListener?.('change', () => {
    if ((localStorage.getItem(adminThemeKey) || 'system') === 'system') applyAdminTheme('system');
});

document.querySelectorAll('.menu-perfil,.menu-notificacoes').forEach((menu) => menu.addEventListener('toggle', () => {
    if (!menu.open) return;
    document.querySelectorAll('.menu-perfil[open],.menu-notificacoes[open]').forEach((other) => {
        if (other !== menu) other.removeAttribute('open');
    });
}));

document.addEventListener('click', (event) => {
    document.querySelectorAll('.menu-perfil[open],.menu-notificacoes[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});

const adminSound = document.querySelector('[data-admin-sound]');
const adminSoundKey = 'mjdev-admin-sound';
const applyAdminSound = (enabled) => {
    adminSound?.classList.toggle('ativo', enabled);
    adminSound?.setAttribute('aria-pressed', String(enabled));
    if (adminSound) adminSound.title = enabled ? 'Som ativado' : 'Som desativado';
};
if (adminSound) {
    applyAdminSound(localStorage.getItem(adminSoundKey) === 'on');
    adminSound.addEventListener('click', () => {
        const enabled = adminSound.getAttribute('aria-pressed') !== 'true';
        localStorage.setItem(adminSoundKey, enabled ? 'on' : 'off');
        applyAdminSound(enabled);
    });
}

const adminSearch = document.querySelector('[data-admin-search]');
if (adminSearch) {
    const searchable = [...document.querySelectorAll('.conteudo tbody tr,.conteudo .linha-registro,.conteudo .pedido-kanban,.conteudo > article,.conteudo > .grade > article')];
    adminSearch.addEventListener('input', () => {
        const query = adminSearch.value.trim().toLocaleLowerCase('pt-BR');
        let matches = 0;
        searchable.forEach((element) => {
            const visible = !query || element.textContent.toLocaleLowerCase('pt-BR').includes(query);
            element.hidden = !visible;
            if (visible && query) matches += 1;
        });
        adminSearch.classList.toggle('sem-resultado', Boolean(query) && matches === 0);
    });
}

// O catálogo funcional mantém os formulários PHP, mas recebe o mesmo cabeçalho,
// busca e ação principal do protótipo aprovado. Nenhum dado demonstrativo é criado.
const catalogModules = {
    zones: ['Bairros e taxas', 'Gerencie regiões atendidas, taxas e pedidos mínimos.', 'Nova área de entrega'],
    coupons: ['Cupons', 'Crie campanhas com validade, limite de uso e pedido mínimo.', 'Novo cupom'],
    options: ['Grupos de opções', 'Organize adicionais e escolhas vinculadas aos produtos.', 'Novo grupo'],
    combos: ['Kits e combos', 'Monte ofertas usando produtos reais do seu catálogo.', 'Novo combo'],
};
const catalogSection = Object.keys(catalogModules).find((name) => document.body.classList.contains(`secao-${name}`));
if (catalogSection) {
    const content = document.querySelector('.conteudo');
    const firstCard = content?.querySelector(':scope > .cartao, :scope > .grade');
    if (content && firstCard && !content.querySelector('.introducao-catalogo')) {
        const [title, description, actionLabel] = catalogModules[catalogSection];
        const intro = document.createElement('div');
        intro.className = 'introducao-modulo introducao-catalogo';
        const copy = document.createElement('div');
        const eyebrow = document.createElement('span');
        eyebrow.className = 'sobretitulo';
        eyebrow.textContent = 'CATÁLOGO E OPERAÇÃO';
        const heading = document.createElement('h2');
        heading.textContent = title;
        const paragraph = document.createElement('p');
        paragraph.textContent = description;
        copy.append(eyebrow, heading, paragraph);
        const action = document.createElement('button');
        action.className = 'botao primario';
        action.type = 'button';
        action.textContent = `+ ${actionLabel}`;
        action.addEventListener('click', () => {
            firstCard.scrollIntoView({behavior: 'smooth', block: 'start'});
            firstCard.querySelector('input:not([type="hidden"]),select,textarea')?.focus();
        });
        intro.append(copy, action);

        const toolbar = document.createElement('div');
        toolbar.className = 'barra-ferramentas ferramentas-catalogo';
        const searchLabel = document.createElement('label');
        searchLabel.className = 'busca';
        const searchIcon = document.createElement('span');
        searchIcon.textContent = '⌕';
        const search = document.createElement('input');
        search.type = 'search';
        search.placeholder = `Buscar em ${title.toLocaleLowerCase('pt-BR')}`;
        search.setAttribute('aria-label', search.placeholder);
        searchLabel.append(searchIcon, search);
        toolbar.append(searchLabel);
        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('pt-BR');
            content.querySelectorAll('.linha-registro').forEach((row) => {
                row.hidden = Boolean(query) && !row.textContent.toLocaleLowerCase('pt-BR').includes(query);
            });
        });
        content.insertBefore(toolbar, firstCard);
        content.insertBefore(intro, toolbar);
    }
}

// Mantém visíveis no protótipo funcional os módulos avançados já aprovados,
// sem fingir que integrações ou persistência ainda inexistentes estão ativas.
const settingsContent = document.querySelector('.secao-settings .conteudo');
const securityCard = settingsContent?.querySelector('#seguranca-conta');
const channelsCard = settingsContent?.querySelector('#canais-venda');
if (settingsContent && channelsCard && !settingsContent.querySelector('#banner-vitrine')) {
    const heroCard = document.createElement('article');
    heroCard.className = 'cartao';
    heroCard.id = 'banner-vitrine';
    const eyebrow = document.createElement('span');
    eyebrow.className = 'sobretitulo';
    eyebrow.textContent = 'DESCRIÇÃO E BANNER DA VITRINE';
    const heading = document.createElement('h2');
    heading.textContent = 'Mensagem principal do catálogo';
    const paragraph = document.createElement('p');
    paragraph.className = 'suave';
    paragraph.textContent = 'O catálogo compacto usa a imagem de banner cadastrada na identidade da loja. Os textos abaixo são mantidos para compatibilidade e não aparecem nesse layout.';
    const form = document.createElement('form');
    form.className = 'formulario';
    form.method = 'post';
    form.action = '/admin/hero/save';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = settingsContent.querySelector('input[name="_csrf"]')?.value || '';
    form.append(csrf);
    const field = (labelText, name, value, placeholder, maxLength) => {
        const label = document.createElement('label');
        label.textContent = labelText;
        const input = document.createElement('input');
        input.name = name;
        input.value = value || '';
        input.placeholder = placeholder;
        input.maxLength = maxLength;
        label.append(input);
        return label;
    };
    form.append(
        field('Título principal', 'hero_title', document.body.dataset.heroTitle, 'Ex.: Feito na brasa.', 120),
        field('Frase em destaque', 'hero_highlight', document.body.dataset.heroHighlight, 'Ex.: Marcado na memória.', 120),
        field('Texto do botão', 'hero_cta_text', document.body.dataset.heroCta, 'Ex.: Ver cardápio', 60),
    );
    const submit = document.createElement('button');
    submit.className = 'botao primario';
    submit.type = 'submit';
    submit.textContent = 'Salvar banner da vitrine';
    form.append(submit);
    heroCard.append(eyebrow, heading, paragraph, form);
    settingsContent.insertBefore(heroCard, channelsCard);
    const settingsNav = settingsContent.querySelector('.atalhos-configuracoes');
    if (settingsNav) {
        const link = document.createElement('a');
        link.href = '#banner-vitrine';
        link.textContent = 'Banner da vitrine';
        settingsNav.insertBefore(link, settingsNav.children[2] || null);
    }
}
if (settingsContent && securityCard && !settingsContent.querySelector('#recursos-planejados')) {
    const resources = [
        ['WhatsApp Cloud API', 'Notificações oficiais e modelos aprovados por estabelecimento.', 'Planejado'],
        ['Pagamento online dos pedidos', 'Cobrança de pedidos por Pix ou cartão, separada da assinatura da plataforma.', 'Planejado'],
        ['Relacionamento automático', 'Carrinho abandonado e retorno de clientes somente com consentimento.', 'Planejado'],
        ['Analytics com consentimento', 'Métricas externas condicionadas às escolhas de privacidade do visitante.', 'Planejado'],
        ['Multiunidade', 'Filiais com horários, estoque e equipes isolados.', 'Planejado'],
        ['Nota fiscal', 'Emissão por provedor compatível após validação fiscal.', 'Planejado'],
    ];
    const section = document.createElement('section');
    section.id = 'recursos-planejados';
    section.className = 'secao-recursos-planejados';
    const header = document.createElement('div');
    header.className = 'cabecalho-recursos-planejados';
    const copy = document.createElement('div');
    const eyebrow = document.createElement('span');
    eyebrow.className = 'sobretitulo';
    eyebrow.textContent = 'PRÓXIMOS RECURSOS';
    const heading = document.createElement('h2');
    heading.textContent = 'Em desenvolvimento';
    const paragraph = document.createElement('p');
    paragraph.textContent = 'Estes recursos ainda não estão disponíveis e não exigem configuração no EasyPanel neste momento.';
    copy.append(eyebrow, heading, paragraph);
    const badge = document.createElement('span');
    badge.className = 'etiqueta amarelo';
    badge.textContent = 'NÃO DISPONÍVEL';
    header.append(copy, badge);
    const grid = document.createElement('div');
    grid.className = 'grade tres-colunas grade-recursos-planejados';
    resources.forEach(([title, description, status]) => {
        const card = document.createElement('article');
        card.className = 'cartao recurso-planejado';
        const icon = document.createElement('span');
        icon.className = 'icone-recurso-planejado';
        icon.textContent = '◇';
        const cardTitle = document.createElement('h3');
        cardTitle.textContent = title;
        const cardDescription = document.createElement('p');
        cardDescription.textContent = description;
        const cardStatus = document.createElement('small');
        cardStatus.textContent = status;
        card.append(icon, cardTitle, cardDescription, cardStatus);
        grid.append(card);
    });
    section.append(header, grid);
    settingsContent.insertBefore(section, securityCard);
    const settingsNav = settingsContent.querySelector('.atalhos-configuracoes');
    if (settingsNav && !settingsNav.querySelector('a[href="#recursos-planejados"]')) {
        const link = document.createElement('a');
        link.href = '#recursos-planejados';
        link.textContent = 'Em desenvolvimento';
        settingsNav.insertBefore(link, settingsNav.lastElementChild);
    }
}

const activeResourcesLink = document.querySelector('.atalhos-configuracoes a[href="#recursos-ativos"]');
if (activeResourcesLink) activeResourcesLink.textContent = 'Recursos disponíveis';

document.querySelectorAll('.configuracoes-pagamento fieldset').forEach((fieldset) => {
    const toggle = fieldset.querySelector('.opcao-destaque input[type="checkbox"]');
    if (!toggle) return;
    const sync = () => fieldset.classList.toggle('configuracao-desativada', !toggle.checked);
    toggle.addEventListener('change', sync);
    sync();
});

const mobileSidebarQuery = window.matchMedia('(max-width: 820px)');
let menuOpener = null;

function syncMenuAccessibility(open = sidebar?.classList.contains('aberto') || false) {
    const mobile = mobileSidebarQuery.matches;
    sidebar?.setAttribute('aria-hidden', String(mobile && !open));
    openMenu?.setAttribute('aria-expanded', String(mobile && open));
    openMenu?.setAttribute('aria-controls', 'sidebar');
}

function setMenu(open) {
    if (!sidebar) return;
    if (open) menuOpener = document.activeElement;
    sidebar.classList.toggle('aberto', open);
    syncMenuAccessibility(open);
    if (open) closeMenu?.focus();
    else if (menuOpener instanceof HTMLElement && menuOpener.isConnected) menuOpener.focus();
}

syncMenuAccessibility();
mobileSidebarQuery.addEventListener?.('change', () => {
    if (!mobileSidebarQuery.matches) sidebar?.classList.remove('aberto');
    syncMenuAccessibility();
});

openMenu?.addEventListener('click', () => setMenu(true));
closeMenu?.addEventListener('click', () => setMenu(false));
menuBackdrop?.addEventListener('click', () => setMenu(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenu(false);
});

document.querySelectorAll('.grupo-navegacao > button').forEach((button) => button.addEventListener('click', () => {
    const group = button.closest('.grupo-navegacao');
    const open = !group.classList.contains('aberto');
    group.classList.toggle('aberto', open);
    button.setAttribute('aria-expanded', String(open));
    const submenu = document.getElementById(button.getAttribute('aria-controls') || '');
    submenu?.setAttribute('aria-hidden', String(!open));
}));

const rotulosOperacionais = {
    received: 'Recebido',
    confirmed: 'Confirmado',
    preparing: 'Em preparo',
    ready: 'Pronto',
    out_for_delivery: 'Saiu para entrega',
    delivered: 'Entregue',
    cancelled: 'Cancelado',
    rejected: 'Recusado',
    delivery: 'Entrega',
    pickup: 'Retirada',
    dine_in: 'Mesa',
    available: 'Disponível',
    occupied: 'Ocupada',
    inactive: 'Inativa',
};

document.querySelectorAll('.pedido-operacional .etiqueta,.mesa-cartao .etiqueta').forEach((elemento) => {
    const valor = elemento.textContent.trim();
    if (rotulosOperacionais[valor]) elemento.textContent = rotulosOperacionais[valor];
});

if (/^(Cozinha|Entregador)/.test(document.title)) {
    window.setInterval(() => {
        const editando = document.activeElement?.matches('input,select,textarea,button');
        if (document.visibilityState === 'visible' && !editando) window.location.reload();
    }, 30000);
}

const provisionalPassword = document.querySelector('input[name="password"]');
if (provisionalPassword?.closest('form[action="/admin/user/create"]')) {
    provisionalPassword.minLength = 15;
    provisionalPassword.maxLength = 128;
}

const posForm = document.querySelector('#posOrderForm');
if (posForm) {
    posForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = document.querySelector('#posOrderResult');
        const submit = posForm.querySelector('button[type="submit"]');
        const items = [...posForm.querySelectorAll('.pos-quantity')]
            .map((input) => ({product_id: Number(input.dataset.productId), quantity: Number(input.value)}))
            .filter((item) => item.quantity > 0);
        result.hidden = false;
        if (!items.length) {
            result.className = 'aviso error';
            result.textContent = 'Adicione pelo menos um produto.';
            return;
        }
        submit.disabled = true;
        result.className = 'aviso';
        result.textContent = 'Processando a venda…';
        try {
            const data = Object.fromEntries(new FormData(posForm));
            const response = await fetch(`/api/store/${encodeURIComponent(posForm.dataset.storeSlug)}/orders`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    items,
                    order_type: 'pickup',
                    customer_name: data.customer_name,
                    customer_phone: data.customer_phone,
                    customer_email: data.customer_email,
                    payment_method: data.payment_method,
                    _csrf: document.querySelector('input[name="_csrf"]')?.value || '',
                }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Não foi possível concluir a venda.');
            result.className = 'aviso success';
            result.textContent = `Venda ${payload.code} criada com sucesso. Total confirmado: ${(payload.total_cents / 100).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})}.`;
            posForm.reset();
            setTimeout(() => location.reload(), 1400);
        } catch (error) {
            result.className = 'aviso error';
            result.textContent = error.message;
        } finally {
            submit.disabled = false;
        }
    });
}

const serviceForm = document.querySelector('#serviceOrderForm');
if (serviceForm) {
    const tableSelect = serviceForm.querySelector('#serviceTable');
    const tableLabel = document.querySelector('#selectedTableLabel');
    document.querySelectorAll('.mesa-operacional[data-table-token]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.mesa-operacional.ativa').forEach((item) => item.classList.remove('ativa'));
            button.classList.add('ativa');
            if (tableSelect) {
                tableSelect.value = button.dataset.tableToken || '';
                tableSelect.dispatchEvent(new Event('change'));
            }
            document.querySelector('.painel-pedido-operacional')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
    });
    tableSelect?.addEventListener('change', () => {
        const option = tableSelect.options[tableSelect.selectedIndex];
        if (tableLabel) tableLabel.textContent = option?.value ? `· ${option.textContent.split(' · ')[0]}` : '· SELECIONE A MESA';
    });
    serviceForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = document.querySelector('#serviceOrderResult');
        const submit = serviceForm.querySelector('button[type="submit"]');
        const items = [...serviceForm.querySelectorAll('.service-quantity')]
            .map((input) => ({
                product_id: Number(input.dataset.productId),
                quantity: Number(input.value),
                option_item_ids: [...serviceForm.querySelectorAll(`[data-service-option-product="${Number(input.dataset.productId)}"]:checked`)].map((option) => Number(option.value)),
            }))
            .filter((item) => item.quantity > 0);
        result.hidden = false;
        if (!items.length) {
            result.className = 'aviso error completo';
            result.textContent = 'Adicione pelo menos um produto.';
            return;
        }
        const data = Object.fromEntries(new FormData(serviceForm));
        submit.disabled = true;
        result.className = 'aviso completo';
        result.textContent = 'Registrando pedido da mesa…';
        try {
            const response = await fetch(`/api/store/${encodeURIComponent(serviceForm.dataset.storeSlug)}/orders`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    items,
                    order_type: 'dine_in',
                    table_token: data.table_token,
                    customer_name: data.customer_name,
                    customer_phone: data.customer_phone,
                    payment_method: data.payment_method,
                    _csrf: document.querySelector('input[name="_csrf"]')?.value || '',
                }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error || 'Não foi possível criar o pedido da mesa.');
            result.className = 'aviso success completo';
            result.textContent = `Pedido ${payload.code} registrado. Total confirmado: ${(payload.total_cents / 100).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})}.`;
            serviceForm.reset();
            setTimeout(() => location.reload(), 1400);
        } catch (error) {
            result.className = 'aviso error completo';
            result.textContent = error.message;
        } finally {
            submit.disabled = false;
        }
    });
}

document.querySelectorAll('.copiar-link,[data-copy-value]').forEach((button) => button.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(button.dataset.copyValue || '');
        const previous = button.textContent;
        button.textContent = 'Copiado';
        setTimeout(() => { button.textContent = previous; }, 1400);
    } catch {
        window.prompt('Copie o link seguro:', button.dataset.copyValue || '');
    }
}));

document.querySelectorAll('.imprimir-pagina, button[onclick="window.print()"], button[onclick="window.print();"]').forEach((button) => {
    button.removeAttribute('onclick');
    button.addEventListener('click', () => window.print());
});

const qrContainers = [...document.querySelectorAll('[data-qr-value]')];
if (qrContainers.length) {
    const renderQrCodes = () => qrContainers.forEach((container) => {
        const qr = qrcode(0, 'M');
        qr.addData(container.dataset.qrValue, 'Byte');
        qr.make();
        container.innerHTML = qr.createSvgTag({cellSize: 5, margin: 4, scalable: true});
    });
    const script = document.createElement('script');
    script.src = '/assets/qrcode.js?v=1.4.4';
    script.onload = renderQrCodes;
    script.onerror = () => qrContainers.forEach((container) => { container.textContent = 'Não foi possível gerar o QR Code.'; });
    document.head.append(script);
}

document.querySelectorAll('.baixar-qr').forEach((button) => button.addEventListener('click', () => {
    const container = button.closest('.cartao')?.querySelector('.qr-code') || document.querySelector('.qr-code');
    const svg = container?.querySelector('svg');
    if (!svg) return;
    const source = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([source], {type: 'image/svg+xml;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'qr-code-cardapio.svg';
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}));

// Nos cartões de configuração, o próprio interruptor principal controla a
// exibição dos campos dependentes. Não há um segundo botão de expandir.
[
    'payment_pix_enabled','payment_cash_enabled','payment_debit_enabled','payment_credit_enabled',
    'service_fee_enabled','convenience_fee_enabled','cashback_enabled','loyalty_enabled',
].forEach((name) => {
    const control = document.querySelector(`input[name="${name}"]`);
    const card = control?.closest('fieldset');
    if (!control || !card) return;
    card.classList.add('configuracao-controlada');
    const sync = () => {
        card.classList.toggle('configuracao-desativada', !control.checked);
        control.setAttribute('aria-expanded', String(control.checked));
    };
    control.addEventListener('change', sync);
    sync();
});

const stockDialog = document.querySelector('#stockDialog');
document.querySelectorAll('[data-open-stock]').forEach((button) => button.addEventListener('click', () => openDialog(stockDialog)));
document.querySelectorAll('[data-close-stock]').forEach((button) => button.addEventListener('click', () => stockDialog?.close()));
stockDialog?.addEventListener('click', (event) => {
    if (event.target === stockDialog) stockDialog.close();
});

const categoryDialog = document.querySelector('#categoryDialog');
document.querySelectorAll('[data-open-category]').forEach((button) => button.addEventListener('click', () => openDialog(categoryDialog)));
document.querySelectorAll('[data-close-category]').forEach((button) => button.addEventListener('click', () => categoryDialog?.close()));
categoryDialog?.addEventListener('click', (event) => {
    if (event.target === categoryDialog) categoryDialog.close();
});

const productDialog = document.querySelector('#productDialog');
document.querySelectorAll('[data-open-product]').forEach((button) => button.addEventListener('click', () => openDialog(productDialog)));
document.querySelectorAll('[data-close-product]').forEach((button) => button.addEventListener('click', () => productDialog?.close()));

const visualProductDialog = document.querySelector('#visualProductDialog');
const visualProductForm = visualProductDialog?.querySelector('form');
const visualImageInput = visualProductForm?.querySelector('input[name="image"]');
const visualImagePreview = visualProductForm?.querySelector('[data-visual-image-preview]');
const visualUploadLabel = visualImageInput?.closest('.upload-editor-visual');
let visualProductInventory = {};
try {
    visualProductInventory = JSON.parse(document.querySelector('#visualProductInventoryData')?.textContent || '{}');
} catch {
    visualProductInventory = {};
}
document.querySelectorAll('[data-edit-visual-product]').forEach((card) => {
    const stock = visualProductInventory[card.dataset.productId || ''];
    const badge = document.createElement('span');
    badge.className = 'estado-estoque-produto';
    if (stock === null || stock === undefined) {
        badge.classList.add('estoque-livre');
        badge.textContent = 'Sem controle de estoque';
    } else if (Number(stock) === 0) {
        badge.classList.add('estoque-esgotado');
        badge.textContent = 'Esgotado';
    } else if (Number(stock) <= 5) {
        badge.classList.add('estoque-baixo');
        badge.textContent = `Baixo estoque · ${stock}`;
    } else {
        badge.classList.add('estoque-disponivel');
        badge.textContent = `${stock} em estoque`;
    }
    card.querySelector('.conteudo-produto-editor')?.append(badge);
    const wrapper = document.createElement('article');
    wrapper.className = 'cartao-produto-editor-rapido';
    card.parentElement?.insertBefore(wrapper, card);
    const selectionLabel = document.createElement('label');
    selectionLabel.className = 'selecao-produto-lote';
    const selectionInput = document.createElement('input');
    selectionInput.type = 'checkbox';
    selectionInput.value = card.dataset.productId || '';
    selectionInput.dataset.bulkProduct = card.dataset.productId || '';
    selectionInput.setAttribute('aria-label', `Selecionar ${card.dataset.name || 'produto'} para edição em lote`);
    const selectionText = document.createElement('span');
    selectionText.textContent = 'Selecionar';
    selectionLabel.append(selectionInput, selectionText);
    wrapper.append(selectionLabel, card);
    const quickForm = document.createElement('form');
    quickForm.className = 'edicao-rapida-produto';
    quickForm.method = 'post';
    quickForm.action = '/admin/product/quick-update';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = document.querySelector('input[name="_csrf"]')?.value || '';
    const productId = document.createElement('input');
    productId.type = 'hidden';
    productId.name = 'id';
    productId.value = card.dataset.productId || '';
    const priceLabel = document.createElement('label');
    priceLabel.append('Preço rápido');
    const priceInput = document.createElement('input');
    priceInput.name = 'price';
    priceInput.inputMode = 'decimal';
    priceInput.value = card.dataset.price || '';
    priceInput.required = true;
    priceInput.setAttribute('aria-label', `Preço de ${card.dataset.name || 'produto'}`);
    priceLabel.append(priceInput);
    const activeLabel = document.createElement('label');
    activeLabel.className = 'disponibilidade-rapida-produto';
    const activeInput = document.createElement('input');
    activeInput.type = 'checkbox';
    activeInput.name = 'active';
    activeInput.checked = card.dataset.active === '1';
    const activeText = document.createElement('span');
    activeText.textContent = 'Disponível';
    activeLabel.append(activeInput, activeText);
    const saveQuick = document.createElement('button');
    saveQuick.className = 'botao pequeno';
    saveQuick.type = 'submit';
    saveQuick.textContent = 'Atualizar';
    quickForm.append(csrf, productId, priceLabel, activeLabel, saveQuick);
    wrapper.append(quickForm);
});
const stockControlPresent = document.createElement('input');
stockControlPresent.type = 'hidden';
stockControlPresent.name = 'stock_control_present';
stockControlPresent.value = '1';
visualProductForm?.append(stockControlPresent);
const stockControl = document.createElement('div');
stockControl.className = 'controle-estoque-visual completo';
const stockToggleLabel = document.createElement('label');
stockToggleLabel.className = 'controle-status';
const stockToggle = document.createElement('input');
stockToggle.type = 'checkbox';
stockToggle.name = 'track_stock';
const stockToggleText = document.createElement('span');
stockToggleText.textContent = 'Controlar estoque deste produto';
stockToggleLabel.append(stockToggle, stockToggleText);
const stockQuantityLabel = document.createElement('label');
stockQuantityLabel.className = 'quantidade-estoque-visual';
stockQuantityLabel.append('Quantidade disponível');
const stockQuantity = document.createElement('input');
stockQuantity.type = 'number';
stockQuantity.name = 'stock_quantity';
stockQuantity.min = '0';
stockQuantity.max = '100000000';
stockQuantity.step = '1';
stockQuantity.value = '0';
stockQuantity.disabled = true;
stockQuantityLabel.append(stockQuantity);
const stockHelp = document.createElement('small');
stockHelp.textContent = 'Alterações de saldo ficam registradas no histórico de movimentações.';
stockControl.append(stockToggleLabel, stockQuantityLabel, stockHelp);
const visualActiveControl = visualProductForm?.querySelector('.campos-editor-visual .controle-status');
visualActiveControl?.insertAdjacentElement('beforebegin', stockControl);
stockToggle.addEventListener('change', () => {
    stockQuantity.disabled = !stockToggle.checked;
    if (stockToggle.checked) stockQuantity.focus();
});
const removeImageInput = document.createElement('input');
removeImageInput.type = 'hidden';
removeImageInput.name = 'remove_image';
removeImageInput.value = '0';
visualProductForm?.append(removeImageInput);
const imageUploadStatus = document.createElement('small');
imageUploadStatus.className = 'status-upload-editor';
imageUploadStatus.textContent = 'Recomendado: imagem quadrada, pelo menos 800 × 800 px.';
visualUploadLabel?.append(imageUploadStatus);
const removeVisualImageButton = document.createElement('button');
removeVisualImageButton.className = 'botao pequeno remover-imagem-editor';
removeVisualImageButton.type = 'button';
removeVisualImageButton.textContent = 'Remover foto';
removeVisualImageButton.hidden = true;
visualUploadLabel?.append(removeVisualImageButton);
const visualCategoryDialog = document.querySelector('#visualCategoryDialog');
document.querySelectorAll('[data-open-visual-category]').forEach((button) => button.addEventListener('click', () => openDialog(visualCategoryDialog)));
document.querySelectorAll('[data-close-visual-category]').forEach((button) => button.addEventListener('click', () => visualCategoryDialog?.close()));
visualCategoryDialog?.addEventListener('click', (event) => {
    if (event.target === visualCategoryDialog) visualCategoryDialog.close();
});
document.querySelectorAll('.item-organizador-categoria').forEach((item) => {
    const categoryId = item.querySelector('input[name="id"]')?.value || '';
    const actions = item.querySelector(':scope > div');
    if (!categoryId || !actions) return;
    const duplicateForm = document.createElement('form');
    duplicateForm.method = 'post';
    duplicateForm.action = '/admin/category/duplicate';
    duplicateForm.className = 'duplicar-categoria-editor';
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = document.querySelector('input[name="_csrf"]')?.value || '';
    const id = document.createElement('input');
    id.type = 'hidden';
    id.name = 'id';
    id.value = categoryId;
    const button = document.createElement('button');
    button.className = 'botao pequeno';
    button.type = 'submit';
    button.textContent = 'Duplicar categoria';
    duplicateForm.append(csrf, id, button);
    duplicateForm.addEventListener('submit', (event) => {
        if (!window.confirm('Duplicar esta categoria e todos os seus produtos como rascunho?')) event.preventDefault();
    });
    actions.append(duplicateForm);
});
const importCatalogButton = document.createElement('button');
importCatalogButton.className = 'botao';
importCatalogButton.type = 'button';
importCatalogButton.textContent = 'Importar CSV';
document.querySelector('.acoes-editor-visual')?.append(importCatalogButton);
const exportCatalogLink = document.createElement('a');
exportCatalogLink.className = 'botao';
exportCatalogLink.href = '/admin/export/catalog';
exportCatalogLink.textContent = 'Exportar CSV';
exportCatalogLink.setAttribute('download', '');
document.querySelector('.acoes-editor-visual')?.append(exportCatalogLink);
const importCatalogDialog = document.createElement('dialog');
importCatalogDialog.className = 'modal-administrativo modal-importacao-catalogo';
const importHeader = document.createElement('div');
importHeader.className = 'cabecalho-modal';
const importHeading = document.createElement('div');
const importEyebrow = document.createElement('span');
importEyebrow.className = 'sobretitulo';
importEyebrow.textContent = 'IMPORTAÇÃO GUIADA';
const importTitle = document.createElement('h2');
importTitle.textContent = 'Adicionar produtos por CSV';
importHeading.append(importEyebrow, importTitle);
const closeImport = document.createElement('button');
closeImport.className = 'icone';
closeImport.type = 'button';
closeImport.setAttribute('aria-label', 'Fechar importação');
closeImport.textContent = '×';
importHeader.append(importHeading, closeImport);
const importHelp = document.createElement('p');
importHelp.className = 'suave';
importHelp.textContent = 'Use ponto e vírgula. Colunas obrigatórias: categoria, nome e preco. O arquivo pode ter até 300 produtos.';
const templateButton = document.createElement('button');
templateButton.className = 'botao pequeno';
templateButton.type = 'button';
templateButton.textContent = 'Baixar modelo CSV';
const uploadImportForm = document.createElement('form');
uploadImportForm.className = 'formulario formulario-importacao-catalogo';
uploadImportForm.method = 'post';
uploadImportForm.action = '/admin/product/import-preview';
uploadImportForm.enctype = 'multipart/form-data';
const importCsrf = document.createElement('input');
importCsrf.type = 'hidden';
importCsrf.name = '_csrf';
importCsrf.value = document.querySelector('input[name="_csrf"]')?.value || '';
const importFileLabel = document.createElement('label');
importFileLabel.append('Arquivo CSV');
const importFile = document.createElement('input');
importFile.type = 'file';
importFile.name = 'catalog_csv';
importFile.accept = '.csv,text/csv';
importFile.required = true;
importFileLabel.append(importFile);
const previewImportButton = document.createElement('button');
previewImportButton.className = 'botao primario';
previewImportButton.type = 'submit';
previewImportButton.textContent = 'Validar e visualizar';
uploadImportForm.append(importCsrf, importFileLabel, previewImportButton);
importCatalogDialog.append(importHeader, importHelp, templateButton, uploadImportForm);
document.body.append(importCatalogDialog);
importCatalogButton.addEventListener('click', () => openDialog(importCatalogDialog));
closeImport.addEventListener('click', () => importCatalogDialog.close());
importCatalogDialog.addEventListener('click', (event) => { if (event.target === importCatalogDialog) importCatalogDialog.close(); });
templateButton.addEventListener('click', () => {
    const content = '\uFEFFcategoria;nome;descricao;preco;estoque;ativo\nLanches;Produto exemplo;Descrição do produto;19,90;10;nao\n';
    const url = URL.createObjectURL(new Blob([content], {type: 'text/csv;charset=utf-8'}));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'modelo-catalogo.csv';
    link.click();
    URL.revokeObjectURL(url);
});
let catalogImportPreview = null;
try {
    catalogImportPreview = JSON.parse(document.querySelector('#catalogImportPreviewData')?.textContent || 'null');
} catch {
    catalogImportPreview = null;
}
if (catalogImportPreview?.token && Array.isArray(catalogImportPreview.rows)) {
    importTitle.textContent = 'Revise antes de importar';
    importHelp.textContent = `${catalogImportPreview.rows.length} produto(s) validado(s). Confira a prévia antes de confirmar.`;
    templateButton.hidden = true;
    uploadImportForm.hidden = true;
    const previewTable = document.createElement('div');
    previewTable.className = 'tabela-previa-importacao';
    catalogImportPreview.rows.slice(0, 50).forEach((row) => {
        const item = document.createElement('div');
        const identity = document.createElement('span');
        const name = document.createElement('strong');
        name.textContent = row.name;
        const category = document.createElement('small');
        category.textContent = row.category;
        identity.append(name, category);
        const price = document.createElement('b');
        price.textContent = (Number(row.price_cents) / 100).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
        const status = document.createElement('em');
        status.textContent = row.active ? 'Será publicado' : 'Rascunho';
        item.append(identity, price, status);
        previewTable.append(item);
    });
    if (catalogImportPreview.rows.length > 50) {
        const remaining = document.createElement('p');
        remaining.className = 'suave';
        remaining.textContent = `Mais ${catalogImportPreview.rows.length - 50} produto(s) foram validados.`;
        previewTable.append(remaining);
    }
    const previewActions = document.createElement('div');
    previewActions.className = 'acoes-previa-importacao';
    const confirmForm = document.createElement('form');
    confirmForm.method = 'post';
    confirmForm.action = '/admin/product/import-confirm';
    const confirmCsrf = importCsrf.cloneNode();
    const importToken = document.createElement('input');
    importToken.type = 'hidden';
    importToken.name = 'import_token';
    importToken.value = catalogImportPreview.token;
    const confirmButton = document.createElement('button');
    confirmButton.className = 'botao primario';
    confirmButton.type = 'submit';
    confirmButton.textContent = 'Confirmar importação';
    confirmForm.append(confirmCsrf, importToken, confirmButton);
    const cancelForm = document.createElement('form');
    cancelForm.method = 'post';
    cancelForm.action = '/admin/product/import-cancel';
    const cancelCsrf = importCsrf.cloneNode();
    const cancelButton = document.createElement('button');
    cancelButton.className = 'botao';
    cancelButton.type = 'submit';
    cancelButton.textContent = 'Cancelar';
    cancelForm.append(cancelCsrf, cancelButton);
    previewActions.append(cancelForm, confirmForm);
    importCatalogDialog.append(previewTable, previewActions);
    openDialog(importCatalogDialog);
}
const visualProductActions = visualProductDialog?.querySelector('[data-visual-product-actions]');
if (visualProductForm) {
    const primarySave = visualProductForm.querySelector('button[type="submit"]');
    const continueButton = document.createElement('button');
    continueButton.className = 'botao completo salvar-e-continuar';
    continueButton.type = 'submit';
    continueButton.name = 'continue_creating';
    continueButton.value = '1';
    continueButton.textContent = 'Salvar e cadastrar outro';
    primarySave?.insertAdjacentElement('afterend', continueButton);
}
if (visualProductActions) {
    [['up', 'Mover antes'], ['down', 'Mover depois']].forEach(([direction, label]) => {
        const button = document.createElement('button');
        button.className = 'botao';
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('click', async () => {
            const id = visualProductForm?.elements.id.value || '';
            if (!id) return;
            button.disabled = true;
            try {
                const response = await fetch('/admin/product/move', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || ''},
                    body: new URLSearchParams({id, direction}),
                });
                if (!response.ok) throw new Error('Não foi possível alterar a posição.');
                window.location.reload();
            } catch (error) {
                window.alert(error.message);
                button.disabled = false;
            }
        });
        visualProductActions.append(button);
    });
}
const resetVisualProductForm = () => {
    if (!visualProductForm) return;
    visualProductForm.querySelector('.aviso-copia-produto')?.remove();
    visualProductForm.reset();
    visualProductForm.elements.id.value = '';
    visualProductForm.elements.active.checked = true;
    stockToggle.checked = false;
    stockQuantity.value = '0';
    stockQuantity.disabled = true;
    removeImageInput.value = '0';
    removeVisualImageButton.hidden = true;
    imageUploadStatus.className = 'status-upload-editor';
    imageUploadStatus.textContent = 'Recomendado: imagem quadrada, pelo menos 800 × 800 px.';
    if (visualProductActions) visualProductActions.hidden = true;
    document.querySelector('#visualProductEyebrow').textContent = 'NOVO PRODUTO';
    document.querySelector('#visualProductTitle').textContent = 'Adicionar à vitrine';
    visualImagePreview.replaceChildren();
    const label = document.createElement('b');
    label.textContent = 'Adicionar foto';
    const help = document.createElement('small');
    help.textContent = 'JPG, PNG ou WebP · até 5 MB';
    visualImagePreview.append(label, help);
};
const showVisualImage = (source) => {
    if (!visualImagePreview) return;
    visualImagePreview.replaceChildren();
    if (!source) return;
    const image = document.createElement('img');
    image.src = source;
    image.alt = 'Prévia da imagem do produto';
    visualImagePreview.append(image);
    removeVisualImageButton.hidden = false;
};
const acceptVisualImage = (file) => {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        imageUploadStatus.className = 'status-upload-editor erro-upload-editor';
        imageUploadStatus.textContent = 'Use uma imagem JPG, PNG ou WebP.';
        return false;
    }
    if (file.size > 5 * 1024 * 1024) {
        imageUploadStatus.className = 'status-upload-editor erro-upload-editor';
        imageUploadStatus.textContent = 'A imagem ultrapassa o limite de 5 MB.';
        return false;
    }
    removeImageInput.value = '0';
    const source = URL.createObjectURL(file);
    showVisualImage(source);
    const probe = new Image();
    probe.onload = () => {
        const size = (file.size / 1024 / 1024).toLocaleString('pt-BR', {maximumFractionDigits: 2});
        const compact = probe.width < 800 || probe.height < 800;
        imageUploadStatus.className = `status-upload-editor${compact ? ' alerta-upload-editor' : ''}`;
        imageUploadStatus.textContent = `${probe.width} × ${probe.height} px · ${size} MB${compact ? ' · pode perder qualidade na vitrine' : ' · boa resolução'}`;
        URL.revokeObjectURL(source);
    };
    probe.src = source;
    return true;
};
document.querySelectorAll('[data-open-visual-product]').forEach((button) => button.addEventListener('click', () => {
    resetVisualProductForm();
    openDialog(visualProductDialog);
}));
const openVisualProductEditor = (button) => {
    if (!visualProductForm) return;
    resetVisualProductForm();
    visualProductForm.elements.id.value = button.dataset.productId || '';
    visualProductForm.elements.category_id.value = button.dataset.categoryId || '';
    visualProductForm.elements.name.value = button.dataset.name || '';
    visualProductForm.elements.description.value = button.dataset.description || '';
    visualProductForm.elements.price.value = button.dataset.price || '';
    visualProductForm.elements.active.checked = button.dataset.active === '1';
    const savedStock = visualProductInventory[button.dataset.productId || ''];
    stockToggle.checked = savedStock !== null && savedStock !== undefined;
    stockQuantity.disabled = !stockToggle.checked;
    stockQuantity.value = stockToggle.checked ? String(savedStock) : '0';
    removeImageInput.value = '0';
    visualProductActions?.querySelectorAll('input[name="id"]').forEach((input) => { input.value = button.dataset.productId || ''; });
    const toggleInput = visualProductActions?.querySelector('input[name="active"]');
    const toggleLabel = visualProductActions?.querySelector('[data-toggle-visual-label]');
    if (toggleInput) toggleInput.value = button.dataset.active === '1' ? '0' : '1';
    if (toggleLabel) toggleLabel.textContent = button.dataset.active === '1' ? 'Pausar na vitrine' : 'Publicar na vitrine';
    if (visualProductActions) visualProductActions.hidden = false;
    document.querySelector('#visualProductEyebrow').textContent = 'EDITAR PRODUTO';
    document.querySelector('#visualProductTitle').textContent = button.dataset.name || 'Editar produto';
    showVisualImage(button.dataset.image || '');
    openDialog(visualProductDialog);
};
document.querySelectorAll('[data-edit-visual-product]').forEach((button) => button.addEventListener('click', () => openVisualProductEditor(button)));

const liveStorefrontEditor = document.querySelector('[data-live-storefront-editor]');
const liveStorefrontFrame = liveStorefrontEditor?.querySelector('[data-live-storefront-frame]');
const liveStorefrontDevice = liveStorefrontEditor?.querySelector('[data-live-device]');
const liveStorefrontStatus = liveStorefrontEditor?.querySelector('[data-live-status]');
let liveStorefrontAutomaticTheme = 'dark';
const setPressedControl = (selector, activeButton) => {
    liveStorefrontEditor?.querySelectorAll(selector).forEach((button) => {
        const active = button === activeButton;
        button.classList.toggle('ativo', active);
        button.setAttribute('aria-pressed', String(active));
    });
};
const connectLiveStorefront = () => {
    if (!liveStorefrontFrame?.contentDocument) return;
    const previewDocument = liveStorefrontFrame.contentDocument;
    liveStorefrontAutomaticTheme = previewDocument.documentElement.dataset.defaultTheme || (previewDocument.documentElement.dataset.theme === 'light' ? 'light' : 'dark');
    const editorStyle = previewDocument.createElement('style');
    editorStyle.dataset.adminLiveEditor = 'true';
    editorStyle.textContent = '[data-catalog-product]{cursor:pointer!important;outline:2px solid transparent;outline-offset:3px;position:relative;transition:outline-color .18s,transform .18s}[data-catalog-product]:hover,[data-catalog-product]:focus-visible{outline-color:#f26b21!important;transform:translateY(-2px)}[data-catalog-product]::after{content:"Editar";position:absolute;right:10px;top:10px;z-index:5;padding:7px 10px;border-radius:999px;background:#111;color:#fff;font:700 11px/1 sans-serif;box-shadow:0 6px 20px #0005}.add-product{pointer-events:none!important}';
    previewDocument.head.append(editorStyle);
    const products = [...previewDocument.querySelectorAll('[data-catalog-product]')];
    products.forEach((product) => {
        const productId = product.querySelector('.add-product[data-id]')?.dataset.id || '';
        const name = product.querySelector('h3')?.textContent?.trim() || 'produto';
        product.tabIndex = 0;
        product.setAttribute('role', 'button');
        product.setAttribute('aria-label', `Editar ${name}`);
        const openFromPreview = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const editorCard = [...document.querySelectorAll('[data-edit-visual-product]')].find((card) => card.dataset.productId === productId);
            if (!editorCard) {
                if (liveStorefrontStatus) liveStorefrontStatus.textContent = 'Este item não está disponível para edição nesta sessão. Atualize a prévia.';
                return;
            }
            openVisualProductEditor(editorCard);
        };
        product.addEventListener('click', openFromPreview, true);
        product.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') openFromPreview(event);
        });
    });
    if (liveStorefrontStatus) liveStorefrontStatus.textContent = products.length ? `${products.length} produto(s) conectado(s). Clique em um item para editar.` : 'A vitrine está conectada. Crie a primeira categoria e adicione um produto.';
};
liveStorefrontFrame?.addEventListener('load', connectLiveStorefront);
if (liveStorefrontFrame?.contentDocument?.readyState === 'complete') connectLiveStorefront();
liveStorefrontEditor?.querySelectorAll('[data-live-viewport]').forEach((button) => button.addEventListener('click', () => {
    const mobile = button.dataset.liveViewport === 'mobile';
    liveStorefrontDevice?.classList.toggle('modo-celular', mobile);
    setPressedControl('[data-live-viewport]', button);
    if (liveStorefrontStatus) liveStorefrontStatus.textContent = mobile ? 'Prévia em celular.' : 'Prévia em computador.';
}));
liveStorefrontEditor?.querySelectorAll('[data-live-theme]').forEach((button) => button.addEventListener('click', () => {
    const requestedTheme = button.dataset.liveTheme || 'auto';
    const previewDocument = liveStorefrontFrame?.contentDocument;
    if (!previewDocument) return;
    previewDocument.documentElement.dataset.theme = requestedTheme === 'auto' ? liveStorefrontAutomaticTheme : requestedTheme;
    setPressedControl('[data-live-theme]', button);
    if (liveStorefrontStatus) liveStorefrontStatus.textContent = requestedTheme === 'auto' ? 'Tema automático do modelo aplicado à prévia.' : `Tema ${requestedTheme === 'light' ? 'claro' : 'escuro'} aplicado somente à prévia.`;
}));
liveStorefrontEditor?.querySelector('[data-live-refresh]')?.addEventListener('click', () => {
    if (liveStorefrontStatus) liveStorefrontStatus.textContent = 'Atualizando a vitrine real…';
    liveStorefrontFrame?.contentWindow?.location.reload();
});
const requestedVisualProduct = new URLSearchParams(window.location.search).get('edit');
if (requestedVisualProduct && /^\d+$/.test(requestedVisualProduct)) {
    const duplicatedProduct = [...document.querySelectorAll('[data-edit-visual-product]')].find((button) => button.dataset.productId === requestedVisualProduct);
    if (duplicatedProduct) {
        duplicatedProduct.click();
        document.querySelector('#visualProductEyebrow').textContent = 'CÓPIA PARA REVISÃO';
        document.querySelector('#visualProductTitle').textContent = 'Finalize o novo produto';
        const guidance = document.createElement('div');
        guidance.className = 'aviso aviso-copia-produto';
        guidance.textContent = 'A cópia está inativa. Revise o nome e o preço, escolha uma nova foto e marque a opção de exibição quando estiver pronta.';
        visualProductForm?.prepend(guidance);
        const nameField = visualProductForm?.elements.name;
        nameField?.focus();
        nameField?.select();
    }
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('edit');
    window.history.replaceState({}, '', cleanUrl);
}
const requestedVisualCategory = new URLSearchParams(window.location.search).get('add');
if (requestedVisualCategory && /^\d+$/.test(requestedVisualCategory) && visualProductForm) {
    const matchingCategory = [...visualProductForm.elements.category_id.options].some((option) => option.value === requestedVisualCategory);
    if (matchingCategory) {
        resetVisualProductForm();
        visualProductForm.elements.category_id.value = requestedVisualCategory;
        openDialog(visualProductDialog);
        document.querySelector('#visualProductEyebrow').textContent = 'CADASTRO EM SEQUÊNCIA';
        document.querySelector('#visualProductTitle').textContent = 'Adicionar outro produto';
        visualProductForm.elements.name.focus();
    }
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('add');
    window.history.replaceState({}, '', cleanUrl);
}
document.querySelectorAll('[data-close-visual-product]').forEach((button) => button.addEventListener('click', () => visualProductDialog?.close()));
visualProductDialog?.addEventListener('click', (event) => {
    if (event.target === visualProductDialog) visualProductDialog.close();
});
visualImageInput?.addEventListener('change', () => {
    const file = visualImageInput.files?.[0];
    if (file && !acceptVisualImage(file)) visualImageInput.value = '';
});
['dragenter', 'dragover'].forEach((eventName) => visualImagePreview?.addEventListener(eventName, (event) => {
    event.preventDefault();
    visualImagePreview.classList.add('recebendo-imagem');
}));
['dragleave', 'drop'].forEach((eventName) => visualImagePreview?.addEventListener(eventName, (event) => {
    event.preventDefault();
    visualImagePreview.classList.remove('recebendo-imagem');
}));
visualImagePreview?.addEventListener('drop', (event) => {
    const file = event.dataTransfer?.files?.[0];
    if (!file || !acceptVisualImage(file)) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    visualImageInput.files = transfer.files;
});
removeVisualImageButton.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    visualImageInput.value = '';
    removeImageInput.value = visualProductForm?.elements.id.value ? '1' : '0';
    visualImagePreview.replaceChildren();
    const label = document.createElement('b');
    label.textContent = 'Arraste uma imagem aqui';
    const help = document.createElement('small');
    help.textContent = 'ou clique para escolher';
    visualImagePreview.append(label, help);
    removeVisualImageButton.hidden = true;
    imageUploadStatus.className = 'status-upload-editor';
    imageUploadStatus.textContent = visualProductForm?.elements.id.value ? 'A foto atual será removida quando você salvar.' : 'Nenhuma foto selecionada.';
});

const visualCatalog = document.querySelector('.moldura-editor-visual');
if (visualCatalog) {
    const catalogProducts = [...visualCatalog.querySelectorAll('.cartao-produto-editor-rapido')];
    const visualEditorButton = (product) => product.querySelector('[data-edit-visual-product]');
    const bulkForm = document.createElement('form');
    bulkForm.className = 'acoes-lote-editor';
    bulkForm.method = 'post';
    bulkForm.action = '/admin/product/bulk-update';
    const bulkCsrf = document.createElement('input');
    bulkCsrf.type = 'hidden';
    bulkCsrf.name = '_csrf';
    bulkCsrf.value = document.querySelector('input[name="_csrf"]')?.value || '';
    const bulkIds = document.createElement('input');
    bulkIds.type = 'hidden';
    bulkIds.name = 'product_ids';
    bulkIds.value = '[]';
    const bulkCount = document.createElement('strong');
    bulkCount.textContent = 'Nenhum produto selecionado';
    const selectVisible = document.createElement('button');
    selectVisible.type = 'button';
    selectVisible.className = 'botao pequeno';
    selectVisible.textContent = 'Selecionar visíveis';
    const clearSelection = document.createElement('button');
    clearSelection.type = 'button';
    clearSelection.className = 'botao pequeno';
    clearSelection.textContent = 'Limpar seleção';
    const publishSelected = document.createElement('button');
    publishSelected.type = 'submit';
    publishSelected.name = 'bulk_action';
    publishSelected.value = 'publish';
    publishSelected.className = 'botao pequeno';
    publishSelected.textContent = 'Publicar';
    const pauseSelected = document.createElement('button');
    pauseSelected.type = 'submit';
    pauseSelected.name = 'bulk_action';
    pauseSelected.value = 'pause';
    pauseSelected.className = 'botao pequeno';
    pauseSelected.textContent = 'Pausar';
    const percentage = document.createElement('input');
    percentage.type = 'number';
    percentage.name = 'percentage';
    percentage.min = '-90';
    percentage.max = '500';
    percentage.step = '1';
    percentage.placeholder = '%';
    percentage.setAttribute('aria-label', 'Percentual de alteração dos preços selecionados');
    const adjustPrices = document.createElement('button');
    adjustPrices.type = 'submit';
    adjustPrices.name = 'bulk_action';
    adjustPrices.value = 'price_percent';
    adjustPrices.className = 'botao pequeno';
    adjustPrices.textContent = 'Aplicar %';
    bulkForm.append(bulkCsrf, bulkIds, bulkCount, selectVisible, clearSelection, publishSelected, pauseSelected, percentage, adjustPrices);
    const bulkSelectors = [...visualCatalog.querySelectorAll('[data-bulk-product]')];
    const updateBulkSelection = () => {
        const selected = bulkSelectors.filter((input) => input.checked);
        bulkIds.value = JSON.stringify(selected.map((input) => Number(input.value)));
        bulkCount.textContent = selected.length ? `${selected.length} produto${selected.length === 1 ? '' : 's'} selecionado${selected.length === 1 ? '' : 's'}` : 'Nenhum produto selecionado';
        [publishSelected, pauseSelected, adjustPrices].forEach((button) => { button.disabled = selected.length === 0; });
        catalogProducts.forEach((product) => product.classList.toggle('selecionado-lote', Boolean(product.querySelector('[data-bulk-product]')?.checked)));
    };
    bulkSelectors.forEach((input) => input.addEventListener('change', updateBulkSelection));
    selectVisible.addEventListener('click', () => {
        bulkSelectors.forEach((input) => { if (!input.closest('.cartao-produto-editor-rapido')?.hidden) input.checked = true; });
        updateBulkSelection();
    });
    clearSelection.addEventListener('click', () => {
        bulkSelectors.forEach((input) => { input.checked = false; });
        updateBulkSelection();
    });
    bulkForm.addEventListener('submit', (event) => {
        const actionLabel = event.submitter?.textContent || 'atualizar';
        if (!window.confirm(`${actionLabel} os produtos selecionados?`)) event.preventDefault();
    });
    updateBulkSelection();
    const filterBar = document.createElement('div');
    filterBar.className = 'filtros-editor-visual';
    filterBar.setAttribute('role', 'group');
    filterBar.setAttribute('aria-label', 'Filtrar produtos do catálogo');
    const searchBar = document.createElement('div');
    searchBar.className = 'busca-editor-visual';
    const searchLabel = document.createElement('label');
    searchLabel.setAttribute('for', 'visualCatalogSearch');
    searchLabel.textContent = 'Buscar produto ou categoria';
    const searchInput = document.createElement('input');
    searchInput.id = 'visualCatalogSearch';
    searchInput.type = 'search';
    searchInput.placeholder = 'Digite um nome ou categoria';
    searchInput.autocomplete = 'off';
    const clearSearch = document.createElement('button');
    clearSearch.type = 'button';
    clearSearch.className = 'limpar-busca-editor';
    clearSearch.textContent = 'Limpar';
    clearSearch.hidden = true;
    const searchResult = document.createElement('output');
    searchResult.className = 'resultado-busca-editor';
    searchResult.setAttribute('aria-live', 'polite');
    searchLabel.append(searchInput);
    searchBar.append(searchLabel, clearSearch, searchResult);
    const emptyFilter = document.createElement('p');
    emptyFilter.className = 'estado-vazio-filtro';
    emptyFilter.textContent = 'Nenhum produto corresponde a este filtro.';
    emptyFilter.hidden = true;
    const filterDefinitions = [
        ['all', 'Todos'],
        ['low', 'Baixo estoque'],
        ['out', 'Esgotados'],
        ['inactive', 'Inativos'],
    ];
    let activeVisualFilter = 'all';
    const normalizeVisualSearch = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
    const matchesVisualSearch = (product, query) => {
        if (!query) return true;
        const category = product.closest('.categoria-editor-visual')?.querySelector('h3')?.textContent || '';
        return normalizeVisualSearch(`${visualEditorButton(product)?.dataset.name || ''} ${category}`).includes(query);
    };
    const matchesVisualFilter = (product, filter) => {
        const editor = visualEditorButton(product);
        const stock = visualProductInventory[editor?.dataset.productId || ''];
        if (filter === 'low') return stock !== null && stock !== undefined && Number(stock) > 0 && Number(stock) <= 5;
        if (filter === 'out') return Number(stock) === 0 && stock !== null && stock !== undefined;
        if (filter === 'inactive') return editor?.dataset.active !== '1';
        return true;
    };
    const applyVisualFilter = (filter) => {
        activeVisualFilter = filter;
        const query = normalizeVisualSearch(searchInput.value);
        let visibleCount = 0;
        catalogProducts.forEach((product) => {
            const visible = matchesVisualFilter(product, filter) && matchesVisualSearch(product, query);
            product.hidden = !visible;
            const editor = visualEditorButton(product);
            if (editor) editor.draggable = filter === 'all' && query === '';
            if (editor) editor.title = editor.draggable ? 'Clique para editar ou arraste para reordenar' : 'Clique para editar';
            if (visible) visibleCount += 1;
        });
        visualCatalog.querySelectorAll('.categoria-editor-visual').forEach((category) => {
            category.hidden = !category.querySelector('.cartao-produto-editor-rapido:not([hidden])');
        });
        filterBar.querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', button.dataset.catalogFilter === filter ? 'true' : 'false'));
        emptyFilter.hidden = visibleCount !== 0;
        clearSearch.hidden = query === '';
        searchResult.textContent = `${visibleCount} produto${visibleCount === 1 ? '' : 's'} encontrado${visibleCount === 1 ? '' : 's'}`;
    };
    filterDefinitions.forEach(([value, label]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'filtro-editor-visual';
        button.dataset.catalogFilter = value;
        button.setAttribute('aria-pressed', value === 'all' ? 'true' : 'false');
        const count = catalogProducts.filter((product) => matchesVisualFilter(product, value)).length;
        button.textContent = `${label} · ${count}`;
        button.addEventListener('click', () => applyVisualFilter(value));
        filterBar.append(button);
    });
    searchInput.addEventListener('input', () => applyVisualFilter(activeVisualFilter));
    clearSearch.addEventListener('click', () => {
        searchInput.value = '';
        applyVisualFilter(activeVisualFilter);
        searchInput.focus();
    });
    visualCatalog.querySelector('.cabecalho-vitrine-editor')?.insertAdjacentElement('afterend', searchBar);
    searchBar.insertAdjacentElement('afterend', bulkForm);
    bulkForm.insertAdjacentElement('afterend', filterBar);
    filterBar.insertAdjacentElement('afterend', emptyFilter);
    applyVisualFilter('all');
    const status = document.createElement('output');
    status.className = 'aviso status-ordem-visual';
    status.hidden = true;
    emptyFilter.insertAdjacentElement('afterend', status);
    let draggedProduct = null;
    let wasDragged = false;
    const products = catalogProducts;
    products.forEach((product) => {
        const editor = visualEditorButton(product);
        if (!editor) return;
        editor.draggable = true;
        editor.title = 'Clique para editar ou arraste para reordenar';
        editor.addEventListener('dragstart', (event) => {
            draggedProduct = product;
            wasDragged = true;
            product.classList.add('arrastando');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', editor.dataset.productId || '');
        });
        product.addEventListener('dragover', (event) => {
            if (!draggedProduct || draggedProduct === product || draggedProduct.parentElement !== product.parentElement) return;
            event.preventDefault();
            const bounds = product.getBoundingClientRect();
            const after = event.clientY > bounds.top + bounds.height / 2 || (Math.abs(event.clientY - (bounds.top + bounds.height / 2)) < bounds.height / 4 && event.clientX > bounds.left + bounds.width / 2);
            product.parentElement.insertBefore(draggedProduct, after ? product.nextSibling : product);
        });
        editor.addEventListener('dragend', async () => {
            product.classList.remove('arrastando');
            draggedProduct = null;
            const order = [...visualCatalog.querySelectorAll('.cartao-produto-editor-rapido [data-edit-visual-product]')].map((item) => Number(item.dataset.productId));
            status.hidden = false;
            status.className = 'aviso status-ordem-visual';
            status.textContent = 'Salvando nova ordem…';
            try {
                const body = new URLSearchParams({order: JSON.stringify(order)});
                const response = await fetch('/admin/product/reorder', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '', Accept: 'application/json'},
                    body,
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.error || 'Não foi possível salvar a ordem.');
                status.className = 'aviso success status-ordem-visual';
                status.textContent = 'Ordem salva e aplicada à vitrine.';
                setTimeout(() => { status.hidden = true; }, 2200);
            } catch (error) {
                status.className = 'aviso error status-ordem-visual';
                status.textContent = error.message;
            }
            setTimeout(() => { wasDragged = false; }, 0);
        });
        editor.addEventListener('click', (event) => {
            if (wasDragged) event.stopImmediatePropagation();
        }, true);
    });
}

const areaDialog = document.getElementById('areaDialog');
const tableDialog = document.getElementById('tableDialog');
const sessionDialog = document.getElementById('sessionDialog');
document.querySelectorAll('[data-open-area]').forEach((button) => button.addEventListener('click', () => openDialog(areaDialog)));
document.querySelectorAll('[data-close-area]').forEach((button) => button.addEventListener('click', () => areaDialog?.close()));
document.querySelectorAll('[data-open-table]').forEach((button) => button.addEventListener('click', () => openDialog(tableDialog)));
document.querySelectorAll('[data-close-table]').forEach((button) => button.addEventListener('click', () => tableDialog?.close()));
document.querySelectorAll('[data-open-session]').forEach((button) => button.addEventListener('click', () => {
  const select = document.getElementById('sessionTable');
  if (select && button.dataset.tableId) select.value = button.dataset.tableId;
  openDialog(sessionDialog);
}));
document.querySelectorAll('[data-close-session]').forEach((button) => button.addEventListener('click', () => sessionDialog?.close()));
const expenseDialog = document.getElementById('expenseDialog');
document.querySelectorAll('[data-open-expense]').forEach((button) => button.addEventListener('click', () => openDialog(expenseDialog)));
document.querySelectorAll('[data-close-expense]').forEach((button) => button.addEventListener('click', () => expenseDialog?.close()));
const userDialog = document.getElementById('userDialog');
document.querySelectorAll('[data-open-user]').forEach((button) => button.addEventListener('click', () => openDialog(userDialog)));
document.querySelectorAll('[data-close-user]').forEach((button) => button.addEventListener('click', () => userDialog?.close()));
document.querySelectorAll('[data-show-qr], [data-show-edit]').forEach((button) => button.addEventListener('click', () => {
  const targetId = button.dataset.showQr || button.dataset.showEdit;
  const target = targetId ? document.getElementById(targetId) : null;
  if (target) target.hidden = !target.hidden;
}));
document.querySelectorAll('[data-salon-tab]').forEach((button) => button.addEventListener('click', () => {
  document.querySelectorAll('[data-salon-tab]').forEach((tab) => tab.classList.toggle('primario', tab === button));
  document.querySelectorAll('[data-salon-panel]').forEach((panel) => { panel.hidden = panel.dataset.salonPanel !== button.dataset.salonTab; });
}));

productDialog?.addEventListener('click', (event) => {
    if (event.target === productDialog) productDialog.close();
});

document.querySelectorAll('.busca-local').forEach((input) => input.addEventListener('input', () => {
    const container = input.closest('.conteudo')?.querySelector('.cartao-tabela-administrativa');
    const term = input.value.trim().toLocaleLowerCase('pt-BR');
    container?.querySelectorAll('tbody tr,.item-categoria-administrativa,.cartao-produto-administrativo').forEach((row) => {
        row.hidden = term !== '' && !row.textContent.toLocaleLowerCase('pt-BR').includes(term);
    });
}));

document.querySelectorAll('[data-table-filter]').forEach((button) => button.addEventListener('click', () => {
    const container = button.closest('.conteudo')?.querySelector('.cartao-tabela-administrativa');
    const active = button.getAttribute('aria-pressed') !== 'true';
    button.setAttribute('aria-pressed', String(active));
    button.classList.toggle('ativo', active);
    button.textContent = active ? '☷ Exibir atenção' : '☷ Filtros';
    container?.querySelectorAll('tbody tr,.item-categoria-administrativa,.cartao-produto-administrativo').forEach((row) => {
        const text = row.textContent.toLocaleLowerCase('pt-BR');
        row.hidden = active && !['baixo','esgotado','inativo'].some((status) => text.includes(status));
    });
}));

document.querySelectorAll('[data-order-filter]').forEach((button) => button.addEventListener('click', () => {
    const rows = button.closest('.conteudo')?.querySelectorAll('.cartao-tabela-administrativa tbody tr[data-order-terminal]');
    const active = button.getAttribute('aria-pressed') !== 'true';
    button.setAttribute('aria-pressed', String(active));
    button.classList.toggle('ativo', active);
    button.textContent = active ? '☷ Somente ativos' : '☷ Filtros';
    rows?.forEach((row) => { row.hidden = active && row.dataset.orderTerminal === '1'; });
}));

document.querySelectorAll('[data-hours-table]').forEach((form) => {
    const rows = form.querySelectorAll('.linha-horario');
    const syncRow = (row) => {
        const closed = row.querySelector('input[type="checkbox"]')?.checked === true;
        row.classList.toggle('dia-fechado', closed);
        row.querySelectorAll('input[type="time"]').forEach((input) => {
            input.disabled = closed;
            input.setAttribute('aria-disabled', String(closed));
        });
    };
    rows.forEach((row) => {
        row.querySelector('input[type="checkbox"]')?.addEventListener('change', () => syncRow(row));
        syncRow(row);
    });
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[type="time"]').forEach((input) => { input.disabled = false; });
    });
});
