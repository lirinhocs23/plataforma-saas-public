<?php
declare(strict_types=1);

putenv('APP_ENV=development');
require __DIR__ . '/../src/bootstrap.php';

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    try {
        $callback();
        $tests[] = [$name, true, null];
    } catch (Throwable $error) {
        $tests[] = [$name, false, $error->getMessage()];
    }
};
$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$test('slugify normaliza acentos e separadores', static function () use ($expect): void {
    $expect(slugify('  Café & Pão  ') === 'cafe-pao', 'slug inesperado');
    $expect(slugify('***') === 'loja', 'fallback de slug inesperado');
});

$test('money_to_cents aceita valores monetários válidos', static function () use ($expect): void {
    $expect(money_to_cents('12,34') === 1234, 'valor com vírgula inválido');
    $expect(money_to_cents('10') === 1000, 'valor inteiro inválido');
    $expect(money_to_cents('0.5') === 50, 'valor decimal inválido');
});

$test('money_to_cents rejeita formatos e estouro', static function () use ($expect): void {
    $expect(money_to_cents('-1') === null, 'valor negativo aceito');
    $expect(money_to_cents('1.234,56') === null, 'formato ambíguo aceito');
    $expect(money_to_cents('999999999.99') === null, 'estouro aceito');
});

$test('valid_document valida CPF e CNPJ', static function () use ($expect): void {
    $expect(valid_document('529.982.247-25'), 'CPF válido rejeitado');
    $expect(valid_document('04.252.011/0001-10'), 'CNPJ válido rejeitado');
    $expect(!valid_document('111.111.111-11'), 'CPF repetido aceito');
});

$test('migration inicial contém isolamento e vínculos essenciais', static function () use ($expect): void {
    $sql = file_get_contents(ROOT_PATH . '/migrations/001_initial.sql') ?: '';
    foreach (['CREATE TABLE IF NOT EXISTS tenants', 'tenant_id BIGINT UNSIGNED NOT NULL', 'tracking_token_hash CHAR(64) NOT NULL UNIQUE', 'CONSTRAINT fk_orders_tenant'] as $fragment) {
        $expect(str_contains($sql, $fragment), "migration sem: {$fragment}");
    }
});

$test('acompanhamento possui expiração e revogação', static function () use ($expect): void {
    $sql = file_get_contents(ROOT_PATH . '/migrations/002_tracking_security.sql') ?: '';
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($sql, 'tracking_expires_at'), 'migration sem expiração');
    $expect(str_contains($sql, 'tracking_revoked_at'), 'migration sem revogação');
    $expect(str_contains($router, 'tracking_expires_at>NOW()'), 'rota não valida expiração');
    $expect(str_contains($router, 'tracking_revoked_at IS NULL'), 'rota não valida revogação');
});

$test('pedido exige produto e categoria ativos no mesmo tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router, 'c.id=p.category_id AND c.tenant_id=p.tenant_id'), 'consulta sem vínculo tenant da categoria');
    $expect(str_contains($router, 'p.active=1 AND c.active=1'), 'consulta aceita produto ou categoria inativos');
});

$test('painel e mutações administrativas exigem autenticação', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(substr_count($router, '$user=require_auth()') >= 2, 'rotas administrativas sem guarda completa');
    $expect(str_contains($router, "if (str_starts_with(\$path,'/admin/') && \$method === 'POST')"), 'mutações administrativas ausentes');
});

$test('módulos operacionais persistem dados por tenant', static function () use ($expect): void {
    $sql = file_get_contents(ROOT_PATH . '/migrations/003_complete_operations.sql') ?: '';
    foreach (['customers','stock_movements','option_groups','combos','dining_tables','product_reviews','expenses','privacy_requests'] as $table) {
        $expect(str_contains($sql, "CREATE TABLE IF NOT EXISTS {$table}"), "módulo sem tabela: {$table}");
    }
    $expect(substr_count($sql, 'tenant_id BIGINT UNSIGNED NOT NULL') >= 10, 'isolamento por tenant incompleto');
});

$test('painel funcional não carrega o JavaScript demonstrativo', static function () use ($expect): void {
    $admin = (file_get_contents(ROOT_PATH . '/src/views/admin.php') ?: '') . (file_get_contents(ROOT_PATH . '/src/views/admin-extra.php') ?: '');
    $expect(!str_contains($admin, 'outputs/admin.js'), 'painel ainda carrega simulação');
    $expect(!str_contains($admin, 'localStorage'), 'painel ainda persiste simulação no navegador');
});

$test('adicionais são recalculados e persistidos no servidor', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $sql = file_get_contents(ROOT_PATH . '/migrations/004_catalog_options.sql') ?: '';
    $expect(str_contains($router, 'option_item_ids'), 'API não recebe opções estruturadas');
    $expect(str_contains($router, 'Uma opção não pertence ao produto selecionado'), 'API não valida vínculo produto-opção');
    $expect(str_contains($router, '$unitPrice=(int)$product'), 'API não recalcula acréscimos');
    $expect(str_contains($sql, 'CREATE TABLE IF NOT EXISTS order_item_options'), 'opções do pedido não são persistidas');
});

$test('painel global exige função de administrador da plataforma', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $platform = file_get_contents(ROOT_PATH . '/src/views/platform.php') ?: '';
    $billingMigration = file_get_contents(ROOT_PATH . '/migrations/023_tenant_billing_accounts.sql') ?: '';
    $expect(str_contains($router, "require_auth(['platform_admin'])"), 'painel global sem guarda de função');
    $expect(str_contains($router, "if(\$requestedDestination===null&&\$user['role']==='platform_admin')redirect('/platform')"), 'administrador da plataforma não é direcionado ao painel global quando não solicitou outra rota autorizada');
    $expect(str_contains($router, 'suspended_at IS NULL'), 'loja suspensa continua pública');
    $expect(str_contains($router, "LEFT JOIN (SELECT tenant_id,COUNT(*) user_count FROM users GROUP BY tenant_id)"), 'métricas globais podem multiplicar vendas pela quantidade de usuários');
    foreach(['trial_expiring','subscribers','past_due','owner_email','billing_status'] as$rule)$expect(str_contains($router,$rule),'monitoramento master ausente: '.$rule);
    foreach(['CLIENTES E LOJAS','ASSINATURAS DA PLATAFORMA','Não representa receita da plataforma','Nenhuma cobrança é simulada'] as$rule)$expect(str_contains($platform,$rule),'painel master sem contexto: '.$rule);
    foreach(['CREATE TABLE IF NOT EXISTS tenant_billing_accounts','provider_subscription_ref','current_period_end','FOREIGN KEY (tenant_id)'] as$rule)$expect(str_contains($billingMigration,$rule),'estrutura de assinatura ausente: '.$rule);
    foreach(['allowedPlatformStatuses','COUNT(*) FROM tenants t','LIMIT :limit OFFSET :offset','PDO::PARAM_INT','/platform/tenant'] as$rule)$expect(str_contains($router,$rule),'consulta master sem busca, filtro ou paginação segura: '.$rule);
    foreach(['name="q"','name="status"','paginacao-plataforma','Nenhum estabelecimento encontrado','/platform/tenant?id='] as$rule)$expect(str_contains($platform,$rule),'listagem master incompleta: '.$rule);
    $detail = file_get_contents(ROOT_PATH . '/src/views/platform-tenant.php') ?: '';
    foreach(['DETALHE DO ESTABELECIMENTO','Estado financeiro','Acessos vinculados','Somente o webhook autenticado','Não representa receita da plataforma'] as$rule)$expect(str_contains($detail,$rule),'detalhe master incompleto: '.$rule);
    $expect(!str_contains($detail,'customer_name')&&!str_contains($detail,'customer_phone'),'detalhe master expõe dados de clientes finais sem necessidade');
});

$test('acessos rápidos possuem URLs próprias e continuam protegidos', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $expect(str_contains($bootstrap, "'kitchen'=>'/cozinha'") && str_contains($bootstrap, "'waiter'=>'/garcom'") && str_contains($bootstrap, "'courier'=>'/entregador'") && str_contains($bootstrap, "'pos'=>'/pdv'") && str_contains($bootstrap, "'salon'=>'/salao'"), 'rotas operacionais separadas não foram declaradas');
    $expect(str_contains($router, '$operationalRouteSections=array_flip(admin_operational_routes())'), 'aliases protegidos dos acessos rápidos ausentes');
    $expect(strpos($router, '$user=require_auth();') > strpos($router, '$operationalRouteSections=array_flip(admin_operational_routes())'), 'alias operacional contorna autenticação');
});

$test('login de funcionário direciona para o painel da própria função', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $team=file_get_contents(ROOT_PATH.'/src/views/team.php')?:'';
    foreach(['kitchen'=>'/cozinha','waiter'=>'/garcom','courier'=>'/entregador','pos'=>'/pdv'] as$role=>$path){
        $expect(str_contains($bootstrap,"'{$role}' => '{$path}'"),'destino de login ausente para '.$role);
    }
    $expect(str_contains($bootstrap,'function authorized_login_destination'),'destino operacional não é autorizado no servidor');
    $expect(str_contains($router,"\$_SESSION['login_destination']=\$path"),'rota operacional não preserva destino antes do login');
    $expect(str_contains($router,'authorized_login_destination($user'),'login não aplica destino autorizado');
    $expect(str_contains($auth,'loginDestination'),'tela de login não informa o acesso operacional');
    $expect(str_contains($team,'Login individual obrigatório'),'equipe não informa o painel individual');
});

$test('módulos administrativos respeitam o segmento no menu e no servidor', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $team = file_get_contents(ROOT_PATH . '/src/views/team.php') ?: '';
    $expect(str_contains($bootstrap, 'function admin_sections_for_business_type'), 'matriz de módulos por segmento ausente');
    $expect(str_contains($bootstrap, "array_diff(\$sections, ['salon', 'kitchen', 'waiter'])"), 'módulos de restaurante aparecem para outros segmentos');
    $expect(str_contains($bootstrap, 'admin_sections_for_context($role,$businessType)'), 'navegação não aplica contexto do segmento');
    $expect(str_contains($router, "admin_sections_for_context(\$user['role'],(string)\$user['business_type'])"), 'acesso direto não valida segmento no servidor');
    $expect(str_contains($router, '$restaurantActions='), 'mutações de salão não possuem bloqueio por segmento');
    $expect(substr_count($router, "staff_roles_for_business_type((string)\$user['business_type'])") >= 2, 'cadastro de equipe aceita função incompatível');
    $expect(str_contains($team, "staff_roles_for_business_type((string)(\$store['business_type']??'Outro'))"), 'formulário de equipe oferece funções incompatíveis');
    $expect(str_contains($router,"\$active&&!role_allowed_for_business_type((string)\$member['role'],(string)\$user['business_type'])"),'reativação permite função incompatível com o segmento');
});

$test('segmento operacional é persistido e separado do preset visual', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $migration=file_get_contents(ROOT_PATH.'/migrations/022_business_segments.sql')?:'';
    $expect(str_contains($bootstrap,'function business_segments')&&str_contains($bootstrap,'function business_segment_code'),'matriz estável de segmentos ausente');
    $expect(str_contains($migration,'business_segment_code'),'migração do código de segmento ausente');
    $expect(str_contains($router,"$"."action==='business-segment/save'")&&str_contains($router,"role IN ('waiter','kitchen')"),'alteração segura do segmento ausente');
    foreach(["table_sessions WHERE tenant_id=:tenant AND status IN ('open','awaiting_payment')","dining_tables WHERE tenant_id=:tenant AND status='occupied'","order_type='dine_in' AND status NOT IN ('delivered','cancelled','rejected')"] as$guard)$expect(str_contains($router,$guard),'proteção operacional ausente: '.$guard);
    $expect(str_contains($router,"SELECT business_type FROM tenants WHERE id=:tenant FOR UPDATE"),'mudança de segmento não bloqueia o tenant durante a validação');
    $expect(str_contains($settings,'action="/admin/business-segment/save"')&&str_contains($settings,'apply_recommended_preset'),'configuração de segmento não está separada do preset');
    $expect(str_contains($settings,"in_array(\$user['role'],['platform_admin','owner'],true)"),'formulário de segmento não está limitado ao proprietário');
});

$test('tema claro alcança módulos administrativos e acessos operacionais', static function () use ($expect): void {
    $css=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    $js=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['body','.coluna-pedidos','.cartao-produto-administrativo','.cabecalho-recursos-planejados','.cartao-qr-impressao','.cabecalho-operacional','.mesa-operacional','.aviso','.aviso.error','th','tr:hover td','.botao-status-loja.fechado','input'] as$selector){
        $expect(str_contains($css,'html[data-admin-theme="light"] '.$selector),'tema claro ausente em '.$selector);
    }
    $expect(str_contains($css,'--border:#dccdc3')&&str_contains($css,'--soft:#f5ece5'),'tokens auxiliares do tema claro não foram redefinidos');
    $expect(str_contains($js,"document.documentElement.dataset.adminTheme = resolved"),'tema escolhido não é aplicado no elemento raiz');
    foreach(glob(ROOT_PATH.'/src/views/*.php')?:[] as$viewFile){
        $view=file_get_contents($viewFile)?:'';
        if(str_contains($view,'/assets/admin.css'))$expect(str_contains($view,'/assets/admin.css?v=20260903-review1'),'view administrativa mantém CSS antigo: '.basename($viewFile));
    }
});

$test('interfaces preservam foco visível e redução de movimento', static function () use ($expect): void {
    foreach(['admin.css','loja.css','app.css'] as$file){
        $css=file_get_contents(ROOT_PATH.'/public/assets/'.$file)?:'';
        $expect(str_contains($css,':focus-visible'),'foco de teclado ausente em '.$file);
        $expect(str_contains($css,'prefers-reduced-motion:reduce'),'redução de movimento ausente em '.$file);
        $expect(str_contains($css,'outline-offset'),'foco sem afastamento visual em '.$file);
    }
    $admin=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    $expect(str_contains($admin,'.busca-global:focus-within')&&str_contains($admin,'input[type="checkbox"]:focus-visible'),'controles administrativos não possuem foco contextual');
});

$test('menus e gavetas expõem estado semântico e restauram o foco', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $storeJs=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    foreach(['aria-controls="<?= e($submenuId) ?>"','aria-current="page"','aria-hidden="<?= $open?\'false\':\'true\' ?>"'] as$semantic){
        $expect(str_contains($bootstrap,$semantic),'semântica do menu administrativo ausente: '.$semantic);
    }
    foreach(['aria-controls="mobileNav"','aria-expanded="false"','role="dialog"','aria-labelledby="cartTitle"'] as$semantic){
        $expect(str_contains($store,$semantic),'semântica da vitrine ausente: '.$semantic);
    }
    $expect(str_contains($adminJs,"submenu?.setAttribute('aria-hidden', String(!open))"),'submenu não sincroniza o estado acessível');
    foreach(["menuButton?.setAttribute('aria-expanded', String(open))","cartDrawer.setAttribute('aria-hidden', 'false')","cartOpener.focus()"] as$behavior){
        $expect(str_contains($storeJs,$behavior),'controle de foco/estado ausente: '.$behavior);
    }
    $expect(str_contains($storeJs,"setMobileMenu(!mobileNav?.classList.contains('aberto'))"),'botão do menu móvel não alterna entre abrir e fechar');
});

$test('modais possuem nome acessível mensagens vivas e retorno de foco', static function () use ($expect): void {
    foreach(['admin-ui.js','store.js'] as$file){
        $js=file_get_contents(ROOT_PATH.'/public/assets/'.$file)?:'';
        foreach(["dialog.querySelector('h1,h2')","dialog.setAttribute('aria-labelledby', title.id)","dialogOpeners.set(dialog, document.activeElement)","dialog.addEventListener('close'","opener.focus()","notice.classList.contains('error') ? 'alert' : 'status'"] as$rule){
            $expect(str_contains($js,$rule),'acessibilidade de modal ausente em '.$file.': '.$rule);
        }
        $expect(!str_contains($js,'?.showModal()')&&!preg_match('/\$\([^\n]+\)\.showModal\(\)/',$js),'abertura de modal ignora o gerenciador acessível em '.$file);
    }
});

$test('formulários públicos possuem preenchimento seguro e erros associados', static function () use ($expect): void {
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $storeJs=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    foreach(['autocomplete="organization"','autocomplete="off" aria-describedby="documentHelp"','autocomplete="address-level2"','target="_blank" rel="noopener"'] as$rule){
        $expect(str_contains($auth,$rule),'semântica do cadastro ausente: '.$rule);
    }
    foreach(["checkoutFormElement.setAttribute('aria-describedby', 'checkoutError')","checkoutErrorElement.setAttribute('aria-live', 'assertive')","customer_phone: ['tel', 'tel']","address: ['shipping street-address', 'text']","trackingUrlField?.setAttribute('inputmode', 'url')"] as$rule){
        $expect(str_contains($storeJs,$rule),'semântica do checkout ausente: '.$rule);
    }
});

$test('LGPD lista de espera e avaliações possuem contexto acessível', static function () use ($expect): void {
    $privacy=file_get_contents(ROOT_PATH.'/src/views/privacy-request.php')?:'';
    $waitlist=file_get_contents(ROOT_PATH.'/src/views/waitlist.php')?:'';
    $review=file_get_contents(ROOT_PATH.'/src/views/review.php')?:'';
    foreach(['aria-labelledby="privacyTitle"','aria-describedby="privacyHelp"','autocomplete="name"','autocomplete="email"','id="notesHelp"'] as$rule){
        $expect(str_contains($privacy,$rule),'formulário LGPD sem contexto: '.$rule);
    }
    foreach(['aria-labelledby="waitlistTitle"','aria-describedby="waitlistHelp"','type="tel" inputmode="tel" autocomplete="tel"'] as$rule){
        $expect(str_contains($waitlist,$rule),'lista de espera sem contexto: '.$rule);
    }
    foreach(['aria-labelledby="reviewTitle"','aria-label="Nota para','aria-describedby="reviewHelp"','id="reviewHelp"'] as$rule){
        $expect(str_contains($review,$rule),'avaliação sem contexto: '.$rule);
    }
    foreach([$privacy,$waitlist,$review] as$page){
        $expect(str_contains($page,"role=\"<?= \$flash[0]==='error'?'alert':'status' ?>\""),'retorno público não diferencia erro e sucesso');
        $expect(str_contains($page,'app.css?v=20260826-responsivo1')&&str_contains($page,'loja.css?v=20260903-turnstile1'),'página pública usa CSS sem versão atual');
    }
});

$test('painel vitrine e acompanhamento permitem pular navegação', static function () use ($expect): void {
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $storeJs=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    $tracking=file_get_contents(ROOT_PATH.'/src/views/tracking.php')?:'';
    foreach([$adminJs,$storeJs] as$js){
        foreach(["className = 'atalho-conteudo'","tabIndex = -1","document.body.prepend(skipLink)"] as$rule)$expect(str_contains($js,$rule),'atalho dinâmico ausente: '.$rule);
    }
    foreach(['href="#conteudo-principal"','role="status" aria-live="polite"','id="conteudo-principal"','tabindex="-1"'] as$rule){
        $expect(str_contains($tracking,$rule),'acompanhamento sem landmark acessível: '.$rule);
    }
    foreach(['admin.css','loja.css','app.css'] as$file){
        $css=file_get_contents(ROOT_PATH.'/public/assets/'.$file)?:'';
        $expect(str_contains($css,'.atalho-conteudo')&&str_contains($css,'.somente-leitor'),'utilitários acessíveis ausentes em '.$file);
    }
});

$test('interfaces móveis preservam zoom toque e tabelas roláveis', static function () use ($expect): void {
    foreach(glob(ROOT_PATH.'/src/views/*.php')?:[] as$viewFile){
        $view=file_get_contents($viewFile)?:'';
        $expect(!preg_match('/user-scalable\s*=\s*no|maximum-scale\s*=\s*1/i',$view),'zoom bloqueado em '.basename($viewFile));
    }
    foreach(['admin.css','loja.css','app.css'] as$file){
        $css=file_get_contents(ROOT_PATH.'/public/assets/'.$file)?:'';
        $expect(str_contains($css,'@media(pointer:coarse)')&&str_contains($css,'min-height:44px'),'alvos de toque ausentes em '.$file);
        $expect(str_contains($css,'font-size:16px'),'campos móveis podem provocar zoom involuntário em '.$file);
    }
    $adminCss=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['overflow-x:auto','-webkit-overflow-scrolling:touch','overscroll-behavior-inline:contain'] as$rule)$expect(str_contains($adminCss,$rule),'rolagem de tabela ausente: '.$rule);
    foreach(["wrapper.tabIndex = 0","wrapper.setAttribute('role', 'region')"] as$rule)$expect(str_contains($adminJs,$rule),'tabela sem acesso por teclado: '.$rule);
});

$test('landing login e cadastro preservam navegação e validação acessíveis', static function () use ($expect): void {
    $landing=file_get_contents(ROOT_PATH.'/src/views/landing.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/login.css')?:'';
    $js=file_get_contents(ROOT_PATH.'/public/assets/auth.js')?:'';
    foreach([$landing,$auth] as$page){
        foreach(['class="atalho-conteudo"','id="conteudo-principal"','tabindex="-1"'] as$rule)$expect(str_contains($page,$rule),'navegação de autenticação ausente: '.$rule);
    }
    foreach([':focus-visible','@media(pointer:coarse)','min-height:44px','font-size:16px','prefers-reduced-motion:reduce'] as$rule)$expect(str_contains($css,$rule),'acessibilidade da landing ausente: '.$rule);
    foreach(["status?.setAttribute('role', 'status')","status?.setAttribute('aria-live', 'polite')"] as$rule)$expect(str_contains($js,$rule),'validação de senha não é anunciada: '.$rule);
});

$test('views públicas e administrativas usam assets versionados atuais', static function () use ($expect): void {
    foreach (glob(ROOT_PATH . '/src/views/*.php') ?: [] as $viewFile) {
        $view = file_get_contents($viewFile) ?: '';
        $name = basename($viewFile);
        $expect(!preg_match('/\/assets\/(?:admin|app|loja|login)\.css"/', $view), 'CSS sem versão em ' . $name);
        $expect(!preg_match('/\/assets\/(?:admin-ui|store|auth)\.js"/', $view), 'JavaScript sem versão em ' . $name);
        if (str_contains($view, '/assets/admin.css')) {
            $expect(str_contains($view, 'admin.css?v=20260903-review1'), 'CSS administrativo antigo em ' . $name);
            $expect(str_contains($view, 'admin-theme.js?v=20260826-tema1'), 'Tema administrativo não é aplicado antes do CSS em ' . $name);
        }
        if (str_contains($view, '/assets/admin-ui.js')) {
            $expect(str_contains($view, 'admin-ui.js?v=20260903-review1'), 'JavaScript administrativo antigo em ' . $name);
        }
    }
});

$test('tema claro administrativo é aplicado antes do CSS e cobre estados concluídos', static function () use ($expect): void {
    $themeJs=file_get_contents(ROOT_PATH.'/public/assets/admin-theme.js')?:'';
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $adminCss=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    foreach(["localStorage.getItem(key)","document.documentElement.dataset.adminTheme"] as$rule)$expect(str_contains($themeJs,$rule),'inicialização antecipada do tema ausente: '.$rule);
    $expect(str_contains($adminJs,"querySelectorAll('button[data-admin-theme]')"),'seletor de tema inclui elementos que não são controles');
    foreach(['.passo-configuracao.concluido{background:#e9f7ee','.acoes-rapidas .botao{background:var(--card)'] as$rule)$expect(str_contains($adminCss,$rule),'cobertura do tema claro ausente: '.$rule);
});

$test('menu administrativo móvel anuncia estado e devolve o foco', static function () use ($expect): void {
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach([
        "window.matchMedia('(max-width: 820px)')",
        "sidebar?.setAttribute('aria-hidden'",
        "openMenu?.setAttribute('aria-expanded'",
        "openMenu?.setAttribute('aria-controls', 'sidebar')",
        'closeMenu?.focus()',
        'menuOpener.focus()',
    ] as$rule)$expect(str_contains($adminJs,$rule),'estado acessível do menu administrativo ausente: '.$rule);
});

$test('catálogo adapta a linguagem ao segmento sem duplicar regras de negócio', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $admin = file_get_contents(ROOT_PATH . '/src/views/admin.php') ?: '';
    $manager = file_get_contents(ROOT_PATH . '/src/views/catalog-manager.php') ?: '';
    $store = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    foreach (['Alimentação','Moda e Vestuário','Informática','Farmácia'] as $segment) {
        $expect(str_contains($bootstrap, "'{$segment}' => ["), "vocabulário de catálogo ausente para {$segment}");
    }
    foreach (['options','option_group','option_item','bundles','bundle','category_example','details_placeholder','notes_placeholder'] as $term) {
        $expect(str_contains($bootstrap, "'{$term}' =>"), "termo adaptável ausente: {$term}");
    }
    $expect(str_contains($admin, 'catalog_vocabulary('), 'cadastro de produtos não usa vocabulário do segmento');
    $expect(str_contains($manager, 'catalog_vocabulary('), 'variações e kits não usam vocabulário do segmento');
    $expect(
        str_contains($store, '$reviewTitle=$isRestaurant?')
        && str_contains($store, "'Quem pediu, avaliou'")
        && str_contains($store, "'Quem comprou, avaliou'"),
        'vitrine não adapta linguagem de compra'
    );
    $expect(str_contains($manager, 'action="/admin/option/save"') && str_contains($manager, 'action="/admin/combo/save"'), 'adaptação visual duplicou ou removeu a persistência existente');
});

$test('estoque por variação usa SKU tenant e baixa transacional no servidor', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/018_option_inventory.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $view=file_get_contents(ROOT_PATH.'/src/views/variant-stock.php')?:'';
    foreach(['ADD COLUMN tenant_id','ADD COLUMN sku','ADD COLUMN stock_quantity','uq_option_items_tenant_sku','option_sku'] as$term)$expect(str_contains($migration,$term),'migration de estoque por variação ausente: '.$term);
    $expect(str_contains($bootstrap,"'variant-stock'=>'Estoque por variação'"),'menu não oferece estoque por variação');
    $expect(str_contains($router,"$"."action==='option-inventory/save'"),'ação de estoque da variação ausente');
    $expect(str_contains($router,'oi.tenant_id=:tenant'),'estoque da variação não está isolado pelo tenant');
    $expect(str_contains($router,'FOR UPDATE')&&str_contains($router,'$optionStockNeeded'),'checkout não bloqueia e agrega estoque de variações');
    $expect(str_contains($router,'stock_quantity=stock_quantity-:qty WHERE id=:option AND tenant_id=:tenant'),'baixa de variação não é protegida pelo tenant');
    $expect(str_contains($view,'action="/admin/option-inventory/save"')&&str_contains($view,'name="sku"')&&str_contains($view,'name="stock_quantity"'),'painel não permite manter SKU e estoque');
});

$test('combos e comandas possuem persistência e validação de tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $sql = file_get_contents(ROOT_PATH . '/migrations/006_combos_and_tables.sql') ?: '';
    foreach (['order_combo_items','table_sessions','table_session_orders'] as $table) $expect(str_contains($sql,"CREATE TABLE IF NOT EXISTS {$table}"),"tabela ausente: {$table}");
    $expect(str_contains($router, 'requestedComboQuantity'), 'API não agrega combos com segurança');
    $expect(str_contains($router, 'Estoque insuficiente para'), 'combo não valida estoque dos componentes');
    $expect(str_contains($router, "WHERE id=:id AND tenant_id=:tenant FOR UPDATE"), 'comanda sem bloqueio e isolamento');
});

$test('avaliações públicas exigem pedido entregue e produto comprado', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router,"o.status='delivered'"), 'avaliação aceita pedido não entregue');
    $expect(str_contains($router,'SELECT 1 FROM order_items WHERE order_id=:order AND product_id=:product'), 'avaliação aceita produto não comprado');
    $expect(str_contains($router,"rate_limit('review'"), 'avaliação pública sem limite de requisições');
});

$test('solicitações LGPD públicas são limitadas e isoladas pela loja', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router,"rate_limit('privacy-request:'"), 'formulário LGPD sem limite');
    $expect(str_contains($router,'INSERT INTO privacy_requests (tenant_id'), 'solicitação LGPD sem tenant');
    $expect(str_contains($router,"['access','correction','deletion','portability','revocation']"), 'tipos LGPD não validados');
});

$test('exportação de pedidos exige autenticação, período e tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router,"\$path==='/admin/export/orders'"), 'rota de exportação ausente');
    $expect(str_contains($router,"require_auth(['platform_admin','owner','manager'])"), 'exportação sem RBAC');
    $expect(str_contains($router,'WHERE tenant_id=:tenant AND created_at>=:from'), 'exportação sem isolamento/período');
    $expect(str_contains($router,"preg_match('/^[=+\\-@]/'"), 'CSV sem proteção contra fórmulas');
});

$test('entregador acessa e altera somente pedidos atribuídos', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router,"o.assigned_courier_id=:current_user"), 'listagem do entregador sem filtro de atribuição');
    $expect(str_contains($router,"(int)\$currentOrder['assigned_courier_id']!==(int)\$user['id']"), 'transição do entregador sem verificação de atribuição sob bloqueio');
    $expect(str_contains($router,"\$currentOrder['order_type']!=='delivery'"), 'entregador pode operar pedido que não é de entrega');
    $expect(str_contains($router,"role='courier' AND active=1"), 'atribuição aceita usuário que não é entregador ativo');
});

$test('funções operacionais têm transições e canais limitados no servidor', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    foreach (["'waiter'=>['received>confirmed']", "'kitchen'=>['confirmed>preparing','preparing>ready']", "'courier'=>['ready>out_for_delivery','out_for_delivery>delivered']"] as $rule) {
        $expect(str_contains($router, $rule), "regra operacional ausente: {$rule}");
    }
    $expect(str_contains($router,"\$currentOrder['order_type']!=='dine_in'"), 'garçom pode operar pedido fora do salão');
    $expect(str_contains($router,"\$currentOrder['order_type']!=='pickup'"), 'PDV pode operar pedido de outro canal');
});

$test('navegação e operação de salão respeitam a função autenticada', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    foreach (['dashboard','salon'] as $viewName) {
        $view = file_get_contents(ROOT_PATH . '/src/views/' . $viewName . '.php') ?: '';
        $expect(str_contains($view,'admin_navigation'), "menu não usa navegação RBAC centralizada: {$viewName}");
    }
    foreach (['operations','pos'] as $viewName) {
        $view = file_get_contents(ROOT_PATH . '/src/views/' . $viewName . '.php') ?: '';
        $expect(str_contains($view,'pagina-operacional'), "aplicativo operacional não usa casco independente: {$viewName}");
        $expect(!str_contains($view,'admin_navigation'), "aplicativo operacional ainda incorpora menu administrativo: {$viewName}");
    }
    $expect(str_contains($bootstrap,"'courier'=>['dashboard','orders','courier']"), 'menu do entregador contém módulos indevidos');
    $expect(str_contains($router,"['table-session/open','table-session/close']"), 'ações operacionais de comanda não possuem autorização explícita');
});

$test('acessos operacionais preservam URLs e casco visual independentes', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $operations = file_get_contents(ROOT_PATH . '/src/views/operations.php') ?: '';
    $pos = file_get_contents(ROOT_PATH . '/src/views/pos.php') ?: '';
    foreach (["'kitchen'=>'/cozinha'", "'waiter'=>'/garcom'", "'courier'=>'/entregador'", "'pos'=>'/pdv'"] as $route) {
        $expect(str_contains($bootstrap,$route), "URL operacional ausente: {$route}");
    }
    foreach ([$operations,$pos] as $view) {
        foreach (['pagina-operacional','botao-som','admin_notifications_menu','admin_profile_menu','csrf_token()'] as $rule) {
            $expect(str_contains($view,$rule), "regra visual/segura ausente no acesso operacional: {$rule}");
        }
    }
    foreach (['grade-garcom','mesa-operacional','estado-pedido-','statusLabels'] as $rule) {
        $expect(str_contains($operations,$rule), "paridade operacional ausente: {$rule}");
    }
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($operations,'name="return_to"'), 'ação operacional não preserva a tela independente');
    $expect(str_contains($router,"['/admin?section=orders','/cozinha','/garcom','/entregador','/pdv']"), 'retorno operacional não usa lista segura');
    $expect(str_contains($router,'redirect($returnTo)'), 'transição não retorna ao aplicativo operacional');
});

$test('painel usa uma única navegação visual agrupada', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $css = file_get_contents(ROOT_PATH . '/public/assets/admin.css') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/admin-ui.js') ?: '';
    foreach (['Dashboard','Estoque','Catálogo','Clientes','Vitrine','Operação','Relacionamento','Acessos Rápidos','Relatórios','Configurações'] as $group) $expect(str_contains($bootstrap,$group), "grupo de menu ausente: {$group}");
    $expect(str_contains($bootstrap,'admin_sections_for_role($role)'), 'menu não filtra seções pela função');
    $expect(str_contains($bootstrap,"'Acessos Rápidos'=>['icon'=>'⚡','sections'=>['kitchen','waiter','courier','pos','menu-preview','print-menu']]"), 'acessos rápidos não seguem o protótipo aprovado');
    $expect(str_contains($bootstrap,"\$quickLink?'target=\"_blank\" rel=\"noopener\"':''"), 'acessos rápidos não abrem em link independente');
    $expect(str_contains($css,'.grupo-navegacao.aberto .subnavegacao'), 'CSS do menu agrupado ausente');
    $expect(str_contains($css,'text-decoration: none'), 'links do menu preservam sublinhado do navegador');
    $expect(str_contains($script,".grupo-navegacao > button"), 'menu agrupado não possui interação');
});

$test('estrutura global aprovada está presente em todas as views administrativas', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $css = file_get_contents(ROOT_PATH . '/public/assets/admin.css') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/admin-ui.js') ?: '';
    foreach (['admin.php','admin-extra.php','catalog-manager.php','dashboard.php','inventory.php','orders.php','reports.php','salon.php','settings.php','team.php'] as $viewName) {
        $view = file_get_contents(ROOT_PATH . '/src/views/' . $viewName) ?: '';
        $expect(str_contains($view,'admin_navigation'), "navegação global ausente: {$viewName}");
        $expect(str_contains($view,'admin_store_card'), "estado da loja ausente: {$viewName}");
        $expect(str_contains($view,'admin_header_actions'), "ações do cabeçalho ausentes: {$viewName}");
    }
    foreach (['admin_notifications_menu','admin_profile_menu','data-admin-search','data-admin-sound'] as $component) $expect(str_contains($bootstrap,$component), "componente global ausente: {$component}");
    $expect(str_contains($css,'.painel-notificacoes'), 'painel visual de notificações ausente');
    $expect(str_contains($script,"document.querySelector('[data-admin-search]')"), 'busca global não possui interação');
});

$test('estoque movimentações e clientes usam módulos visuais com dados reais', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/inventory.php') ?: '';
    $expect(str_contains($router,"'stock','moves','customers'=>'inventory'"), 'módulos não usam a view visual dedicada');
    foreach (['/admin/stock/adjust','csrf_token()','stock_quantity','lifetime_value_cents','customerOrders','envoltorio-tabela','metricas-modulo'] as $rule) $expect(str_contains($view,$rule), "regra visual/funcional ausente no módulo: {$rule}");
    $expect(!str_contains($view,'R$ 432,00'), 'módulo de estoque contém valor demonstrativo do protótipo');
});

$test('status operacional da loja é alterado no servidor', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $dashboard = file_get_contents(ROOT_PATH . '/src/views/dashboard.php') ?: '';
    $expect(str_contains($router,"\$action==='store/status'"), 'rota protegida para alternar a loja está ausente');
    $expect(str_contains($router,'UPDATE tenants SET store_open=:open WHERE id=:tenant'), 'status da loja não é persistido com isolamento de tenant');
    $expect(str_contains($dashboard,'action="/admin/store/status"'), 'botão de status não envia a alteração ao servidor');
    $expect(str_contains($dashboard,'csrf_token()'), 'botão de status não possui proteção CSRF');
});

$test('vitrine funcional preserva a interface aprovada com dados reais', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $store = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/store.js') ?: '';
    foreach (['promocoes','duvidas','mobileNav','data-featured-coupon','resumo-loja-status','catalogo-compacto'] as $feature) {
        $expect(str_contains($store,$feature), "recurso visual aprovado ausente na vitrine: {$feature}");
    }
    $expect(str_contains($router,'$featuredCoupon->execute(compact(\'tenant\'))'), 'cupom em destaque não é isolado pelo tenant');
    $expect(str_contains($script,"mjdev-store-theme"), 'preferência de tema da vitrine não é persistida');
    $expect(str_contains($script,"navigator.clipboard.writeText(code)"), 'cupom em destaque não pode ser copiado');
    $expect(!str_contains($router,'used_count<usage_limit'), 'vitrine consulta coluna inexistente do cupom');
    $expect(str_contains($router,'usage_count<usage_limit'), 'vitrine não respeita limite de uso do cupom');
    foreach (['showCheckoutStep','renderCheckoutReview','Etapa ${number} de 4'] as $feature) $expect(str_contains($script,$feature), "checkout em quatro etapas ausente: {$feature}");
    $expect(str_contains($router,"\$path==='/loja.html'"), 'link legado do protótipo não redireciona para a vitrine real');
    $expect(str_contains($store,'<?php if($tableContext):?><option value="dine_in">'), 'checkout da mesa ainda oferece entrega ou retirada');
    $expect(str_contains($router,'!$privacyAccepted'), 'aceite de privacidade do pedido não é validado no servidor');
    $expect(strpos($store,'/assets/app.css')<strpos($store,'/assets/loja.css'), 'CSS genérico sobrescreve o visual aprovado da vitrine');
});

$test('tema claro da vitrine prevalece sobre presets e inicia antes do CSS', static function () use ($expect): void {
    $store = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $css = file_get_contents(ROOT_PATH . '/public/assets/loja.css') ?: '';
    $theme = file_get_contents(ROOT_PATH . '/public/assets/store-theme.js') ?: '';
    $expect(str_contains($css, 'html[data-theme=light] body[class]{--bg:#f6f0ea'), 'preset do segmento continua sobrescrevendo o tema claro');
    $expect(str_contains($theme, "localStorage.getItem('mjdev-store-theme')"), 'preferência da vitrine não é aplicada antecipadamente');
    $expect(str_contains($store, 'store-theme.js?v=20260827-tema1'), 'inicializador antecipado do tema não está publicado');
    $expect(str_contains($store, 'loja.css?v=20260904-compact1'), 'CSS corrigido da vitrine não possui versão atual');
    $expect(str_contains($css, 'html[data-theme=dark] body[class]'), 'modo escuro não prevalece sobre o preset visual');
    $expect(str_contains($css, 'main>#cardapio{order:3}') && str_contains($css, 'main>#promocoes{order:4}'), 'vitrine não prioriza o catálogo antes dos conteúdos auxiliares');
    $expect(str_contains($css, '.conteudo-destaque{max-width:none}') && str_contains($css, 'max-width:100%;padding:0;width:calc(100% - 36px)'), 'banner compacto mantém a coluna de conteúdo estreita ou o recuo antigo no celular');
    $expect(strpos($store, '/assets/store-theme.js') < strpos($store, '/assets/loja.css'), 'tema da vitrine é aplicado depois do CSS');
    $expect(str_contains($store, 'store.js?v=20260904-compact1'), 'JavaScript da vitrine não possui versão atual');
    $storeScript = file_get_contents(ROOT_PATH . '/public/assets/store.js') ?: '';
    $expect(str_contains($storeScript, "createElementNS(svgNamespace, 'svg')"), 'ícone vetorial do WhatsApp não foi criado com DOM seguro');
    $expect(!str_contains($storeScript, "whatsappLink.textContent = '☎'"), 'telefone genérico permanece no botão do WhatsApp');
    $expect(str_contains($css, 'justify-items: center'), 'controles flutuantes da vitrine não compartilham o mesmo eixo');
});

$test('imagem de produção aplica migrações antes de iniciar o Apache', static function () use ($expect): void {
    $dockerfile = file_get_contents(ROOT_PATH . '/Dockerfile') ?: '';
    $entrypoint = file_get_contents(ROOT_PATH . '/docker/entrypoint.sh') ?: '';
    $expect(str_contains($dockerfile,'CMD ["docker/entrypoint.sh"]'), 'imagem não usa o entrypoint de produção');
    $expect(str_contains($entrypoint,'until php bin/migrate.php'), 'entrypoint não aplica migrações pendentes');
    $expect(str_contains($entrypoint,'exec apache2-foreground'), 'entrypoint não entrega o processo ao Apache');
    $expect(str_contains($entrypoint,'DB_STARTUP_MAX_ATTEMPTS'), 'tentativas de conexão ao banco não são limitadas/configuráveis');
});

$test('checkout público continua público quando o visitante está autenticado', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router,"$" . "presentedCsrf=(string)($" . "data['_csrf']??'')"), 'checkout não identifica explicitamente a intenção interna');
    $expect(str_contains($router,"$" . "internal=$" . "presentedCsrf!==''&&$" . "internalStore"), 'sessão autenticada converte indevidamente checkout público em PDV');
    $expect(str_contains($router,"hash_equals(csrf_token(),$" . "presentedCsrf)"), 'operação interna não valida o CSRF apresentado');
});

$test('acompanhamento público usa token seguro, histórico real e fallback pelo site', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/tracking.php') ?: '';
    foreach (['tracking_token_hash=:token','tracking_revoked_at IS NULL','tracking_expires_at>NOW()','hash(\'sha256\',$m[1])'] as $rule) {
        $expect(str_contains($router,$rule), "proteção do acompanhamento ausente: {$rule}");
    }
    foreach (['order_status_history','o.tenant_id=:tenant','order_items'] as $rule) $expect(str_contains($router,$rule), "dados reais/tenant ausentes no acompanhamento: {$rule}");
    $expect(str_contains($router,'order_combo_items oci')&&str_contains($router,'$orderItems=array_merge'), 'acompanhamento omite combos persistidos');
    foreach (['linha-tempo-pedido','itens-acompanhamento','noindex,nofollow','Receber atualizações no WhatsApp','atualizações automaticamente a cada 20 segundos'] as $feature) {
        $expect(str_contains($view,$feature), "recurso visual do acompanhamento ausente: {$feature}");
    }
    foreach(['Gostaria de confirmar meu pedido ','Acompanhar pedido:','Abrir WhatsApp e enviar pedido','https://wa.me/','rawurlencode($whatsappMessage)'] as$feature)$expect(str_contains($view,$feature),'confirmação manual por WhatsApp incompleta: '.$feature);
    $expect(str_contains($view,"rtrim(env('APP_URL','http://localhost'),'/').'/pedido/'"),'mensagem não usa URL pública configurada');
    $expect(str_contains($view,'O pedido permanece salvo mesmo que você não abra ou não envie a mensagem no WhatsApp.'),'fallback independente do WhatsApp não está claro');
    $expect(!str_contains($view,'rawurlencode($token)'), 'token secreto de acompanhamento é exposto ao WhatsApp');
    $expect(strpos($view,'/assets/app.css')<strpos($view,'/assets/loja.css'), 'CSS genérico sobrescreve o visual aprovado do acompanhamento');
});

$test('vitrine não oferece caminhos de pedido indisponíveis', static function () use ($expect): void {
    $view = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/store.js') ?: '';
    foreach (['data-store-open','data-has-delivery-zones'] as $attribute) $expect(str_contains($view,$attribute), "estado ausente na vitrine: {$attribute}");
    $expect(str_contains($view,'$deliveryAvailable=$deliveryEnabled&&!empty($zones)'), 'disponibilidade da entrega não exige canal e área ativa');
    $expect(str_contains(file_get_contents(ROOT_PATH . '/public/assets/loja.css') ?: '','.campo[hidden]'), 'campos de entrega ocultos são exibidos indevidamente na retirada');
    $expect(str_contains($script,"deliveryOption.disabled = true"), 'entrega permanece disponível sem área cadastrada');
    $expect(str_contains($script,"checkoutButton').disabled = true"), 'checkout permanece ativo com loja fechada');
    $expect(!str_contains($script,"button.title = storeOpen ? 'Os canais de pedido estão temporariamente indisponíveis.'"), 'loja fechada impede o cliente de preparar a sacola');
    $expect(str_contains($script,'replacement.disabled = false'), 'lista de espera fica bloqueada com loja fechada');
});

$test('PWA não intercepta áreas privadas nem APIs', static function () use ($expect): void {
    $worker = file_get_contents(ROOT_PATH . '/public/sw.js') ?: '';
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $storeScript = file_get_contents(ROOT_PATH . '/public/assets/store.js') ?: '';
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $docker = file_get_contents(ROOT_PATH . '/Dockerfile') ?: '';
    $preflight = file_get_contents(ROOT_PATH . '/bin/preflight.php') ?: '';
    foreach (['/api/','/admin','/platform','/pedido/'] as $private) $expect(str_contains($worker,$private),"service worker sem exclusão: {$private}");
    $expect(str_contains($router,'application/manifest+json'), 'manifesto dinâmico ausente');
    $expect(str_contains($view, 'rel="manifest"') && str_contains($view, '/manifest.webmanifest'), 'manifesto não é declarado diretamente no HTML da vitrine');
    $expect(str_contains($storeScript, "addEventListener('beforeinstallprompt'") && str_contains($storeScript, "addEventListener('appinstalled'"), 'vitrine não controla o fluxo nativo de instalação');
    $expect(str_contains($view, 'data-store-name="<?= e($store[\'name\']) ?>"'), 'nome seguro da loja não está disponível ao aviso de instalação');
    $expect(str_contains($storeScript, '`Instalar ${storeName}`') && str_contains($storeScript, 'Acesse mais rápido, direto da tela inicial.') && str_contains($storeScript, 'Instalar aplicativo'), 'aviso dinâmico e ação de instalação não estão disponíveis ao cliente');
    $expect(str_contains($storeScript, "classList.add('pwa-install-visible')") && str_contains($storeScript, "classList.remove('pwa-install-visible')"), 'aviso de instalação não coordena os controles flutuantes');
    $expect(str_contains($view, 'loja.css?v=20260904-compact1') && str_contains($view, 'store.js?v=20260904-compact1'), 'ativos do fluxo PWA não possuem versão coordenada');
    foreach (['app-icon-192.png','app-icon-512.png'] as $icon) {
        $expect(is_file(ROOT_PATH . '/public/assets/' . $icon), "ícone PWA ausente: {$icon}");
    }
    $expect(filesize(ROOT_PATH . '/public/assets/app-icon-192.png') > 3000, 'ícone PWA de 192 px parece recortado ou incompleto');
    foreach (['icon-192.png','icon-512.png'] as $icon) $expect(str_contains($router, $icon), "manifesto não publica o ícone por loja: {$icon}");
    $expect(str_contains($router, 'store_pwa_icon_file') && str_contains($router, 'SELECT logo_path,secondary_color'), 'rota do ícone não usa a identidade isolada da loja');
    $expect(str_contains($bootstrap, 'function store_pwa_icon_file') && str_contains($bootstrap, 'imagecopyresampled'), 'logo da loja não é transformada com enquadramento seguro');
    $expect(str_contains($docker, 'docker-php-ext-configure gd') && str_contains($docker, 'curl gd'), 'imagem de produção não instala o processamento de ícones');
    $expect(str_contains($preflight, "extension_loaded('gd')"), 'preflight não exige suporte à geração dos ícones');
    $expect(str_contains($router, "\$publicPath='/'.\$store['slug']"), 'manifesto não possui identidade estável pela URL curta da loja');
    $expect(str_contains($router, "preg_match('#^/([a-z0-9-]+)\$#',\$path,\$m)"), 'vitrine não possui URL pública curta');
    $expect(str_contains($router, "redirect('/'.\$legacyStoreMatch[1].\$suffix)"), 'URL antiga da vitrine não redireciona preservando os parâmetros');
    $expect(str_contains($view, 'href="/<?= e($store[\'slug\']) ?>/manifest.webmanifest"'), 'HTML ainda declara o manifesto pela URL antiga');
    $expect(str_contains($worker, "CACHE = 'mjdev-store-v12'"), 'cache do PWA não foi renovado');
});

$test('webhooks usam segredo protegido, fila e mitigação SSRF', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $worker = file_get_contents(ROOT_PATH . '/bin/process-webhooks.php') ?: '';
    $expect(str_contains($bootstrap,"openssl_encrypt(\$plaintext,'aes-256-gcm'"), 'segredo não criptografado');
    $expect(str_contains($bootstrap,'FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE'), 'URL sem bloqueio de rede privada');
    $expect(str_contains($worker,'CURLOPT_FOLLOWLOCATION=>false'), 'webhook permite redirecionamento');
    $expect(str_contains($worker,'CURLOPT_RESOLVE'), 'webhook sujeito a DNS rebinding');
    $expect(str_contains($worker,'attempts<8'), 'fila sem limite de tentativas');
});

$test('lista de espera só aceita produto ativo sem estoque', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(substr_count($router,'stock_quantity IS NOT NULL AND stock_quantity<=0')>=2, 'lista de espera sem validação de estoque');
    $expect(str_contains($router,"rate_limit('waitlist:'"), 'lista de espera sem limite');
});

$test('avaliação de entrega exige pedido entregue e courier atribuído', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $sql = file_get_contents(ROOT_PATH . '/migrations/010_courier_reviews.sql') ?: '';
    $expect(str_contains($router,"status='delivered' AND assigned_courier_id IS NOT NULL"), 'avaliação de entrega sem vínculo entregue/courier');
    $expect(str_contains($router,"rate_limit('courier-review'"), 'avaliação de entrega sem limite');
    $expect(str_contains($sql,'UNIQUE KEY uq_courier_review_order'), 'pedido permite avaliações duplicadas do courier');
});

$test('PDV cria venda pela API com preços do servidor e proteção CSRF', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/admin-ui.js') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/pos.php') ?: '';
    $expect(str_contains($router,'$internal&&!hash_equals(csrf_token()'), 'venda interna sem proteção CSRF');
    $expect(str_contains($router,'SELECT p.id,p.name,p.price_cents,p.stock_quantity'), 'API não consulta preço no servidor');
    $expect(str_contains($script,"order_type: 'pickup'"), 'PDV não cria pedido de retirada');
    $expect(str_contains($view,'data-product-id'), 'PDV sem produtos reais do tenant');
});

$test('QR de mesa usa token seguro e vincula pedido à comanda do tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $migration = file_get_contents(ROOT_PATH . '/migrations/011_table_qr_orders.sql') ?: '';
    $expect(str_contains($router,"hash_hmac('sha256',\$tableToken,env('APP_KEY',''))"), 'token da mesa não é validado no servidor');
    $expect(str_contains($router,'INSERT INTO table_session_orders'), 'pedido de mesa não é vinculado à comanda');
    $expect(str_contains($router,"tenant_id=:tenant AND access_token_hash=:token"), 'QR da mesa sem isolamento por tenant');
    $expect(str_contains($migration,"ENUM('delivery','pickup','dine_in')"), 'tipo de pedido de salão ausente');
});

$test('cadastros de catálogo permitem edição e inativação por tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/catalog-manager.php') ?: '';
    foreach (['UPDATE delivery_zones','UPDATE coupons','UPDATE option_groups','UPDATE option_items','UPDATE combos'] as $operation) $expect(str_contains($router,$operation),"operação ausente: {$operation}");
    foreach (['product-option/unassign','combo-item/remove'] as $action) $expect(str_contains($router,$action),"ação ausente: {$action}");
    $expect(substr_count($view,'name="_csrf"')>=10, 'formulários CRUD sem cobertura CSRF');
    $expect(str_contains($router,'WHERE id=:id AND tenant_id=:tenant'), 'atualizações sem filtro de tenant');
});

$test('gestão de equipe protege fundador e papéis privilegiados', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/team.php') ?: '';
    $expect(str_contains($router,"role NOT IN ('platform_admin','owner')"), 'status permite desativar conta privilegiada');
    $expect(str_contains($router,"\$targetRole==='owner'"), 'edição permite alterar proprietário');
    $expect(str_contains($router,'AND tenant_id=:tenant'), 'gestão de equipe sem tenant');
    $expect(str_contains($view,'minlength="15"'), 'senha provisória abaixo da política');
    foreach (['data-open-user','userDialog','cartao-tabela-administrativa','busca-local','Acesso protegido'] as $visual) $expect(str_contains($view,$visual),"visual de equipe ausente: {$visual}");
    $expect(str_contains($router,"filter_var(\$_POST['active']??false,FILTER_VALIDATE_BOOLEAN)"),'estado do acesso não é interpretado explicitamente no servidor');
    $adminCss=(string)file_get_contents(ROOT_PATH . '/public/assets/admin.css');
    $expect(!str_contains($adminCss,"content: 'NL'"),'casco operacional ainda exibe a marca antiga NL');
    $expect(str_contains($adminCss,"content: 'MJ'"),'casco operacional não aplica a marca MJ');
});

$test('dashboard usa métricas reais e isoladas do estabelecimento', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/dashboard.php') ?: '';
    foreach (['orders_today','revenue_today','month_revenue','hourlyOrders','dailyRevenue','topProducts'] as $metric) $expect(str_contains($router,$metric),"métrica ausente: {$metric}");
    $expect(substr_count($router,'WHERE tenant_id=:tenant')>=10, 'consultas analíticas sem isolamento suficiente');
    $expect(!str_contains($view,'R$ 68.490'), 'dashboard ainda contém faturamento demonstrativo');
    $expect(str_contains($view,"\$money((int)(\$dashboardStats['revenue_today']"), 'faturamento real não renderizado');
});

$test('relatórios e DRE usam período, pedidos e despesas reais', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/reports.php') ?: '';
    $migration = file_get_contents(ROOT_PATH . '/migrations/012_expense_lifecycle.sql') ?: '';
    foreach (['reportPayments','reportChannels','reportProducts','expenseCategories'] as $dataset) $expect(str_contains($router,$dataset),"dataset ausente: {$dataset}");
    $expect(str_contains($router,'created_at>=:from'), 'relatório não filtra início do período');
    $expect(str_contains($router,'active=1 AND occurred_on BETWEEN :from AND :to'), 'DRE inclui despesas anuladas ou fora do período');
    $expect(str_contains($view,'window.print()'), 'cardápio e relatório sem impressão/PDF do navegador');
    foreach (['7 dias','30 dias','90 dias','periodo-personalizado','data-open-expense'] as $visual) $expect(str_contains($view,$visual),"filtro ou ação visual ausente no relatório: {$visual}");
    $expect(str_contains($view,'action="/admin/expense/save"'), 'modal financeiro não persiste despesas no backend');
    $expect(str_contains($migration,'ADD COLUMN active'), 'despesas sem ciclo de vida reversível');
});

$test('modos operacionais usam pedidos reais e respeitam atribuição', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/operations.php') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/admin-ui.js') ?: '';
    $expect(str_contains($router,"'waiter','pos'"), 'garçom não autorizado a criar pedido interno');
    $expect(str_contains($router,"\$phoneOptional=\$internal&&\$type==='dine_in'"), 'pedido presencial não trata telefone opcional com contexto autenticado');
    $expect(str_contains($router,'assigned_courier_id=:courier'), 'entregador pode alterar pedido não atribuído');
    foreach (['serviceOrderForm','data-service-option-product','Abrir no GPS','order/status'] as $feature) $expect(str_contains($view,$feature),"operação ausente: {$feature}");
    $expect(str_contains($script,"order_type: 'dine_in'"), 'garçom não envia pedido de salão');
});

$test('vitrine filtra catálogo e exibe apenas avaliações aprovadas', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $script = file_get_contents(ROOT_PATH . '/public/assets/store.js') ?: '';
    $expect(substr_count($router,'approved=1')>=2, 'vitrine pode expor avaliações não moderadas');
    $expect(str_contains($router,'AVG(rating) average_rating'), 'média de avaliações ausente');
    foreach (['catalogSearch','data-catalog-category','grade-avaliacoes'] as $feature) $expect(str_contains($view,$feature),"recurso de vitrine ausente: {$feature}");
    $expect(str_contains($script,'filterCatalog'), 'busca/filtro do catálogo sem comportamento');
    $expect(str_contains($script,"localStorage.setItem('mjdev-store-theme'"), 'preferência visual da vitrine não persiste');
});

$test('identidade visual, onboarding e PWA usam dados persistidos', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $settings = file_get_contents(ROOT_PATH . '/src/views/settings.php') ?: '';
    $store = file_get_contents(ROOT_PATH . '/src/views/store.php') ?: '';
    $expect(str_contains($router,'logo_path=:logo_path OR tp.banner_path=:banner_path'), 'rota de marca usa placeholder PDO incompatível');
    $expect(str_contains($router,'store_image_upload($_FILES[\'logo\']'), 'upload seguro de logo ausente');
    $expect(str_contains($settings,'enctype="multipart/form-data"'), 'formulário visual não envia arquivos');
    $expect(str_contains($settings,'data-qr-value'), 'PWA não gera QR do catálogo real');
    $expect(str_contains($settings,'count(array_filter($steps))'), 'onboarding não calcula progresso real');
    foreach (['primary_color','logo_path','banner_path','/brand/'] as $feature) $expect(str_contains($store,$feature),"branding ausente na vitrine: {$feature}");
    $expect(str_contains($settings,'action="/admin/store/status"'), 'configurações não oferecem controle operacional real da loja');
    $expect(!str_contains($settings,'name="store_open"'), 'formulário geral ainda pode alterar acidentalmente o estado operacional');
    $expect(!str_contains($router,'description=:description,store_open=:open'), 'salvamento do perfil ainda mistura dados cadastrais e estado operacional');
});

$test('canais de venda são persistidos e validados no servidor', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';$settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';$store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';$script=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';$migration=file_get_contents(ROOT_PATH.'/migrations/013_sales_channels.sql')?:'';
    foreach(['delivery_enabled','pickup_enabled','free_delivery_above_cents'] as$column)$expect(str_contains($migration,$column),"migration de canal ausente: {$column}");
    $expect(str_contains($settings,'action="/admin/channels/save"'),'configurações não salvam canais de venda');
    $expect(str_contains($router,"$"."action==='channels/save'"),'ação de canais ausente');
    $expect(str_contains($router,"Este tipo de pedido está temporariamente indisponível."),'API não bloqueia canal pausado');
    $expect(str_contains($router,"$"."subtotal>=(int)$"."channelPreferences['free_delivery_above_cents']"),'entrega grátis não é recalculada no servidor');
    $expect(str_contains($store,'data-orders-enabled'),'vitrine não recebe disponibilidade dos canais');
    $expect(str_contains($script,"dataset.ordersEnabled === '1'"),'checkout não respeita indisponibilidade de canais');
});

$test('formas de pagamento públicas possuem configuração e mínimo no servidor', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';$settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';$store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';$migration=file_get_contents(ROOT_PATH.'/migrations/014_payment_methods.sql')?:'';
    foreach(['payment_pix_enabled','payment_cash_enabled','payment_debit_enabled','payment_credit_enabled','payment_pix_minimum_cents'] as$column)$expect(str_contains($migration,$column),"migration de pagamento ausente: {$column}");
    $expect(str_contains($settings,'action="/admin/payments/save"'),'configurações não salvam formas de pagamento');
    $expect(str_contains($settings,'Gateway online não está sendo simulado'),'painel confunde pagamento no atendimento com gateway online');
    $expect(str_contains($router,"$"."action==='payments/save'"),'ação de pagamentos ausente');
    $expect(str_contains($router,'Esta forma de pagamento está indisponível.'),'API não bloqueia pagamento público desativado');
    $expect(str_contains($router,"$"."subtotal<$"."paymentMinimum"),'API não valida mínimo por pagamento');
    $expect(str_contains($store,'$enabledPayments'),'checkout não filtra formas de pagamento persistidas');
});

$test('setores e mesas permitem manutenção segura por tenant', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/salon.php') ?: '';
    $expect(str_contains($router,'UPDATE dining_areas SET name=:name WHERE id=:id AND tenant_id=:tenant'), 'setor não permite edição isolada');
    $expect(str_contains($router,'UPDATE dining_tables SET area_id=:area,name=:name,capacity=:capacity,active=:active,status=:status WHERE id=:id AND tenant_id=:tenant'), 'mesa não permite edição isolada');
    $expect(str_contains($router,"Feche a comanda antes de inativar a mesa"), 'mesa ocupada pode ser inativada');
    $expect(str_contains($router,"if($" . "action==='table/status')"), 'salão não permite reservar ou liberar mesa pelo servidor');
    $expect(str_contains($router,"UPDATE dining_tables SET status=:status WHERE id=:id AND tenant_id=:tenant"), 'situação da mesa não é isolada por tenant');
    $expect(substr_count($view,'action="/admin/table/save"')>=2, 'tela não oferece edição das mesas');
    $expect(substr_count($view,'action="/admin/area/save"')>=2, 'tela não oferece edição dos setores');
    $expect(str_contains($view,"$" . "canManage=in_array($" . "user['role'],['platform_admin','owner','manager'],true)"), 'controles administrativos do salão não respeitam função');
    $expect(str_contains($view,"$" . "t['status']==='available'"), 'abertura de comanda oferece mesa indisponível');
    $expect(str_contains($view,'Nenhuma mesa disponível no momento.'), 'salão não informa ausência de mesa disponível');
    $expect(str_contains($view,'Cada código abre o catálogo no modo salão, sem entrega ou retirada.'), 'QR de mesa não explica o canal exclusivo de salão');
    $expect(str_contains($view,'$statusLabels'), 'estados das mesas não são apresentados em português');
});

$test('usuário autenticado pode trocar a própria senha com segurança', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $view = file_get_contents(ROOT_PATH . '/src/views/settings.php') ?: '';
    foreach (["password_verify(\$current,\$hash)",'password_hash($password,password_algorithm())','session_regenerate_id(true)',"rate_limit('password-change:'"] as $guard) $expect(str_contains($router,$guard),"proteção ausente na troca de senha: {$guard}");
    $expect(str_contains($router,'WHERE id=:id AND tenant_id=:tenant AND active=1'), 'troca de senha sem isolamento do usuário');
    $expect(str_contains($view,'action="/admin/account/password"'), 'painel não oferece troca de senha');
    $expect(str_contains($view,'minlength="15"'), 'nova senha abaixo da política');
});

$test('health check não revela detalhes internos da aplicação', static function () use ($expect): void {
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $expect(str_contains($router, "json_response(['status' => 'ok'])"), 'health check não retorna estado genérico');
    $expect(!str_contains($router, "json_response(['status'=>'ok','schema'=>"), 'health check revela versão do schema');
    $expect(str_contains($router, "json_response(['status' => 'unavailable'], 503)"), 'health check não sinaliza indisponibilidade');
});

$test('deploy de produção possui preflight obrigatório', static function () use ($expect): void {
    $preflight = file_get_contents(ROOT_PATH . '/bin/preflight.php') ?: '';
    $entrypoint = file_get_contents(ROOT_PATH . '/docker/entrypoint.sh') ?: '';
    foreach (['APP_KEY', 'APP_URL', 'APP_DEBUG', 'SESSION_SECURE_COOKIE', 'PRIVACY_CONTACT_EMAIL', 'DB_PASSWORD', 'pdo_mysql'] as $guard) {
        $expect(str_contains($preflight, $guard), "validação ausente no preflight: {$guard}");
    }
    $expect(str_contains($entrypoint, 'php bin/preflight.php'), 'entrypoint não executa o preflight');
    $expect(strpos($entrypoint, 'php bin/preflight.php') < strpos($entrypoint, 'php bin/migrate.php'), 'preflight executado depois das migrations');
    $compose = file_get_contents(ROOT_PATH . '/compose.yaml') ?: '';
    $appBlock = explode('  mysql:', $compose, 2)[0];
    $expect(!str_contains($appBlock, 'MYSQL_ROOT_PASSWORD'), 'senha root do MySQL foi exposta ao container da aplicação');
});

$test('todas as views referenciam arquivos estáticos publicados', static function () use ($expect): void {
    $views = glob(ROOT_PATH . '/src/views/*.php') ?: [];
    $references = [];
    foreach ($views as $view) {
        $content = file_get_contents($view) ?: '';
        preg_match_all('#(?:href|src)="/assets/([^"?]+)#', $content, $matches);
        foreach ($matches[1] as $asset) $references[$asset] = true;
    }
    foreach (array_keys($references) as $asset) {
        $expect(is_file(ROOT_PATH . '/public/assets/' . $asset), 'arquivo estático ausente: ' . $asset);
    }
    $landing = file_get_contents(ROOT_PATH . '/src/views/landing.php') ?: '';
    $expect(str_contains($landing, '/assets/login.css?v='), 'landing sem versão de cache no CSS principal');
    $adminCss = file_get_contents(ROOT_PATH . '/public/assets/admin.css') ?: '';
    $expect(str_contains($adminCss, '.estrutura.plataforma-global'), 'painel global herda margem de uma barra lateral inexistente');
});

$test('taxas e recompensas são persistidas e calculadas no servidor', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';$settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';$tracking=file_get_contents(ROOT_PATH.'/src/views/tracking.php')?:'';$migration=file_get_contents(ROOT_PATH.'/migrations/015_fees_and_rewards.sql')?:'';
    foreach(['service_fee_enabled','convenience_fee_percent','cashback_enabled','loyalty_every_orders','CREATE TABLE IF NOT EXISTS customer_rewards','service_fee_cents','convenience_fee_cents'] as$term)$expect(str_contains($migration,$term),'migration de taxas/recompensas ausente: '.$term);
    $expect(str_contains($settings,'action="/admin/rewards/save"'),'configurações não salvam taxas e recompensas');
    $expect(str_contains($router,"$"."action==='rewards/save'"),'ação de taxas e recompensas ausente');
    $expect((bool)preg_match('/\\$status\\s*===\\s*[\'\"]delivered[\'\"]/', $router),'benefícios não são emitidos após entrega');
    $expect(str_contains($router,'customer_phone_hash'),'benefício não é vinculado ao cliente');
    $expect(str_contains($router,'redeemed_at=NOW()'),'benefício não possui consumo único');
    $expect(str_contains($router,'service_fee_cents,convenience_fee_cents'),'pedido não persiste taxas separadas');
    $expect(str_contains($tracking,'BENEFÍCIOS CONQUISTADOS'),'acompanhamento não apresenta recompensa emitida');
});

$test('programa de indicação atribui primeiro pedido e recompensa somente após entrega', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/021_customer_referrals.sql')?:'';$router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';$settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';$store=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';$tracking=file_get_contents(ROOT_PATH.'/src/views/tracking.php')?:'';
    foreach(['CREATE TABLE IF NOT EXISTS customer_referrals','CREATE TABLE IF NOT EXISTS referral_attributions','UNIQUE KEY uq_referral_referred_customer','referrer_customer_id','referred_customer_id'] as$term)$expect(str_contains($migration,$term),'estrutura de indicação ausente: '.$term);
    $expect(str_contains($settings,'action="/admin/referral-program/save"'),'painel não configura programa de indicação');
    $expect(str_contains($router,"$"."action==='referral-program/save'"),'ação de configuração da indicação ausente');
    $expect(str_contains($router,"(int)$"."customer['order_count']===1"),'indicação não está limitada ao primeiro pedido');
    $expect(str_contains($router,"ra.status='pending'"),'recompensa de indicação não exige atribuição pendente');
    $expect(str_contains($router,"$"."status === 'delivered'"),'recompensa de indicação não depende da entrega');
    $expect(str_contains($store,'payload.referral_token'),'vitrine não envia token de indicação');
    $expect(str_contains($tracking,'data-copy-referral'),'acompanhamento não oferece link pessoal');
});

$test('assinatura aprovada está integrada ao painel sem simular cobrança', static function () use ($expect): void {
    $bootstrap = file_get_contents(ROOT_PATH . '/src/bootstrap.php') ?: '';
    $router = file_get_contents(ROOT_PATH . '/public/index.php') ?: '';
    $billing = file_get_contents(ROOT_PATH . '/src/views/billing.php') ?: '';
    $expect(str_contains($bootstrap, "'billing'=>'Assinatura & Pagamento'"), 'menu não contém assinatura e pagamento');
    $expect(str_contains($router, "'billing'=>'billing'"), 'rota administrativa não carrega a view de cobrança');
    $expect(str_contains($billing, 'OFERTA FUNDADORES'), 'visual aprovado da oferta não foi migrado');
    $expect(str_contains($billing, '14 dias grátis'), 'período gratuito não está apresentado');
    $expect(str_contains($billing, "action=\"/admin/billing/checkout\"")&&str_contains($billing,'$gatewayReady'), 'checkout seguro não está condicionado ao gateway');
});

$test('fundação Mercado Pago mantém segredos no servidor e eventos idempotentes', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $migration=file_get_contents(ROOT_PATH.'/migrations/030_mercadopago_billing_events.sql')?:'';
    $environment=file_get_contents(ROOT_PATH.'/.env.example')?:'';
    $preflight=file_get_contents(ROOT_PATH.'/bin/preflight.php')?:'';
    $docker=file_get_contents(ROOT_PATH.'/Dockerfile')?:'';
    foreach(['function mercadopago_request','https://api.mercadopago.com','X-Idempotency-Key','CURLOPT_FOLLOWLOCATION=>false','function mercadopago_notification_url','source_news=webhooks','final class MercadoPagoRequestException','function mercadopago_webhook_signature_valid','hash_hmac'] as$rule)$expect(str_contains($bootstrap,$rule),'cliente seguro ausente: '.$rule);
    foreach(['CREATE TABLE IF NOT EXISTS billing_provider_events','UNIQUE KEY uq_billing_provider_event','payload_hash','tenant_id'] as$rule)$expect(str_contains($migration,$rule),'eventos financeiros ausentes: '.$rule);
    foreach(['MERCADOPAGO_ENV=sandbox','MERCADOPAGO_PUBLIC_KEY=','MERCADOPAGO_ACCESS_TOKEN=','MERCADOPAGO_WEBHOOK_SECRET=','MERCADOPAGO_MONTHLY_PLAN_ID=','MERCADOPAGO_ANNUAL_PLAN_ID='] as$rule)$expect(str_contains($environment,$rule),'variável documentada ausente: '.$rule);
    $expect(!preg_match('/MERCADOPAGO_ACCESS_TOKEN=\S+/', $environment),'token real não pode estar no exemplo');
    $expect(str_contains($preflight,"['sandbox','production']")&&str_contains($preflight,'extension_loaded(\'curl\')'),'preflight do gateway incompleto');
    $expect(str_contains($docker,'docker-php-ext-install pdo_mysql opcache curl'),'imagem não instala cliente HTTP PHP');
});

$test('provisionamento sandbox cria planos sem duplicar teste grátis ou revelar credenciais', static function () use ($expect): void {
    $script=file_get_contents(ROOT_PATH.'/bin/create-mercadopago-plans.php')?:'';
    foreach (["PHP_SAPI !== 'cli'","mercadopago_environment() !== 'sandbox'","strlen((string)env('APP_KEY', '')) < 32",'mercadopago_plan_id($code)',"mercadopago_request('POST', '/preapproval_plan'",'hash_hmac',"'amount_cents' => 5990","'amount_cents' => 59900","'frequency_type' => 'months'",'MERCADOPAGO_MONTHLY_PLAN_ID','MERCADOPAGO_ANNUAL_PLAN_ID'] as $rule) {
        $expect(str_contains($script,$rule),'provisionamento de planos incompleto: '.$rule);
    }
    $expect(!str_contains($script,"'free_trial'"),'gateway não deve conceder um segundo período gratuito');
    $expect(!str_contains($script,"echo env('MERCADOPAGO_ACCESS_TOKEN'"),'comando não pode revelar o token');
});

$test('checkout Mercado Pago associa assinatura ao tenant sem confiar no retorno do navegador', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $billing=file_get_contents(ROOT_PATH.'/src/views/billing.php')?:'';
    $migration=file_get_contents(ROOT_PATH.'/migrations/031_mercadopago_checkout.sql')?:'';
    foreach (["if(\$action==='billing/checkout')",'require_csrf()',"in_array(\$user['role'],['platform_admin','owner'],true)",'mercadopago_plan_id($plan)',"mercadopago_environment()==='production'&&\$trialEndsAt","'preapproval_plan_id'=>\$planId","'card_token_id'=>\$cardToken","'payer_email'=>\$payerEmail","'notification_url'=>mercadopago_notification_url()","mercadopago_request('GET','/preapproval_plan/'","'external_reference'=>\$checkoutReference","'status'=>'authorized'","mercadopago_request('POST','/preapproval'",'provider_subscription_ref=:subscription','checkout_reference=:reference',"status='pending'"] as $rule) {
        $expect(str_contains($router,$rule),'fluxo seguro de checkout ausente: '.$rule);
    }
    foreach (['function mercadopago_public_key',"preg_match('/^[A-Za-z0-9_-]{20,200}$/'"] as $rule)$expect(str_contains($bootstrap,$rule),'public key validada ausente: '.$rule);
    foreach (['checkout_reference','provider_checkout_url','checkout_started_at','UNIQUE KEY uq_tenant_billing_checkout_reference'] as $rule)$expect(str_contains($migration,$rule),'persistência do checkout ausente: '.$rule);
    $expect(str_contains($billing,'O retorno do pagamento não ativa a conta sozinho'),'interface induz confirmação pelo retorno do navegador');
    $expect(!str_contains($router,"SET status='active' WHERE tenant_id=:tenant"),'retorno local não pode ativar assinatura');
    foreach(["preg_match('/^[A-Za-z0-9_-]{20,300}$/',\$cardToken)","filter_var(\$payerEmail,FILTER_VALIDATE_EMAIL)","status=:status,provider_subscription_ref=NULL",'checkout_reference=NULL'] as$rule)$expect(str_contains($router,$rule),'validação ou recuperação do checkout ausente: '.$rule);
    foreach(['provider_code=',"'MP-'.\$errorCode","'MJ-'.\$errorCode","Código: '.\$diagnostic"] as$rule)$expect(str_contains($bootstrap.$router,$rule),'diagnóstico seguro do checkout ausente: '.$rule);
    foreach(['existingProviderSubscription',"\$existing['status']==='trialing'&&\$existingProviderSubscription"] as$rule)$expect(str_contains($router,$rule),'teste local bloqueia nova assinatura: '.$rule);
    foreach (["if(\$action==='billing/reconcile')","rate_limit('billing-reconcile:'","mercadopago_request('GET','/preapproval/'",'hash_equals($checkoutReference,$providerReference)','hash_equals($expectedPlanId,$providerPlanId)',"LIMIT 1 FOR UPDATE","audit(\$tenant,(int)\$user['id'],'reconcile'",'action="/admin/billing/reconcile"'] as $rule) $expect(str_contains($router.$billing,$rule),'reconciliação segura da assinatura ausente: '.$rule);
    $expect(str_contains($billing,"\$billingAccount['status']==='trialing'&&!empty(\$billingAccount['provider_subscription_ref'])"),'painel confunde teste local com assinatura externa');
    foreach (["if(\$action==='billing/cancel')","rate_limit('billing-cancel:'","mercadopago_request('PUT','/preapproval/'","['status'=>'canceled']",'hash_equals($checkoutReference,$providerReference)',"SET status='cancelled',cancel_at_period_end=0","audit(\$tenant,(int)\$user['id'],'cancel'",'name="confirm_cancel"','action="/admin/billing/cancel"'] as $rule) $expect(str_contains($router.$billing,$rule),'cancelamento seguro da assinatura ausente: '.$rule);
    $expect(str_contains($router,"in_array(\$billing['status'],['active','past_due'],true)"),'cancelamento aceita assinatura sem cobrança ativa');
    foreach(['https://sdk.mercadopago.com/js/v2','/assets/billing-payment.js','data-billing-plan','name="card_token"','name="payer_email"'] as$rule)$expect(str_contains($billing,$rule),'formulário tokenizado ausente: '.$rule);
    $paymentScript=file_get_contents(ROOT_PATH.'/public/assets/billing-payment.js')?:'';
    foreach(['new window.MercadoPago','mp.cardForm','data.token','form.submit()'] as$rule)$expect(str_contains($paymentScript,$rule),'tokenização no navegador ausente: '.$rule);
});

$test('webhook Mercado Pago valida assinatura consulta a API e processa por tenant com idempotência', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $migration=file_get_contents(ROOT_PATH.'/migrations/032_mercadopago_webhook_processing.sql')?:'';
    foreach (["\$path==='/webhooks/mercadopago'","\$type!=='subscription_preapproval'","raw_query_parameter('data.id')",'HTTP_X_SIGNATURE','HTTP_X_REQUEST_ID','mercadopago_webhook_signature_valid',"mercadopago_request('GET','/preapproval/'",'provider_event_key',"status='processing'",'checkout_reference=:reference','provider_subscription_ref=:subscription',"'authorized'=>'active'",'beginTransaction()',"status IN ('active','past_due','cancelled','expired')"] as $rule)$expect(str_contains($router,$rule),'webhook seguro incompleto: '.$rule);
    foreach (['function raw_query_parameter','rawurldecode($key)','function mercadopago_datetime','new DateTimeImmutable'] as $rule)$expect(str_contains($bootstrap,$rule),'leitura segura da assinatura ausente: '.$rule);
    foreach (["ENUM('received','processing','processed','ignored','failed')",'attempts','last_attempt_at'] as $rule)$expect(str_contains($migration,$rule),'controle idempotente incompleto: '.$rule);
    $expect(!str_contains($router,"\$_GET['tenant_id']"),'webhook não pode confiar em tenant recebido');
    $expect(str_contains($router,"json_response(['status'=>'unauthorized'],401)"),'assinatura inválida não é rejeitada');
    foreach(["mercadopago_environment()==='sandbox'","\$dataId==='123456'","'simulation'=>true"] as$rule)$expect(str_contains($router,$rule),'simulação assinada do sandbox não é reconhecida: '.$rule);
});

$test('página PWA administrativa preserva compartilhamento e QR reais', static function () use ($expect): void {
    $settings = file_get_contents(ROOT_PATH . '/src/views/settings.php') ?: '';
    $adminJs = file_get_contents(ROOT_PATH . '/public/assets/admin-ui.js') ?: '';
    foreach (['APP (PWA) E IDENTIDADE', 'QR CODE REAL', 'COMPARTILHAR CARDÁPIO', 'STATUS DO APLICATIVO'] as $heading) {
        $expect(str_contains($settings, $heading), 'bloco PWA ausente: ' . $heading);
    }
    $expect(str_contains($settings, 'data-qr-value="<?= e($catalogUrl) ?>"'), 'QR não usa URL real do tenant');
    $expect(str_contains($adminJs, "link.download = 'qr-code-cardapio.svg'"), 'QR não pode ser baixado em SVG');
});

$test('QR da vitrine possui rota própria e ações reais', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $qr=file_get_contents(ROOT_PATH.'/src/views/qr.php')?:'';
    $expect(str_contains($router,"'qr'=>'qr'"),'QR continua carregando a tela administrativa genérica');
    $expect(!str_contains($bootstrap,"['qr','menu-preview','print-menu','pwa']"),'PWA permanece duplicado no grupo Vitrine');
    $expect(str_contains($qr,'data-qr-value="<?= e($catalogUrl) ?>"'),'QR não usa o endereço real do tenant');
    $expect(str_contains($qr,'baixar-qr') && str_contains($qr,'window.print()') && str_contains($qr,'data-copy-value'),'ações de baixar, imprimir e copiar não estão completas');
    $adminJs=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $expect(str_contains($adminJs,"|| document.querySelector('.qr-code')"),'download do QR falha quando o botão está em outro cartão');
    $expect(str_contains($adminJs,"'cashback_enabled'") && str_contains($adminJs,"'loyalty_enabled'"),'interruptores de configuração não ocultam os campos dependentes');
});

$test('vitrine móvel preserva menu completo e barra persistente da sacola', static function () use ($expect): void {
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $storeJs=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    $storeCss=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    foreach(['estado-loja-movel','mobileCartButton','mobileCartBar','mobileCartQty','mobileCartTotal'] as$id)$expect(str_contains($store,$id),'elemento móvel ausente: '.$id);
    $expect(str_contains($storeJs,"$('#mobileCartButton')") && str_contains($storeJs,"$('#mobileCartBar')"),'atalhos móveis não abrem a sacola real');
    $expect(str_contains($storeJs,"$('#mobileCartTotal').textContent = money(total)"),'barra móvel não recebe o total real do carrinho');
    $tabletMedia=strpos($storeCss,'@media(max-width:980px)');
    $tabletMenu=$tabletMedia===false?false:strpos($storeCss,'.botao-menu', $tabletMedia);
    $tabletDisplay=$tabletMenu===false?false:strpos($storeCss,'display: block', $tabletMenu);
    $expect($tabletMedia!==false && $tabletMenu!==false && $tabletDisplay!==false && $tabletDisplay-$tabletMenu<100,'menu responsivo não aparece no breakpoint de tablet');
});

$test('webhooks LGPD e auditoria usam painel dedicado com dados reais', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $view=file_get_contents(ROOT_PATH.'/src/views/governance.php')?:'';
    $expect(str_contains($router,"'webhooks','lgpd','audit'=>'governance'"),'módulos de governança continuam na tela genérica');
    foreach(['csrf_token()','/admin/webhook/save','/admin/webhook/status','/admin/privacy/status','$webhooks','$privacy','$audits','tenant'] as$rule)$expect(str_contains($view,$rule),'governança sem regra funcional: '.$rule);
    foreach(['INTEGRAÇÕES SEGURAS','PRIVACIDADE E LGPD','LOGS DE AUDITORIA','metricas-modulo','vazio'] as$visual)$expect(str_contains($view,$visual),'paridade visual ausente em governança: '.$visual);
    $expect(!str_contains($view,'data-action='),'tela de governança contém ação demonstrativa');
});

$test('relacionamento usa visual dedicado e operações persistidas', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $view=file_get_contents(ROOT_PATH.'/src/views/engagement.php')?:'';
    $expect(str_contains($router,"'waitlist','subscriptions','reviews','product-reviews','driver-reviews'=>'engagement'"),'relacionamento continua na tela genérica');
    foreach(['/admin/waitlist/status','/admin/subscription/save','/admin/subscription/status','/admin/review/moderate','/admin/courier-review/moderate','csrf_token()'] as$action)$expect(str_contains($view,$action),'ação real ausente em relacionamento: '.$action);
    foreach(['metricas-modulo','grade-avaliacoes-admin','lista-assinaturas','vazio','data-admin-search'] as$visual)$expect(str_contains($view,$visual),'paridade visual ausente em relacionamento: '.$visual);
    foreach(['$waitlist','$subscriptions','$reviews','$courierReviews'] as$data)$expect(str_contains($view,$data),'dados reais ausentes em relacionamento: '.$data);
    $expect(!str_contains($view,'data-action='),'relacionamento contém ação demonstrativa');
});

$test('catálogo e configurações preservam o visual aprovado sem simular integrações', static function () use ($expect): void {
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    foreach(['catalogModules','introducao-catalogo','ferramentas-catalogo','recursos-planejados','NÃO DISPONÍVEL'] as$rule) {
        $expect(str_contains($script,$rule),"componente visual aprovado ausente: {$rule}");
    }
    foreach(['.introducao-catalogo','.cabecalho-recursos-planejados','.recurso-planejado'] as$rule) {
        $expect(str_contains($css,$rule),"estilo de paridade ausente: {$rule}");
    }
    $expect(!str_contains($script,'interruptor-bloqueado'),'recursos futuros continuam parecendo acionáveis');
    foreach(['Pagamento online dos pedidos','Em desenvolvimento','não exigem configuração no EasyPanel'] as$copy)$expect(str_contains($script,$copy),'comunicação de recurso futuro ausente: '.$copy);
    $expect(str_contains($script,'ainda não estão disponíveis'), 'recursos sem backend não estão identificados como indisponíveis');
});

$test('banner da vitrine usa identidade persistida e layout responsivo aprovado', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/016_storefront_hero.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    foreach(['hero_title','hero_highlight','hero_cta_text'] as$field) {
        $expect(str_contains($migration,$field),'campo persistente ausente no banner: '.$field);
        $expect(str_contains($router,$field),'salvamento do banner ausente: '.$field);
    }
    foreach(['data-hero-title','data-hero-highlight','data-hero-cta'] as$attribute)$expect(str_contains($settings,$attribute),'configuração não expõe valor seguro do banner: '.$attribute);
    $expect(str_contains($router,"$"."action==='hero/save'"),'rota administrativa do banner ausente');
    $expect(str_contains($store,'$heroTitle') && str_contains($store,'$heroHighlight') && str_contains($store,'$heroCta'),'vitrine não renderiza identidade real da loja');
    $expect(str_contains($store,'resumo-loja')&&str_contains($store,'data-featured-coupon'),'resumo compacto não preserva cupom');
    $expect(str_contains($css,'.acoes-destaque-vitrine'),'layout responsivo do banner não possui estilos');
    $expect(str_contains($css,'.recurso:has(.imagem-produto-vazia)')&&str_contains($css,'.cartao-produto:has(.imagem-produto:empty)'),'produto sem foto ainda reserva uma coluna vazia');
    $expect(str_contains($css,'.recurso button')&&str_contains($css,'white-space:nowrap'),'ação do produto em destaque ainda quebra verticalmente');
    $expect(str_contains($css,'#optionsFields .opcao-produto')&&str_contains($script,"label.className = 'opcao-produto'"),'opções do produto não possuem linha clicável alinhada');
    $expect(str_contains($css,'.acoes-item-carrinho .remover-item-carrinho')&&str_contains($css,'grid-column:1/4'),'quantidade da sacola não possui controle compacto');
    $expect(str_contains($css,'#trackingDialog input')&&str_contains($css,'margin:0 0 14px'),'acompanhamento não separa campo e ação');
});

$test('horários da loja têm formulário compacto e estado fechado seguro', static function () use ($expect): void {
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $expect(str_contains($settings,'cabecalho-tabela-horarios')&&str_contains($settings,'data-hours-table'),'tabela de horários não possui cabeçalho ou controlador');
    $expect(str_contains($settings,"\$closed?'disabled':''")&&str_contains($settings,'alternar-dia-fechado'),'dia fechado não é apresentado corretamente');
    $expect(str_contains($css,'.linha-horario.dia-fechado')&&str_contains($css,'@media(max-width:700px)'),'horários não possuem estado fechado ou adaptação móvel');
    $expect(str_contains($script,"input.disabled = closed")&&str_contains($script,"form.addEventListener('submit'"),'alternância de dia fechado não preserva horários no envio');
});

$test('presets de vitrine são persistidos e aplicados por segmento', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $migration=file_get_contents(ROOT_PATH.'/migrations/017_storefront_presets.sql')?:'';
    foreach(['alimentacao-artesanal','pizzaria-italiana','pastelaria-brasileira','almoco-caseiro','moda-editorial','farmacia-confiavel','informatica-tech','comercio-versatil'] as$preset)$expect(str_contains($bootstrap,$preset),'preset ausente: '.$preset);
    foreach(['storefront_presets','storefront_preset_key_for_business_type','storefront_preset'] as$helper)$expect(str_contains($bootstrap,'function '.$helper),'helper de preset ausente: '.$helper);
    $expect(str_contains($migration,'ADD COLUMN storefront_preset'),'preset não é persistido por tenant');
    $expect(str_contains($router,'storefront_preset_key_for_business_type($type)'),'cadastro não escolhe preset pelo segmento validado');
    $expect(str_contains($router,"$"."action==='preset/save'")&&str_contains($router,'storefront_preset=VALUES(storefront_preset)'),'alteração de preset não é persistida no servidor');
    $expect(str_contains($settings,'action="/admin/preset/save"'),'configurações não oferecem seleção de preset');
    foreach(['Hamburgueria artesanal','Pizzaria italiana','Pastelaria brasileira','Almoço caseiro','storefront_presets_for_business_type','apply_preset_identity'] as$rule)$expect(str_contains($bootstrap.$settings.$router,$rule),'separação visual de alimentação ausente: '.$rule);
    $expect(str_contains($router,"in_array((string)\$user['business_type'],\$preset['business_types'],true)"),'servidor aceita modelo incompatível com o segmento');
    $expect(str_contains($bootstrap,"\$preset&&in_array(\$businessType,\$preset['business_types'],true)"),'vitrine renderiza modelo incompatível persistido anteriormente');
    $expect(str_contains($router,'hero_title=NULL,hero_highlight=NULL,hero_cta_text=NULL'),'modelo não consegue aplicar sua linguagem aprovada');
    foreach(["$"."preset['hero_title']","$"."preset['nav_catalog']","$"."preset['catalog_eyebrow']","$"."preset['catalog_title']","$"."preset['catalog_search']","$"."preset['color_scheme']"] as$binding)$expect(str_contains($store,$binding),'vitrine não aplica preset: '.$binding);
    $expect(str_contains($store,"$"."preferences['hero_title']??''"),'personalização do tenant não tem prioridade sobre o preset');
    $css=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    foreach(['alimentacao','moda','beleza','pet','casa','farmacia','esportes','informatica','comercio'] as$theme)$expect(str_contains($css,'body.tema-'.$theme),'tema visual ausente: '.$theme);
    $expect(str_contains($css,'--surface: var(--panel2)')&&str_contains($css,'--border: var(--line)'),'tokens visuais da vitrine não acompanham o preset');
    foreach(['Pizza feita para compartilhar.','Pastel crocante.','Comida caseira.'] as$copy)$expect(str_contains($bootstrap,$copy),'texto comercial curto ausente: '.$copy);
    $expect(substr_count($bootstrap,"'hero_cta_text'=>'Ver cardápio'")>=4,'segmentos de alimentação não usam CTA neutro');
    $expect(str_contains($store,"!empty($"."preferences['banner_path'])")&&str_contains($store,'class="banner-catalogo"'),'banner não é opcional no catálogo compacto');
    $expect(str_contains($css,'.destaque-principal.com-banner .conteudo-destaque')&&str_contains($css,'.fatos-destaque{bottom:auto'),'composição híbrida do destaque não foi aplicada');
    $expect(str_contains($store,"'com-imagem':'sem-imagem'")&&str_contains($store,'aria-label="Adicionar'),'cartões não distinguem imagem nem descrevem a ação de adicionar');
    $expect(str_contains($css,'.cartao-produto.sem-imagem')&&str_contains($css,'grid-template-columns:104px minmax(0,1fr)'),'cartões não possuem composição responsiva com e sem foto');
    $expect(str_contains($css,'@media(max-width:420px)')&&str_contains($css,'.aviso-instalacao-pwa .botao{grid-column:1/-1'),'aviso PWA não se adapta a iPhones estreitos');
    foreach(['tema-hamburgueria','tema-pizzaria','tema-pastelaria','tema-almoco'] as$theme)$expect(str_contains($css,$theme),'tema alimentar ausente: '.$theme);
    $expect(str_contains($store,'data-business-segment'),'vitrine não identifica o segmento operacional renderizado');
});

$test('pedidos preservam listagem aprovada com busca e exportação protegida', static function () use ($expect): void {
    $view=file_get_contents(ROOT_PATH.'/src/views/orders.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['PEDIDOS › LISTAR','cartao-tabela-administrativa','busca-local','data-order-filter','ferramentas-pedidos'] as$visual)$expect(str_contains($view,$visual),'estrutura aprovada ausente em pedidos: '.$visual);
    $expect(str_contains($view,'/admin/export/orders'),'exportação real não está acessível no módulo de pedidos');
    $expect(str_contains($view,"['platform_admin','owner','manager']"),'exportação não está limitada a papéis administrativos');
    $expect(str_contains($router,"$"."path==='/admin/export/orders'") && str_contains($router,'tenant_id=:tenant'),'exportação não está protegida e isolada por tenant');
    $expect(str_contains($script,'.cartao-tabela-administrativa tbody tr'),'busca local não filtra as linhas reais da listagem');
    $expect(str_contains($script,'[data-order-filter]'),'filtro de pedidos não foi conectado ao JavaScript');
});

$test('redes sociais e links rápidos são funcionais e isolados por tenant', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/019_social_links_quick_links.sql')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $dashboard=file_get_contents(ROOT_PATH.'/src/views/dashboard.php')?:'';
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['social_links_enabled','instagram_url','CREATE TABLE IF NOT EXISTS tenant_quick_links','FOREIGN KEY (tenant_id)','idx_tenant_quick_links_order'] as$rule)$expect(str_contains($migration,$rule),'persistência ausente: '.$rule);
    foreach(['function safe_configured_link','function valid_social_url','https'] as$rule)$expect(str_contains($bootstrap,$rule),'validação de URL ausente: '.$rule);
    foreach(["$"."action==='social-links/save'","$"."action==='quick-link/save'","$"."action==='quick-link/delete'",'tenant_id=:tenant','SELECT COUNT(*) FROM tenant_quick_links','audit($tenant'] as$rule)$expect(str_contains($router,$rule),'controle de servidor ausente: '.$rule);
    foreach(['/admin/social-links/save','/admin/quick-link/save','/admin/quick-link/delete','recursos-ativos'] as$rule)$expect(str_contains($settings,$rule),'formulário funcional ausente: '.$rule);
    $expect(str_contains($dashboard,'links-rapidos-personalizados')&&str_contains($dashboard,'$quickLinks'),'dashboard não renderiza os atalhos persistidos');
    $expect(str_contains($store,'social_links_enabled')&&str_contains($store,'valid_social_url')&&str_contains($store,'storeSocialLinks'),'vitrine não renderiza redes sociais validadas');
    $expect(!str_contains($script,"['Redes sociais'")&&!str_contains($script,"['Links rápidos'"),'recursos já implementados continuam marcados como bloqueados');
});

$test('notificações do painel respeitam preferências e eventos reais do tenant', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/020_panel_notification_preferences.sql')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['notification_new_order','notification_cancelled_order','notification_low_stock'] as$column)$expect(str_contains($migration,$column),'preferência não persistida: '.$column);
    $expect(str_contains($router,"$"."action==='panel-notifications/save'")&&str_contains($router,"'panel_notifications'")&&str_contains($router,'tenant_preferences (tenant_id,notification_new_order'),'salvamento seguro das notificações ausente');
    foreach(['/admin/panel-notifications/save','Novo pedido','Pedido cancelado ou recusado','Estoque baixo'] as$rule)$expect(str_contains($settings,$rule),'configuração visual ausente: '.$rule);
    foreach(['tenant_id=:tenant','notification_new_order','time()-86400','stock_quantity<=:threshold','array_slice($notifications,0,12)'] as$rule)$expect(str_contains($bootstrap,$rule),'regra real do sino ausente: '.$rule);
    $expect(str_contains($bootstrap,"['platform_admin','owner','manager','attendant','pos']"),'alerta de estoque não respeita papéis autorizados');
    $expect(!str_contains($script,"['Notificações no painel'"),'notificações funcionais continuam marcadas como bloqueadas');
});

$test('LGPD e sacola preservam layout responsivo sem colisões', static function () use ($expect): void {
    $privacy=file_get_contents(ROOT_PATH.'/src/views/privacy-request.php')?:'';
    $appCss=file_get_contents(ROOT_PATH.'/public/assets/app.css')?:'';
    $storeCss=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    $expect(str_contains($privacy,'pagina-solicitacao-privacidade'),'página LGPD não possui escopo visual próprio');
    $expect(str_contains($privacy,'Barlow+Condensed'),'fonte aprovada da página LGPD não é carregada');
    foreach(['.pagina-solicitacao-privacidade .cartao-acompanhamento h1','overflow-wrap:normal','word-break:normal'] as$rule)$expect(str_contains($appCss,$rule),'proteção do título LGPD ausente: '.$rule);
    $couponStart=strpos($storeCss,'.formulario-cupom {');$couponEnd=strpos($storeCss,'.formulario-cupom input',$couponStart?:0);$couponBlock=$couponStart!==false&&$couponEnd!==false?substr($storeCss,$couponStart,$couponEnd-$couponStart):'';
    $expect(str_contains($couponBlock,'display: grid')&&str_contains($couponBlock,'gap: 8px'),'cupom ainda usa layout horizontal sujeito a colisão');
    $expect(str_contains($storeCss,'.totais p b{flex:0 0 auto;white-space:nowrap}'),'valor total pode quebrar ou colidir na sacola');
});

$test('recuperação de senha usa token seguro, fila criptografada e invalida sessões', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/024_password_recovery.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $worker=file_get_contents(ROOT_PATH.'/bin/process-email-queue.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    foreach(['password_reset_tokens','token_hash','expires_at','used_at','session_version','outbound_emails','payload_encrypted'] as$rule)$expect(str_contains($migration,$rule),'persistência segura ausente: '.$rule);
    foreach(['/esqueci-senha','/redefinir-senha/','random_bytes(32)',"hash('sha256',$".'token)',"session_version=session_version+1",'Se o e-mail estiver cadastrado'] as$rule)$expect(str_contains($router,$rule),'fluxo seguro ausente: '.$rule);
    $expect(str_contains($bootstrap,"$"."_SESSION['session_version']")&&str_contains($bootstrap,"u.session_version"),'sessões antigas não são invalidadas');
    foreach(['queue_transactional_email','encrypt_secret($payload)','smtp_send','STARTTLS'] as$rule)$expect(str_contains($mailer,$rule),'entrega SMTP segura ausente: '.$rule);
    foreach(['FOR UPDATE SKIP LOCKED','attempts=attempts+1',"$"."failed?'failed':'pending'",'decrypt_secret'] as$rule)$expect(str_contains($worker,$rule),'worker resiliente ausente: '.$rule);
    $expect(str_contains($auth,"env('MAIL_ENABLED','false')==='true'")&&str_contains($auth,'Esqueci minha senha'),'recuperação não respeita ativação operacional');
});

$test('confirmação de e-mail usa token isolado e protege a primeira publicação', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/025_email_verification.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    foreach(['email_verified_at','email_verification_tokens','tenant_id','user_id','token_hash','email_hash','expires_at','used_at'] as$rule)$expect(str_contains($migration,$rule),'persistência da confirmação ausente: '.$rule);
    foreach(['/verificar-email/','hash_equals($verification','account/email-verification','Confirme seu e-mail antes de publicar','tenant_id=:tenant'] as$rule)$expect(str_contains($router,$rule),'controle de confirmação ausente: '.$rule);
    $expect(str_contains($bootstrap,'u.email_verified_at'),'contexto autenticado não informa confirmação');
    foreach(['function issue_email_verification','random_bytes(32)','DATE_ADD(NOW(),INTERVAL 24 HOUR)','queue_transactional_email'] as$rule)$expect(str_contains($mailer,$rule),'emissão segura ausente: '.$rule);
    foreach(['E-MAIL CONFIRMADO','CONFIRMAÇÃO PENDENTE','SMTP ATIVO','SMTP INATIVO','Envio transacional não configurado','/admin/account/email-verification'] as$rule)$expect(str_contains($settings,$rule),'estado visual ausente: '.$rule);
    $expect(str_contains($settings,'$canEnableTwoFactor=$mailEnabled&&$emailVerified')&&str_contains($settings,"?'disabled':''"),'ativação visual do 2FA não respeita SMTP e confirmação');
});

$test('2FA por e-mail protege contas privilegiadas antes de criar a sessão autenticada', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/026_email_two_factor.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    foreach(['two_factor_enabled','login_two_factor_challenges','challenge_token_hash','code_hash','attempts','expires_at','consumed_at','tenant_id','user_id'] as$rule)$expect(str_contains($migration,$rule),'persistência 2FA ausente: '.$rule);
    foreach(['complete_authenticated_login','pending_two_factor','/verificar-acesso','attempts<5','FOR UPDATE','two-factor-resend','account/two-factor',"['platform_admin','owner','manager']"] as$rule)$expect(str_contains($router,$rule),'controle 2FA ausente: '.$rule);
    foreach(['unset($_SESSION[\'user_id\'],$_SESSION[\'session_version\'])', '$_SESSION[\'pending_two_factor\']'] as$rule)$expect(str_contains($router,$rule),'sessão não permanece pendente antes do 2FA: '.$rule);
    foreach(['function issue_two_factor_challenge','random_int(100000,999999)',"hash_hmac('sha256',$".'challengeToken', 'DATE_ADD(NOW(),INTERVAL 10 MINUTE)','queue_transactional_email'] as$rule)$expect(str_contains($mailer,$rule),'emissão 2FA insegura ou ausente: '.$rule);
    $expect(str_contains($bootstrap,'u.two_factor_enabled'),'contexto autenticado não informa 2FA');
    foreach(['autocomplete="one-time-code"','[0-9]{6}','Enviar novo código'] as$rule)$expect(str_contains($auth,$rule),'tela 2FA ausente: '.$rule);
    foreach(['2FA ATIVO','2FA DESATIVADO','/admin/account/two-factor'] as$rule)$expect(str_contains($settings,$rule),'configuração 2FA ausente: '.$rule);
    $expect(!str_contains($script,"['Verificação em duas etapas'"),'2FA funcional continua listado como pendente');
});

$test('códigos de recuperação do 2FA são únicos, descartáveis e armazenados somente como hash', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/027_two_factor_recovery_codes.sql')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    foreach(['user_two_factor_recovery_codes','code_hash','used_at','tenant_id','user_id'] as$rule)$expect(str_contains($migration,$rule),'persistência de recuperação ausente: '.$rule);
    foreach(['replace_two_factor_recovery_codes','random_bytes(4)',"hash_hmac('sha256',$".'code','DELETE FROM user_two_factor_recovery_codes'] as$rule)$expect(str_contains($bootstrap,$rule),'geração segura ausente: '.$rule);
    foreach(['^MJ-[A-F0-9]{4}-[A-F0-9]{4}$','FOR UPDATE','used_at=NOW()','two_factor_recovery_login','two-factor-recovery-codes'] as$rule)$expect(str_contains($router,$rule),'consumo protegido ausente: '.$rule);
    $expect(str_contains($mailer,'issue_two_factor_recovery_challenge'),'fallback de recuperação não funciona sem entrega SMTP');
    foreach(['MJ-[A-Fa-f0-9]{4}','Código de acesso ou recuperação'] as$rule)$expect(str_contains($auth,$rule),'entrada de recuperação ausente: '.$rule);
    foreach(['não será exibido novamente','Gerar novos códigos de recuperação'] as$rule)$expect(str_contains($settings,$rule),'orientação visual de recuperação ausente: '.$rule);
    $platformTenant=file_get_contents(ROOT_PATH.'/src/views/platform-tenant.php')?:'';
    foreach(['platform/user/two-factor-reset','administrative_reset','session_version=session_version+1',"role<>'platform_admin'",'password_verify'] as$rule)$expect(str_contains($router,$rule),'recuperação administrativa insegura ou ausente: '.$rule);
    foreach(['RECUPERAÇÃO ADMINISTRATIVA','AÇÃO AUDITADA','current_password'] as$rule)$expect(str_contains($platformTenant,$rule),'interface de recuperação administrativa ausente: '.$rule);
});

$test('sessões autenticadas são persistidas, isoladas e revogáveis por dispositivo', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/028_user_sessions.sql')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $sessionView=file_get_contents(ROOT_PATH.'/src/views/account-sessions.php')?:'';
    foreach(['user_sessions','session_token_hash','session_version','ip_hash','user_agent_hash','device_label','last_seen_at','expires_at','revoked_at','tenant_id','user_id'] as$rule)$expect(str_contains($migration,$rule),'persistência de sessão ausente: '.$rule);
    foreach(['start_authenticated_session','random_bytes(32)','hash(\'sha256\',$token)','hash_hmac(\'sha256\',$agent','$_SESSION[\'auth_session_token\']','expires_at>NOW()','DATE_ADD(NOW(),INTERVAL :minutes MINUTE)'] as$rule)$expect(str_contains($bootstrap,$rule),'controle de sessão ausente: '.$rule);
    $expect(!str_contains($bootstrap,"date('Y-m-d H:i:s',time()+\$minutes*60)"),'sessão volta a misturar o fuso do PHP com o relógio do banco');
    foreach(['account/session/revoke','account/session/revoke-others','id<>:current','UPDATE user_sessions SET revoked_at=NOW()'] as$rule)$expect(str_contains($router,$rule),'revogação de sessão ausente: '.$rule);
    foreach(['SELECT id,tenant_id,role,session_version,two_factor_enabled FROM users','complete_authenticated_login($createdUser,null)'] as$rule)$expect(str_contains($router,$rule),'cadastro não reutiliza o fluxo persistente de autenticação: '.$rule);
    foreach(['session_version=:version OR id=:current','revoked_at IS NULL','expires_at>NOW()'] as$rule)$expect(str_contains($settings,$rule),'listagem segura de sessões ausente: '.$rule);
    foreach(['SESSÕES E DISPOSITIVOS','ESTE DISPOSITIVO','Encerrar todos os outros acessos'] as$rule)$expect(str_contains($sessionView,$rule),'interface de sessões ausente: '.$rule);
});

$test('novo dispositivo gera alerta opcional sem armazenar identificadores em texto aberto', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/029_new_device_alerts.sql')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $sessionView=file_get_contents(ROOT_PATH.'/src/views/account-sessions.php')?:'';
    foreach(['new_device_alerts_enabled','information_schema.COLUMNS','ALTER TABLE users'] as$rule)$expect(str_contains($migration,$rule),'migration de alerta ausente: '.$rule);
    foreach(['COUNT(CASE WHEN user_agent_hash=:agent THEN 1 END)','issue_new_device_alert','new_device','device_label'] as$rule)$expect(str_contains($bootstrap,$rule),'detecção ou auditoria de dispositivo ausente: '.$rule);
    foreach(['function issue_new_device_alert','MAIL_ENABLED','email_verified_at','queue_transactional_email','Novo dispositivo conectado'] as$rule)$expect(str_contains($mailer,$rule),'alerta transacional ausente: '.$rule);
    foreach(['account/security-alerts','new_device_alerts_enabled','029_new_device_alerts.sql'] as$rule)$expect(str_contains($router,$rule),'preferência segura de alerta ausente: '.$rule);
    foreach(['Avisar sobre novo dispositivo','Salvar preferência','/admin/account/security-alerts'] as$rule)$expect(str_contains($sessionView,$rule),'interface do alerta ausente: '.$rule);
    $expect(!str_contains($mailer,'HTTP_USER_AGENT'),'mensagem não deve receber o agente bruto do navegador');
});

$test('política de 2FA privilegiado restringe rotas sem bloquear a própria ativação', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $preflight=file_get_contents(ROOT_PATH.'/bin/preflight.php')?:'';
    $environment=file_get_contents(ROOT_PATH.'/.env.example')?:'';
    $notice=file_get_contents(ROOT_PATH.'/src/views/two-factor-policy.php')?:'';
    foreach(['function privileged_two_factor_required','REQUIRE_PRIVILEGED_2FA','platform_admin','owner','manager','function enforce_privileged_two_factor'] as$rule)$expect(str_contains($bootstrap,$rule),'política central ausente: '.$rule);
    foreach(['privileged_two_factor_required($user)?\'settings\'','twoFactorSetupActions','account/two-factor','account/email-verification','enforce_privileged_two_factor($user)','privileged_two_factor_required($operator)','A política de segurança não permite desativar'] as$rule)$expect(str_contains($router,$rule),'bloqueio de rota ausente: '.$rule);
    foreach(['REQUIRE_PRIVILEGED_2FA','MAIL_ENABLED=true'] as$rule)$expect(str_contains($preflight,$rule),'preflight da política ausente: '.$rule);
    $expect(str_contains($environment,'REQUIRE_PRIVILEGED_2FA=false'),'política deve iniciar desativada no exemplo');
    foreach(['AÇÃO DE SEGURANÇA OBRIGATÓRIA','Configurar agora','2FA obrigatório e ativo'] as$rule)$expect(str_contains($notice,$rule),'orientação visual ausente: '.$rule);
});

$test('histórico de segurança mostra somente eventos da própria conta e do próprio tenant', static function () use ($expect): void {
    $settings=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $history=file_get_contents(ROOT_PATH.'/src/views/account-security-events.php')?:'';
    foreach(['FROM audit_logs','tenant_id=:tenant','user_id=:user','ORDER BY id DESC LIMIT 30','account-security-events.php'] as$rule)$expect(str_contains($settings,$rule),'consulta isolada do histórico ausente: '.$rule);
    foreach(['HISTÓRICO DE SEGURANÇA','Atividades da sua conta','password_change|user','new_device|user_session','complete|two_factor_login','Dados técnicos, endereços e hashes não são exibidos'] as$rule)$expect(str_contains($history,$rule),'histórico visual ausente: '.$rule);
    $expect(!str_contains($history,'ip_hash')&&!str_contains($history,'metadata'),'histórico expõe dados técnicos desnecessários');
});

$test('tentativas suspeitas de login são limitadas e alertadas sem enumerar contas', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $mailer=file_get_contents(ROOT_PATH.'/src/mailer.php')?:'';
    $history=file_get_contents(ROOT_PATH.'/src/views/account-security-events.php')?:'';
    foreach(['identity_failures','source_failures',"<8&&", "<20",'record_suspicious_login_attempt','attempts<5','suspicious-login-alert:','1800',"'suspicious','login_attempt'",'DELETE FROM login_attempts'] as$rule)$expect(str_contains($router,$rule),'controle de tentativa suspeita ausente: '.$rule);
    $expect(str_contains($router,"flash('error','Credenciais inválidas.')"),'resposta de login deixou de ser neutra');
    foreach(['function issue_suspicious_login_alert','MAIL_ENABLED','email_verified_at','Tentativas de acesso detectadas','queue_transactional_email'] as$rule)$expect(str_contains($mailer,$rule),'alerta seguro ausente: '.$rule);
    $alertStart=strpos($mailer,'function issue_suspicious_login_alert');$alertEnd=strpos($mailer,'function smtp_send',$alertStart?:0);$alertBlock=$alertStart!==false&&$alertEnd!==false?substr($mailer,$alertStart,$alertEnd-$alertStart):'';
    $expect($alertBlock!==''&&!str_contains($alertBlock,'client_ip_hash')&&!str_contains($alertBlock,'HTTP_USER_AGENT'),'alerta recebe identificador técnico desnecessário');
    $expect(str_contains($history,'suspicious|login_attempt'),'histórico não traduz o alerta de tentativa suspeita');
});

$test('manutenção remove somente dados técnicos expirados em lotes controlados', static function () use ($expect): void {
    $cleanup=file_get_contents(ROOT_PATH.'/bin/cleanup-expired-data.php')?:'';
    $mailerWorker=file_get_contents(ROOT_PATH.'/bin/process-email-queue.php')?:'';
    $readme=file_get_contents(ROOT_PATH.'/README.md')?:'';
    $deploy=file_get_contents(ROOT_PATH.'/DEPLOY_EASYPANEL.md')?:'';
    foreach(["PHP_SAPI!=='cli'",'max(100,min(5000','login_attempts','rate_limits','password_reset_tokens','email_verification_tokens','login_two_factor_challenges','user_two_factor_recovery_codes','user_sessions',"status='sent'","status='failed'",'ORDER BY id LIMIT'] as$rule)$expect(str_contains($cleanup,$rule),'rotina de manutenção ausente: '.$rule);
    foreach(['audit_logs','orders','customers','products'] as$protected)$expect(!str_contains($cleanup,'DELETE FROM '.$protected),'rotina tenta apagar dado permanente: '.$protected);
    $expect(!str_contains($mailerWorker,'DELETE FROM password_reset_tokens')&&!str_contains($mailerWorker,'DELETE FROM email_verification_tokens'),'worker de e-mail ainda duplica a manutenção');
    $expect(str_contains($readme,'php bin/cleanup-expired-data.php 1000'),'agendamento não foi documentado no README');
    $expect(str_contains($deploy,'php /var/www/html/bin/cleanup-expired-data.php 1000'),'agendamento não foi incluído no deploy');
});

$test('cabeçalhos de segurança bloqueiam origens externas sem quebrar a prévia interna', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $apache=file_get_contents(ROOT_PATH.'/docker/apache.conf')?:'';
    $reports=file_get_contents(ROOT_PATH.'/src/views/reports.php')?:'';
    foreach(["X-Frame-Options: SAMEORIGIN","X-Permitted-Cross-Domain-Policies: none","Cross-Origin-Opener-Policy: same-origin","Cross-Origin-Resource-Policy: same-origin","Origin-Agent-Cluster: ?1","frame-ancestors 'self'","frame-src 'self'","object-src 'none'","script-src 'self'","connect-src 'self'","worker-src 'self'"] as$rule)$expect(str_contains($bootstrap,$rule),'cabeçalho da aplicação ausente: '.$rule);
    $expect(!str_contains($bootstrap,"script-src 'self' 'unsafe-inline'"),'CSP voltou a permitir JavaScript inline');
    foreach(['X-Frame-Options "SAMEORIGIN"','Strict-Transport-Security "max-age=31536000"','Cross-Origin-Opener-Policy "same-origin"','Cross-Origin-Resource-Policy "same-origin"'] as$rule)$expect(str_contains($apache,$rule),'cabeçalho do Apache ausente: '.$rule);
    $expect(!str_contains($apache,'X-Frame-Options "DENY"'),'Apache ainda bloqueia a prévia interna da vitrine');
    $expect(str_contains($reports,'<iframe')&&str_contains($reports,'src="/loja/<?= e($store[\'slug\']) ?>"'),'prévia interna da vitrine deixou de existir');
});

$test('editor visual cadastra e edita o catálogo real com isolamento do tenant', static function () use ($expect): void {
    $migration=file_get_contents(ROOT_PATH.'/migrations/033_product_sort_order.sql')?:'';
    $pizzaMigration=file_get_contents(ROOT_PATH.'/migrations/034_option_group_pricing.sql')?:'';
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $view=file_get_contents(ROOT_PATH.'/src/views/admin.php')?:'';
    $storeView=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/admin-ui.js')?:'';
    $storeScript=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    $style=file_get_contents(ROOT_PATH.'/public/assets/admin.css')?:'';
    $storeStyle=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    foreach(["'visual-catalog'=>'Editor visual'","'visual-catalog','categories','products'",'visual-catalog\',\'categories'] as$rule)$expect(str_contains($bootstrap,$rule),'rota ou navegação do editor ausente: '.$rule);
    foreach(["in_array(\$section,['categories','products','visual-catalog'],true)","\$productReturn=",'tenant_id=:tenant','redirect($productReturn)'] as$rule)$expect(str_contains($router,$rule),'persistência segura do editor ausente: '.$rule);
    foreach(['data-open-visual-product','data-edit-visual-product','return_to" value="visual-catalog','/admin/product/save','Edição avançada'] as$rule)$expect(str_contains($view,$rule),'interface do editor ausente: '.$rule);
    foreach(['visualProductForm.elements.id.value','visualProductForm.elements.category_id.value','textContent','URL.createObjectURL'] as$rule)$expect(str_contains($script,$rule),'comportamento seguro do editor ausente: '.$rule);
    foreach(['data-live-storefront-editor','data-live-storefront-frame','sandbox="allow-same-origin allow-scripts"','data-live-viewport="desktop"','data-live-viewport="mobile"','data-live-theme="auto"','data-live-theme="light"','data-live-theme="dark"'] as$rule)$expect(str_contains($view,$rule),'estrutura do editor ao vivo ausente: '.$rule);
    foreach(['connectLiveStorefront','contentDocument','[data-catalog-product]','openVisualProductEditor','data-live-refresh','liveStorefrontAutomaticTheme','aria-pressed'] as$rule)$expect(str_contains($script,$rule),'comportamento do editor ao vivo ausente: '.$rule);
    foreach(['editor-vitrine-ao-vivo','barra-editor-vitrine','palco-editor-vitrine','dispositivo-editor-vitrine','modo-celular','status-editor-vitrine'] as$rule)$expect(str_contains($style,$rule),'aparência responsiva do editor ao vivo ausente: '.$rule);
    foreach(["(int)\$viewer['tenant_id']===\$tenant","in_array(\$viewer['role'],['owner','manager'],true)",'privileged_two_factor_required($viewer)','Cache-Control: no-store, private',"'storefront-editor'=>'/loja/'.\$user['slug'].'?editar=1#cardapio'"] as$rule)$expect(str_contains($router,$rule),'proteção do editor na vitrine ausente: '.$rule);
    foreach(['data-owner-editor','barra-editor-proprietario','data-owner-product','ownerProductEditor','name="_csrf"','return_to" value="storefront-editor','Ver como cliente'] as$rule)$expect(str_contains($storeView,$rule),'interface proprietária da vitrine ausente: '.$rule);
    foreach(['ownerEditorEnabled','openOwnerProductEditor','data-owner-new-product','stopImmediatePropagation','data-owner-product-form'] as$rule)$expect(str_contains($storeScript,$rule),'comportamento proprietário da vitrine ausente: '.$rule);
    foreach(['modo-editor-proprietario','barra-editor-proprietario','editor-produto-proprietario','data-owner-product'] as$rule)$expect(str_contains($storeStyle,$rule),'aparência proprietária da vitrine ausente: '.$rule);
    foreach(['pricing_strategy',"ENUM('sum','highest')"] as$rule)$expect(str_contains($pizzaMigration,$rule),'migração de preço por grupo ausente: '.$rule);
    foreach(["if(\$action==='pizza/setup')",'pizzaria-italiana',"'Sabores da pizza'",'pricing_strategy',"'highest'",'array_search($groupCharge',"'charged_price'",'INSERT IGNORE INTO product_option_groups'] as$rule)$expect(str_contains($router,$rule),'assistente de sabores sem regra segura: '.$rule);
    foreach(['assistente-sabores-pizza','/admin/pizza/setup','flavors','max_flavors','product_ids[]','sabor de maior preço'] as$rule)$expect(str_contains($view,$rule),'interface do assistente de pizzaria ausente: '.$rule);
    foreach(['optionPriceTotal','group.pricing === \'highest\'','input.dataset.strategy','strategy: input.dataset.strategy'] as$rule)$expect(str_contains($storeScript,$rule),'cálculo visual de sabores ausente: '.$rule);
    foreach(["if(\$action==='product/duplicate')","if(\$action==='product/toggle')",'WHERE id=:id AND tenant_id=:tenant','source_product_id',"'duplicate','product'","'activate':'deactivate'"] as$rule)$expect(str_contains($router,$rule),'ação rápida segura ausente: '.$rule);
    foreach(['/admin/product/duplicate','/admin/product/toggle','data-visual-product-actions','data-toggle-visual-label'] as$rule)$expect(str_contains($view,$rule),'controle rápido do produto ausente: '.$rule);
    foreach(['sort_order','idx_products_visual_order','UPDATE products'] as$rule)$expect(str_contains($migration,$rule),'ordenação persistente ausente: '.$rule);
    foreach(["if(\$action==='product/reorder')","if(\$action==='product/move')",'count(array_unique($ids))','SELECT COUNT(*) FROM products WHERE tenant_id=?','beginTransaction()','p.sort_order,p.id'] as$rule)$expect(str_contains($router,$rule),'ordenação segura ausente: '.$rule);
    foreach(['dragstart','dragover','dragend','/admin/product/reorder',"['up', 'Mover antes']","['down', 'Mover depois']",'/admin/product/move'] as$rule)$expect(str_contains($script,$rule),'interação de ordenação ausente: '.$rule);
    foreach(["if(\$action==='category/move')",'SELECT sort_order FROM categories WHERE id=:id AND tenant_id=:tenant FOR UPDATE','INSERT INTO categories (tenant_id,name,sort_order,active)','categoryReturn'] as$rule)$expect(str_contains($router,$rule),'categoria rápida ou ordenação segura ausente: '.$rule);
    foreach(['data-open-visual-category','visualCategoryDialog','/admin/category/move','return_to" value="visual-catalog','Mover antes','Mover depois'] as$rule)$expect(str_contains($view,$rule),'organização visual de categorias ausente: '.$rule);
    foreach(["redirect('/admin?section=visual-catalog&edit='.\$copyId)",'Cópia preparada. Altere nome, preço e foto'] as$rule)$expect(str_contains($router,$rule),'duplicação inteligente não direciona à revisão: '.$rule);
    foreach(['requestedVisualProduct','/^\\d+$/','duplicatedProduct.click()','CÓPIA PARA REVISÃO','nameField?.select()','window.history.replaceState'] as$rule)$expect(str_contains($script,$rule),'revisão rápida da cópia ausente: '.$rule);
    foreach(['continueCreating',"\$_POST['continue_creating']","'&add='.\$category",'Cadastre o próximo item desta categoria'] as$rule)$expect(str_contains($router,$rule),'cadastro sequencial não preserva a categoria com segurança: '.$rule);
    foreach(['continueButton.name = \'continue_creating\'','Salvar e cadastrar outro','requestedVisualCategory','matchingCategory','CADASTRO EM SEQUÊNCIA','searchParams.delete(\'add\')'] as$rule)$expect(str_contains($script,$rule),'interface de cadastro sequencial ausente: '.$rule);
    foreach(['removeImage','image_path=NULL','($image||$removeImage)','remove_stored_image($previousImage,$tenant)'] as$rule)$expect(str_contains($router,$rule),'remoção segura da imagem ausente: '.$rule);
    foreach(['acceptVisualImage','image/jpeg','image/png','image/webp','5 * 1024 * 1024','dragenter','dataTransfer?.files','new DataTransfer()','remove_image','A foto atual será removida quando você salvar'] as$rule)$expect(str_contains($script,$rule),'experiência segura de upload ausente: '.$rule);
    foreach(['stock_control_present','stock_quantity=:stock','INSERT INTO stock_movements','balance_after',"'adjustment'",'stock_tracking'] as$rule)$expect(str_contains($router,$rule),'estoque rápido sem validação ou histórico: '.$rule);
    foreach(['visualProductInventoryData','stock_quantity'] as$rule)$expect(str_contains($view,$rule),'dados reais do estoque ausentes no editor: '.$rule);
    foreach(['visualProductInventory','track_stock','stock_quantity','Quantidade disponível','stockQuantity.disabled'] as$rule)$expect(str_contains($script,$rule),'controle visual de estoque ausente: '.$rule);
    foreach(['estado-estoque-produto','Sem controle de estoque','Esgotado','Baixo estoque','em estoque','Number(stock) <= 5'] as$rule)$expect(str_contains($script,$rule),'estado visual do estoque ausente: '.$rule);
    foreach(['estoque-livre','estoque-disponivel','estoque-baixo','estoque-esgotado','data-admin-theme="light"'] as$rule)$expect(str_contains($style,$rule),'aparência responsiva do estoque ausente: '.$rule);
    foreach(['filterDefinitions','Todos','Baixo estoque','Esgotados','Inativos','matchesVisualFilter','applyVisualFilter','aria-pressed',"editor.draggable = filter === 'all' && query === ''",'categoria-editor-visual'] as$rule)$expect(str_contains($script,$rule),'filtro seguro do editor ausente: '.$rule);
    foreach(['filtros-editor-visual','filtro-editor-visual','estado-vazio-filtro','aria-pressed="true"','overflow-x:auto'] as$rule)$expect(str_contains($style,$rule),'aparência responsiva dos filtros ausente: '.$rule);
    foreach(['visualCatalogSearch','Buscar produto ou categoria','normalizeVisualSearch','toLocaleLowerCase(\'pt-BR\')','matchesVisualSearch','activeVisualFilter','searchInput.addEventListener(\'input\'','clearSearch','aria-live','filter === \'all\' && query === \'\''] as$rule)$expect(str_contains($script,$rule),'busca instantânea do catálogo ausente: '.$rule);
    foreach(['busca-editor-visual','limpar-busca-editor','resultado-busca-editor','grid-template-columns:minmax(220px,1fr) auto auto'] as$rule)$expect(str_contains($style,$rule),'aparência responsiva da busca ausente: '.$rule);
    foreach(["if(\$action==='product/quick-update')",'money_to_cents','SELECT price_cents,active FROM products WHERE id=:id AND tenant_id=:tenant FOR UPDATE','UPDATE products SET price_cents=:price,active=:active','quick_update',"'before'=>", "'after'=>"] as$rule)$expect(str_contains($router,$rule),'edição rápida sem validação segura: '.$rule);
    foreach(['/admin/product/quick-update','edicao-rapida-produto','Preço rápido','Disponível','visualEditorButton','cartao-produto-editor-rapido'] as$rule)$expect(str_contains($script,$rule),'interface de edição rápida ausente: '.$rule);
    foreach(['cartao-produto-editor-rapido','edicao-rapida-produto','disponibilidade-rapida-produto','data-admin-theme="light"'] as$rule)$expect(str_contains($style,$rule),'aparência da edição rápida ausente: '.$rule);
    foreach(["if(\$action==='product/bulk-update')",'count($ids)>500','array_unique(array_map(\'intval\'','FOR UPDATE','count($productsToUpdate)!==count($ids)','price_percent','intdiv(PHP_INT_MAX','bulk_update'] as$rule)$expect(str_contains($router,$rule),'atualização em lote sem proteção: '.$rule);
    foreach(['/admin/product/bulk-update','data-bulk-product','Selecionar visíveis','Limpar seleção','product_ids','price_percent','updateBulkSelection','window.confirm'] as$rule)$expect(str_contains($script,$rule),'interface de atualização em lote ausente: '.$rule);
    foreach(['selecao-produto-lote','selecionado-lote','acoes-lote-editor','button:disabled'] as$rule)$expect(str_contains($style,$rule),'aparência da atualização em lote ausente: '.$rule);
    foreach(["if(\$action==='category/duplicate')",'SELECT name FROM categories WHERE id=:id AND tenant_id=:tenant FOR UPDATE','Cópia de ','INSERT INTO categories','image_path,stock_quantity,active','VALUES (:tenant,:category,:sort_order,:name,:description,:price,NULL,:stock,0)','product_option_groups',"'images_copied'=>false"] as$rule)$expect(str_contains($router,$rule),'duplicação segura da categoria ausente: '.$rule);
    foreach(['/admin/category/duplicate','duplicar-categoria-editor','Duplicar categoria','todos os seus produtos como rascunho','window.confirm'] as$rule)$expect(str_contains($script,$rule),'interface de duplicação da categoria ausente: '.$rule);
    foreach(["if(\$action==='product/import-preview')","if(\$action==='product/import-confirm')",'is_uploaded_file','1048576','count($rows)>=300','money_to_cents','random_bytes(24)',"\$_SESSION['catalog_import']",'hash_equals','tenant_id',"'import','product'",'Nenhum produto foi gravado'] as$rule)$expect(str_contains($router,$rule),'importação CSV sem validação segura: '.$rule);
    foreach(['catalogImportPreviewData','hash_equals','expires_at','user_id'] as$rule)$expect(str_contains($view,$rule),'prévia da importação não está vinculada à sessão: '.$rule);
    foreach(['Importar CSV','Baixar modelo CSV','catalog_csv','Validar e visualizar','catalogImportPreview','slice(0, 50)','Confirmar importação','/admin/product/import-cancel','textContent'] as$rule)$expect(str_contains($script,$rule),'interface guiada da importação ausente: '.$rule);
    foreach(['modal-importacao-catalogo','tabela-previa-importacao','acoes-previa-importacao','max-height:50vh'] as$rule)$expect(str_contains($style,$rule),'aparência responsiva da importação ausente: '.$rule);
    foreach(["\$path==='/admin/export/catalog'", "require_auth(['platform_admin','owner','manager'])",'JOIN categories c ON c.id=p.category_id AND c.tenant_id=p.tenant_id','WHERE p.tenant_id=:tenant','Content-Type: text/csv','X-Content-Type-Options: nosniff',"preg_match('/^[=+\\-@]/u", "'export','catalog'"] as$rule)$expect(str_contains($router,$rule),'exportação segura do catálogo ausente: '.$rule);
    foreach(['/admin/export/catalog','Exportar CSV','setAttribute(\'download\''] as$rule)$expect(str_contains($script,$rule),'ação de exportação do catálogo ausente: '.$rule);
});

$test('Cloudflare Turnstile protege formulários públicos com validação no servidor', static function () use ($expect): void {
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php')?:'';
    $router=file_get_contents(ROOT_PATH.'/public/index.php')?:'';
    $auth=file_get_contents(ROOT_PATH.'/src/views/auth.php')?:'';
    $privacy=file_get_contents(ROOT_PATH.'/src/views/privacy-request.php')?:'';
    $waitlist=file_get_contents(ROOT_PATH.'/src/views/waitlist.php')?:'';
    $review=file_get_contents(ROOT_PATH.'/src/views/review.php')?:'';
    $preflight=file_get_contents(ROOT_PATH.'/bin/preflight.php')?:'';
    $environment=file_get_contents(ROOT_PATH.'/.env.example')?:'';
    foreach(['TURNSTILE_ENABLED','TURNSTILE_SITE_KEY','TURNSTILE_SECRET_KEY'] as$variable)$expect(str_contains($environment,$variable),'variável Turnstile ausente: '.$variable);
    foreach(['turnstile_enabled','turnstile_widget','turnstile_script','turnstile_verify','require_turnstile','https://challenges.cloudflare.com/turnstile/v0/siteverify',"hash_equals(\$action",'CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS'] as$rule)$expect(str_contains($bootstrap,$rule),'validação Turnstile ausente: '.$rule);
    foreach(['frame-src','script-src','connect-src'] as$directive)$expect(str_contains($bootstrap,$directive)&&str_contains($bootstrap,'https://challenges.cloudflare.com'),'CSP não libera Turnstile com escopo controlado');
    foreach(["require_turnstile('register'","require_turnstile('login'","require_turnstile('password_reset'","require_turnstile('privacy_request'","require_turnstile('waitlist'",'product_review','courier_review'] as$rule)$expect(str_contains($router,$rule),'rota pública sem Turnstile: '.$rule);
    foreach(["turnstile_widget('login')","turnstile_widget('register')","turnstile_widget('password_reset')",'turnstile_script()'] as$rule)$expect(str_contains($auth,$rule),'autenticação sem widget Turnstile: '.$rule);
    $expect(str_contains($privacy,"turnstile_widget('privacy_request')")&&str_contains($waitlist,"turnstile_widget('waitlist')"),'formulários públicos auxiliares sem Turnstile');
    $expect(str_contains($review,"turnstile_widget('product_review')")&&str_contains($review,"turnstile_widget('courier_review')"),'avaliações sem Turnstile');
    foreach(['TURNSTILE_ENABLED','TURNSTILE_SITE_KEY','TURNSTILE_SECRET_KEY'] as$rule)$expect(str_contains($preflight,$rule),'preflight não valida Turnstile: '.$rule);
});

$test('detalhes do produto e checkout mantêm conteúdo legível', static function () use ($expect): void {
    $view=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $script=file_get_contents(ROOT_PATH.'/public/assets/store.js')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    $expect(str_contains($view,"'pix'=>'Pix'"),'rótulo público do Pix incorreto');
    $expect(str_contains($view,'autocomplete="street-address"'),'endereço sem identificação');
    foreach(['openProductDetails','productDetails','productQuantity','abrir-detalhes-produto','source?.dataset.description','source.dataset.image'] as $rule) $expect(str_contains($script,$rule),'detalhes ausentes: '.$rule);
    $expect(!str_contains($script,'if (!groups.length) return addConfiguredProduct(product)'), 'produto sem opções ignora os detalhes');
    foreach(['#checkoutForm [hidden]','#addressField textarea','body.corpo-acompanhamento .codigo-pedido strong'] as $rule) $expect(str_contains($css,$rule),'proteção visual ausente: '.$rule);
});

$test('destaques separam título e apoio e exibem descrição legível', static function () use ($expect): void {
    $css=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    $expect(str_contains($css,'body #promocoes>.cabecalho-secao{display:grid;grid-template-columns:minmax(0,1fr)'), 'título dos destaques não separa o texto de apoio');
    $expect(str_contains($css,'.recurso>div:last-child>p{font-size:15px;line-height:1.6'), 'descrição dos destaques pequena');
    $expect(str_contains($css,'#productDetails p{font-size:16px;line-height:1.65}'), 'descrição completa sem tamanho legível');
    $expect(str_contains($css,'.recurso .abrir-detalhes-produto{background:none'), 'nome do produto recebe aparência de botão de compra');
});

$test('catálogo compacto prioriza produtos e mantém banner opcional', static function () use ($expect): void {
    $view=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $css=file_get_contents(ROOT_PATH.'/public/assets/loja.css')?:'';
    $expect(str_contains($view,'<main class="catalogo-compacto">'),'layout compacto ausente');
    $expect(str_contains($view,'class="banner-catalogo"'),'banner da empresa ausente');
    $expect(!str_contains($view,'<main><section class="destaque-principal'),'hero promocional antigo permanece');
    $expect(!str_contains($view,'Escolhas apresentadas diretamente pelo catálogo atualizado.'),'texto genérico permanece');
    foreach(['.catalogo-compacto #cardapio{order:1}','.catalogo-compacto #promocoes{order:2','grid-template-columns:minmax(0,1fr) 104px'] as $rule) $expect(str_contains($css,$rule),'regra compacta ausente: '.$rule);
});

$test('seletor visual preserva identidade por padrão e usa modelos permitidos', static function () use ($expect): void {
    $view=file_get_contents(ROOT_PATH.'/src/views/settings.php')?:'';
    $worker=file_get_contents(ROOT_PATH.'/public/sw.js')?:'';
    $store=file_get_contents(ROOT_PATH.'/src/views/store.php')?:'';
    $expect(str_contains($view,'class="modelo-visual-card"')&&str_contains($view,'type="radio" name="storefront_preset"'),'cartões de seleção ausentes');
    $expect(!str_contains($view,'name="apply_preset_identity" checked'),'identidade é sobrescrita por padrão');
    $expect(str_contains($view,'foreach($availablePresets as$key=>$presetOption)'),'modelos não usam lista compatível com segmento');
    $expect(str_contains($view,'Miniaturas ilustrativas'),'prévia promete fidelidade não implementada');
    foreach(['loja.css','store.js'] as $asset){
        preg_match('~'.preg_quote($asset,'~').'\\?v=([^"\x27]+)~',$store,$match);
        $expect(!empty($match[1])&&str_contains($worker,$asset.'?v='.$match[1]),'PWA não acompanha versão da vitrine: '.$asset);
    }
});

$test('cadastro alimentar oferece exemplos próprios para cada modelo', static function () use ($expect): void {
    foreach(['pizzaria-italiana'=>'Pizza Margherita','alimentacao-artesanal'=>'Hambúrguer artesanal','pastelaria-brasileira'=>'Pastel de queijo','almoco-caseiro'=>'Prato feito'] as$preset=>$name){
        $example=onboarding_food_product($preset);
        $expect($example!==null&&$example['name']===$name,'exemplo incompatível com o modelo');
        $expect(is_int($example['price'])&&$example['price']>0,'preço não está em centavos');
        $expect(str_contains($example['description'],'Exemplo:'),'descrição não orienta a personalização');
    }
    $expect(onboarding_food_product('moda-editorial')===null,'produto alimentar criado para outro segmento');
    $expect(onboarding_food_product('invalido')===null,'preset desconhecido aceito');
});

$test('produto demonstrativo nasce inativo somente no cadastro e preserva tenant', static function () use ($expect): void {
    $router=file_get_contents(ROOT_PATH.'/public/index.php');
    $bootstrap=file_get_contents(ROOT_PATH.'/src/bootstrap.php');
    $expect(substr_count($router,'create_onboarding_product(')===1,'exemplo pode ser recriado fora do cadastro');
    $expect(str_contains($router,"if(\$type==='Alimentação')create_onboarding_product(\$pdo,\$tenantId,\$presetKey)"),'cadastro não restringe exemplo ao segmento');
    $expect(str_contains($bootstrap,'price_cents,active,is_demo) VALUES (:tenant,:category,:name,:description,:price,0,1)'),'exemplo pode nascer publicado');
    $expect(str_contains($bootstrap,'SELECT id FROM products WHERE tenant_id=:tenant LIMIT 1'),'falta proteção contra catálogo existente');
    $expect(str_contains($router,'UPDATE products SET is_demo=0 WHERE id=:id AND tenant_id=:tenant'),'edição não encerra demonstração com isolamento');
});

$test('loja fechada não renderiza catálogo e mantém identidade e horários', static function () use ($expect): void {
    $store=['name'=>'Loja <teste>','slug'=>'loja-teste','business_type'=>'Alimentação'];
    $preferences=['storefront_preset'=>'pizzaria-italiana'];
    $hours=[['weekday'=>1,'opens_at'=>'18:00:00','closes_at'=>'23:00:00','closed'=>0]];
    ob_start();require ROOT_PATH.'/src/views/store-closed.php';$html=ob_get_clean();
    $expect(str_contains($html,'Loja &lt;teste&gt;'),'nome não escapado');
    $expect(str_contains($html,'18:00 – 23:00'),'horário ausente');
    $expect(str_contains($html,'data-theme="light"'),'tema do modelo não preservado');
    $expect(str_contains($html,'link seguro recebido'),'acompanhamento existente não explicado');
    foreach(['add-product','cartDrawer','checkoutForm','productOptionsData'] as$selector)$expect(!str_contains($html,$selector),'loja fechada inclui compra ou produtos');
    $router=file_get_contents(ROOT_PATH.'/public/index.php');
    $expect(str_contains($router,"if(!\$store['store_open']&&!\$canPreviewClosed)"),'falta bloqueio do catálogo público');
    $expect(str_contains($router,"['store_open'=>false,'products'=>[]]"),'disponibilidade expõe produtos fechados');
    $expect(str_contains($router,'SELECT store_open FROM tenants WHERE id=:tenant FOR UPDATE'),'pedidos não sincronizam fechamento');
});

$failed = 0;
foreach ($tests as [$name, $passed, $message]) {
    echo ($passed ? 'OK' : 'FALHOU') . " - {$name}" . ($message ? ": {$message}" : '') . PHP_EOL;
    if (!$passed) $failed++;
}
echo PHP_EOL . count($tests) . ' testes, ' . $failed . ' falhas.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);

