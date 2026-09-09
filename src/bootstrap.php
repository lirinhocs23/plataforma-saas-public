<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';

function load_env(string $file): void
{
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv("{$key}={$value}");
    }
}

load_env(ROOT_PATH . '/.env');
require_once ROOT_PATH . '/src/mailer.php';

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $error): never {
        error_log(sprintf('[mjdev] %s in %s:%d', $error->getMessage(), $error->getFile(), $error->getLine()));
        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo 'Não foi possível concluir esta solicitação. Tente novamente em instantes.';
        exit;
    });
}

if (env('APP_ENV', 'production') === 'production') {
    if (strlen((string)env('APP_KEY', '')) < 32) throw new RuntimeException('APP_KEY deve possuir pelo menos 32 caracteres em produção.');
    $legalEmail = (string)env('PRIVACY_CONTACT_EMAIL', '');
    if (strlen(trim((string)env('LEGAL_OPERATOR_NAME', ''))) < 3 || !filter_var($legalEmail, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($legalEmail), '@example.invalid')) {
        throw new RuntimeException('Configure LEGAL_OPERATOR_NAME e PRIVACY_CONTACT_EMAIL antes de iniciar em produção.');
    }
    if (env('TURNSTILE_ENABLED', 'false') === 'true' && (strlen(trim((string)env('TURNSTILE_SITE_KEY', ''))) < 10 || strlen(trim((string)env('TURNSTILE_SECRET_KEY', ''))) < 10)) {
        throw new RuntimeException('TURNSTILE_ENABLED exige as chaves pública e secreta do Cloudflare Turnstile.');
    }
}

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', env('APP_DEBUG', 'false') === 'true' ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(env('SESSION_NAME', 'mjdev_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => env('SESSION_SECURE_COOKIE', 'true') === 'true',
        'httponly' => true,
        'samesite' => env('SESSION_SAME_SITE', 'Lax'),
    ]);
    session_start();
    $sessionLifetime = max(5, (int)env('SESSION_LIFETIME_MINUTES', '120')) * 60;
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > $sessionLifetime) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Origin-Agent-Cluster: ?1');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; frame-src 'self' https://secure-fields.mercadopago.com https://challenges.cloudflare.com; form-action 'self'; img-src 'self' data:; media-src 'self'; font-src 'self' https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' https://sdk.mercadopago.com https://challenges.cloudflare.com; connect-src 'self' https://api.mercadopago.com https://secure-fields.mercadopago.com https://challenges.cloudflare.com; manifest-src 'self'; worker-src 'self'");
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', env('DB_HOST', 'mysql'), env('DB_PORT', '3306'), env('DB_DATABASE', 'mjdev_delivery'), env('DB_CHARSET', 'utf8mb4'));
    $pdo = new PDO($dsn, env('DB_USERNAME', 'mjdev_app'), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function json_response(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403); exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function turnstile_enabled(): bool
{
    return env('TURNSTILE_ENABLED', 'false') === 'true'
        && trim((string)env('TURNSTILE_SITE_KEY', '')) !== ''
        && trim((string)env('TURNSTILE_SECRET_KEY', '')) !== '';
}

function turnstile_widget(string $action): string
{
    if (!turnstile_enabled() || preg_match('/^[a-z0-9_-]{1,32}$/i', $action) !== 1) return '';
    return '<div class="turnstile-container"><div class="cf-turnstile" data-sitekey="'.e((string)env('TURNSTILE_SITE_KEY', '')).'" data-action="'.e($action).'" data-theme="auto" data-size="flexible" data-appearance="interaction-only"></div><small>Verificação de segurança protegida pelo Cloudflare Turnstile.</small></div>';
}

function turnstile_script(): string
{
    return turnstile_enabled() ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' : '';
}

function turnstile_verify(string $action): bool
{
    if (!turnstile_enabled()) return true;
    if (preg_match('/^[a-z0-9_-]{1,32}$/i', $action) !== 1) return false;
    $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '' || strlen($token) > 2048) return false;
    $handle = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($handle === false) return false;
    $payload = ['secret'=>(string)env('TURNSTILE_SECRET_KEY', ''),'response'=>$token];
    $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteIp !== '') $payload['remoteip'] = $remoteIp;
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]);
    $body = curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);curl_close($handle);
    if (!is_string($body) || $status !== 200) { error_log('[mjdev] turnstile validation unavailable status='.$status); return false; }
    try{$result=json_decode($body,true,16,JSON_THROW_ON_ERROR);}catch(Throwable){return false;}
    if (empty($result['success']) || !hash_equals($action,(string)($result['action']??''))) return false;
    $expectedHost = strtolower((string)(parse_url((string)env('APP_URL',''),PHP_URL_HOST)??''));
    $verifiedHost = strtolower(trim((string)($result['hostname']??'')));
    return $expectedHost !== '' && $verifiedHost !== '' && hash_equals($expectedHost,$verifiedHost);
}

function require_turnstile(string $action, string $redirectPath): void
{
    if (turnstile_verify($action)) return;
    flash('error','Não foi possível confirmar a verificação de segurança. Tente novamente.');
    redirect($redirectPath);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])||empty($_SESSION['auth_session_token'])) return null;
    static $user;
    if ($user !== null) return $user ?: null;
    $stmt = db()->prepare('SELECT u.id,u.tenant_id,u.name,u.email,u.email_verified_at,u.two_factor_enabled,u.new_device_alerts_enabled,u.role,u.active,u.session_version,t.name tenant_name,t.slug,t.business_type,t.business_segment_code,t.published_at,t.suspended_at FROM users u JOIN tenants t ON t.id=u.tenant_id WHERE u.id=:id LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: false;
    if (!$user || !$user['active'] || ($user['suspended_at'] && $user['role']!=='platform_admin')) { $_SESSION = []; return null; }
    if(!isset($_SESSION['session_version']))$_SESSION['session_version']=(int)$user['session_version'];
    if((int)$_SESSION['session_version']!==(int)$user['session_version']){$_SESSION=[];return null;}
    $sessionHash=hash('sha256',(string)$_SESSION['auth_session_token']);$session=db()->prepare('SELECT id,last_seen_at FROM user_sessions WHERE session_token_hash=:hash AND tenant_id=:tenant AND user_id=:user AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1');$session->execute(['hash'=>$sessionHash,'tenant'=>$user['tenant_id'],'user'=>$user['id']]);$activeSession=$session->fetch();if(!$activeSession){$_SESSION=[];return null;}$_SESSION['auth_session_id']=(int)$activeSession['id'];if(strtotime((string)$activeSession['last_seen_at'])<time()-300){$minutes=max(5,(int)env('SESSION_LIFETIME_MINUTES','120'));$renew=db()->prepare('UPDATE user_sessions SET last_seen_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL :minutes MINUTE) WHERE id=:id');$renew->bindValue(':minutes',$minutes,PDO::PARAM_INT);$renew->bindValue(':id',(int)$activeSession['id'],PDO::PARAM_INT);$renew->execute();}
    return $user;
}

function device_label(): string
{
    $agent=(string)($_SERVER['HTTP_USER_AGENT']??'');$browser=str_contains($agent,'Edg/')?'Edge':(str_contains($agent,'Chrome/')?'Chrome':(str_contains($agent,'Firefox/')?'Firefox':(str_contains($agent,'Safari/')?'Safari':'Navegador')));$system=str_contains($agent,'Windows')?'Windows':(str_contains($agent,'Android')?'Android':(str_contains($agent,'iPhone')||str_contains($agent,'iPad')?'iOS':(str_contains($agent,'Mac OS')?'macOS':(str_contains($agent,'Linux')?'Linux':'dispositivo'))));return substr($browser.' · '.$system,0,120);
}

function start_authenticated_session(int $userId,int $tenantId,int $sessionVersion): void
{
    session_regenerate_id(true);$token=bin2hex(random_bytes(32));$minutes=max(5,(int)env('SESSION_LIFETIME_MINUTES','120'));$agent=(string)($_SERVER['HTTP_USER_AGENT']??'');$agentHash=hash_hmac('sha256',$agent,(string)env('APP_KEY',''));$label=device_label();
    $known=db()->prepare('SELECT COUNT(*) total,COUNT(CASE WHEN user_agent_hash=:agent THEN 1 END) recognized FROM user_sessions WHERE tenant_id=:tenant AND user_id=:user');$known->execute(['agent'=>$agentHash,'tenant'=>$tenantId,'user'=>$userId]);$deviceState=$known->fetch()?:['total'=>0,'recognized'=>0];
    $insertSession=db()->prepare('INSERT INTO user_sessions (tenant_id,user_id,session_token_hash,session_version,ip_hash,user_agent_hash,device_label,last_seen_at,expires_at) VALUES (:tenant,:user,:token,:version,:ip,:agent,:label,NOW(),DATE_ADD(NOW(),INTERVAL :minutes MINUTE))');$insertSession->bindValue(':tenant',$tenantId,PDO::PARAM_INT);$insertSession->bindValue(':user',$userId,PDO::PARAM_INT);$insertSession->bindValue(':token',hash('sha256',$token));$insertSession->bindValue(':version',$sessionVersion,PDO::PARAM_INT);$insertSession->bindValue(':ip',client_ip_hash());$insertSession->bindValue(':agent',$agentHash);$insertSession->bindValue(':label',$label);$insertSession->bindValue(':minutes',$minutes,PDO::PARAM_INT);$insertSession->execute();$_SESSION['user_id']=$userId;$_SESSION['session_version']=$sessionVersion;$_SESSION['auth_session_token']=$token;$_SESSION['auth_session_id']=(int)db()->lastInsertId();
    if((int)$deviceState['total']>0&&(int)$deviceState['recognized']===0){$account=db()->prepare('SELECT id,tenant_id,name,email,email_verified_at,new_device_alerts_enabled FROM users WHERE id=:user AND tenant_id=:tenant AND active=1 LIMIT 1');$account->execute(['user'=>$userId,'tenant'=>$tenantId]);$alertUser=$account->fetch();if($alertUser){try{audit($tenantId,$userId,'new_device','user_session',(int)$_SESSION['auth_session_id'],['device_label'=>$label]);}catch(Throwable $error){error_log('[mjdev] new device audit failure: '.get_class($error));}try{issue_new_device_alert($alertUser,$label);}catch(Throwable $error){error_log('[mjdev] new device alert queue failure: '.get_class($error));}}}
}

function require_auth(array $roles = []): array
{
    $user = current_user();
    if (!$user) redirect('/entrar');
    if ($roles && !in_array($user['role'], $roles, true)) { http_response_code(403); exit('Acesso negado.'); }
    header('Cache-Control: no-store, private');
    return $user;
}

function privileged_two_factor_required(array $user): bool
{
    return env('REQUIRE_PRIVILEGED_2FA','false')==='true'
        && in_array((string)($user['role']??''),['platform_admin','owner','manager'],true)
        && empty($user['two_factor_enabled']);
}

function enforce_privileged_two_factor(array $user): void
{
    if(!privileged_two_factor_required($user))return;
    flash('error','Ative a verificação em duas etapas para continuar usando os recursos administrativos.');
    redirect('/admin?section=settings#seguranca-conta');
}

function business_segments(): array
{
    return [
        'alimentacao'=>'Alimentação','moda'=>'Moda e Vestuário','beleza'=>'Beleza',
        'pet-shop'=>'Pet Shop','casa-decoracao'=>'Casa e Decoração','farmacia'=>'Farmácia',
        'esportes'=>'Esportes','informatica'=>'Informática','outro'=>'Outro',
    ];
}

function business_segment_code(string $value): string
{
    $segments=business_segments();
    if(isset($segments[$value]))return $value;
    $code=array_search($value,$segments,true);
    return $code===false?'outro':$code;
}

function business_type_from_segment(string $code): ?string
{
    return business_segments()[$code]??null;
}

function admin_section_labels(): array
{
    return ['dashboard'=>'Dashboard','stock'=>'Visão geral','moves'=>'Movimentações','categories'=>'Categorias','products'=>'Produtos','visual-catalog'=>'Editor visual','options'=>'Grupo de opções','variant-stock'=>'Estoque por variação','combos'=>'Kit / Combo','coupons'=>'Grupo de desconto','product-reviews'=>'Avaliações','waitlist'=>'Lista de espera','customers'=>'Clientes','subscriptions'=>'Assinaturas','qr'=>'QR code da vitrine','orders'=>'Pedidos','salon'=>'Salão','zones'=>'Bairros / Taxas','reviews'=>'Avaliações','users'=>'Funcionários','drivers'=>'Entregadores','driver-reviews'=>'Avaliações para entregadores','kitchen'=>'Cozinha','waiter'=>'Garçom','courier'=>'Entregador','pos'=>'PDV','menu-preview'=>'Ver cardápio','print-menu'=>'Imprimir cardápio','reports'=>'Relatórios','driver-report'=>'Relatório de entregadores','finance'=>'DRE / Financeiro','onboarding'=>'Primeiros passos','settings'=>'Configurações gerais','pwa'=>'App / QR Code','billing'=>'Assinatura & Pagamento','webhooks'=>'Webhooks de saída','lgpd'=>'LGPD / Privacidade','audit'=>'Logs de auditoria'];
}

function admin_sections_for_role(string $role): array
{
    $all=array_keys(admin_section_labels());
    return match($role){
        'platform_admin','owner','manager'=>$all,
        'attendant'=>['dashboard','customers','orders','salon','pos'],
        'waiter'=>['dashboard','orders','salon','waiter'],
        'kitchen'=>['dashboard','orders','kitchen'],
        'pos'=>['dashboard','products','customers','orders','salon','pos'],
        'courier'=>['dashboard','orders','courier'],
        default=>[],
    };
}

function business_type_supports_restaurant_operations(string $businessType): bool
{
    return business_segment_code($businessType) === 'alimentacao';
}

function role_allowed_for_business_type(string $role, string $businessType): bool
{
    if (in_array($role, ['platform_admin', 'owner'], true)) return true;
    return in_array($role, staff_roles_for_business_type($businessType), true);
}

function staff_roles_for_business_type(string $businessType): array
{
    $roles=['manager','attendant','pos','courier'];
    if (business_type_supports_restaurant_operations($businessType)) {
        array_splice($roles,2,0,['waiter','kitchen']);
    }
    return $roles;
}

function admin_sections_for_business_type(string $businessType): array
{
    $sections = array_keys(admin_section_labels());
    if (business_type_supports_restaurant_operations($businessType)) return $sections;
    return array_values(array_diff($sections, ['salon', 'kitchen', 'waiter']));
}

function admin_sections_for_context(string $role, string $businessType): array
{
    if (!role_allowed_for_business_type($role, $businessType)) return [];
    return array_values(array_intersect(
        admin_sections_for_role($role),
        admin_sections_for_business_type($businessType)
    ));
}

function catalog_vocabulary(string $businessType): array
{
    return match ($businessType) {
        'Alimentação' => [
            'options' => 'Adicionais e escolhas',
            'option_group' => 'Grupo de adicionais',
            'option_item' => 'Adicional ou escolha',
            'bundles' => 'Combos',
            'bundle' => 'Combo',
            'category_example' => 'Ex.: Hambúrgueres, Pizzas ou Bebidas',
            'details_placeholder' => 'Ingredientes, preparo e informações importantes',
            'notes_placeholder' => 'Ex.: retirar cebola',
        ],
        'Moda e Vestuário' => [
            'options' => 'Variações de tamanho e cor',
            'option_group' => 'Grupo de variações',
            'option_item' => 'Tamanho, cor ou modelo',
            'bundles' => 'Conjuntos e kits',
            'bundle' => 'Conjunto ou kit',
            'category_example' => 'Ex.: Moda feminina, Camisetas ou Acessórios',
            'details_placeholder' => 'Material, modelagem, medidas e cuidados',
            'notes_placeholder' => 'Ex.: confirmar tamanho ou cor',
        ],
        'Informática' => [
            'options' => 'Variações e especificações',
            'option_group' => 'Grupo de especificações',
            'option_item' => 'Memória, armazenamento ou voltagem',
            'bundles' => 'Kits de informática',
            'bundle' => 'Kit',
            'category_example' => 'Ex.: Computadores, Periféricos ou Acessórios',
            'details_placeholder' => 'Especificações, compatibilidade e garantia',
            'notes_placeholder' => 'Ex.: confirmar compatibilidade',
        ],
        'Farmácia' => [
            'options' => 'Apresentações e variações',
            'option_group' => 'Grupo de apresentações',
            'option_item' => 'Apresentação ou quantidade',
            'bundles' => 'Kits de cuidados',
            'bundle' => 'Kit',
            'category_example' => 'Ex.: Higiene, Cuidados pessoais ou Dermocosméticos',
            'details_placeholder' => 'Apresentação, quantidade e orientações do fabricante',
            'notes_placeholder' => 'Ex.: confirmar apresentação',
        ],
        default => [
            'options' => 'Variações e opções',
            'option_group' => 'Grupo de variações',
            'option_item' => 'Variação ou opção',
            'bundles' => 'Kits e conjuntos',
            'bundle' => 'Kit ou conjunto',
            'category_example' => 'Ex.: Destaques, Novidades ou Acessórios',
            'details_placeholder' => 'Características, materiais e informações importantes',
            'notes_placeholder' => 'Ex.: informe sua preferência',
        ],
    };
}

function admin_operational_routes(): array
{
    return [
        'kitchen'=>'/cozinha',
        'waiter'=>'/garcom',
        'courier'=>'/entregador',
        'pos'=>'/pdv',
        'salon'=>'/salao',
    ];
}

function default_panel_path_for_role(string $role): string
{
    return match ($role) {
        'platform_admin' => '/platform',
        'kitchen' => '/cozinha',
        'waiter' => '/garcom',
        'courier' => '/entregador',
        'pos' => '/pdv',
        default => '/admin',
    };
}

function authorized_login_destination(array $user, ?string $requested): string
{
    $fallback=default_panel_path_for_role((string)$user['role']);
    if (!$requested) return $fallback;
    $section=array_flip(admin_operational_routes())[$requested]??null;
    if (!$section) return $fallback;
    return in_array($section,admin_sections_for_context((string)$user['role'],(string)$user['business_type']),true)
        ? $requested
        : $fallback;
}

function admin_section_url(string $section): string
{
    return admin_operational_routes()[$section] ?? '/admin?section='.rawurlencode($section);
}

function admin_navigation(string $role, string $active): string
{
    $labels=admin_section_labels();
    $context=current_user();
    $businessType=(string)($context['business_type']??'Outro');
    $catalogWords=catalog_vocabulary($businessType);
    $labels['options']=$catalogWords['options'];
    $labels['combos']=$catalogWords['bundles'];
    if(!business_type_supports_restaurant_operations($businessType)){
        $labels['menu-preview']='Ver catálogo';
        $labels['print-menu']='Imprimir catálogo';
    }
    $allowed=array_flip(admin_sections_for_context($role,$businessType));
    $groups=[
        'Dashboard'=>['icon'=>'⌂','sections'=>['dashboard']],
        'Estoque'=>['icon'=>'▦','sections'=>['stock','moves']],
        'Catálogo'=>['icon'=>'◫','sections'=>['visual-catalog','categories','products','options','variant-stock','combos','coupons','product-reviews','waitlist']],
        'Clientes'=>['icon'=>'♙','sections'=>['customers','subscriptions']],
        'Vitrine'=>['icon'=>'◇','sections'=>['qr']],
        'Operação'=>['icon'=>'◉','sections'=>['orders','salon','zones','reviews']],
        'Relacionamento'=>['icon'=>'♧','sections'=>['users','drivers','driver-reviews']],
        'Acessos Rápidos'=>['icon'=>'⚡','sections'=>['kitchen','waiter','courier','pos','menu-preview','print-menu']],
        'Relatórios'=>['icon'=>'▥','sections'=>['reports','driver-report','finance']],
        'Configurações'=>['icon'=>'⚙','sections'=>['onboarding','settings','pwa','billing','webhooks','lgpd','audit']],
    ];
    $icons=['dashboard'=>'⌂','orders'=>'◉','pos'=>'▤','salon'=>'▥','kitchen'=>'♨','waiter'=>'♧','courier'=>'●','visual-catalog'=>'✦','categories'=>'▦','products'=>'◫','options'=>'☷','variant-stock'=>'#','combos'=>'◇','coupons'=>'％','product-reviews'=>'★','qr'=>'▣','menu-preview'=>'◎','print-menu'=>'▧','customers'=>'♙','subscriptions'=>'↻','waitlist'=>'⌛','zones'=>'⌖','reviews'=>'☆','drivers'=>'♙','driver-reviews'=>'★','stock'=>'▦','moves'=>'≡','reports'=>'▥','driver-report'=>'▥','finance'=>'$','users'=>'♙','onboarding'=>'✓','settings'=>'⚙','pwa'=>'▣','billing'=>'◈','webhooks'=>'⌁','lgpd'=>'§','audit'=>'⌁'];
    ob_start();
    foreach($groups as $group=>$config){
        $sections=$config['sections'];
        $visible=array_values(array_filter($sections,fn($key)=>isset($allowed[$key])&&isset($labels[$key])));
        if(!$visible)continue;
        $open=in_array($active,$visible,true)||$group==='Dashboard';
        $submenuId='submenu-'.slugify($group); ?>
            <div class="grupo-navegacao <?= $open?'aberto':'' ?>">
                <button type="button" aria-expanded="<?= $open?'true':'false' ?>" aria-controls="<?= e($submenuId) ?>"><span class="icone-navegacao"><?= e($config['icon']) ?></span><span><?= e($group) ?></span><b class="seta-navegacao" aria-hidden="true">⌄</b></button>
                <div class="subnavegacao" id="<?= e($submenuId) ?>" aria-hidden="<?= $open?'false':'true' ?>"><?php foreach($visible as$key):$quickLink=$group==='Acessos Rápidos';?><a class="item-navegacao <?= $active===$key?'ativo':'' ?>" href="<?= e(admin_section_url($key)) ?>" <?= $active===$key?'aria-current="page"':'' ?> <?= $quickLink?'target="_blank" rel="noopener"':'' ?>><span class="icone-navegacao"><?= e($icons[$key]??'•') ?></span><span><?= e($labels[$key]) ?></span></a><?php endforeach;?></div>
            </div>
        <?php
    }
    return (string)ob_get_clean();
}

function admin_profile_menu(array $user): string
{
    $roleLabels=['platform_admin'=>'Administrador da plataforma','owner'=>'Proprietário','manager'=>'Gerente','attendant'=>'Atendente','waiter'=>'Garçom','kitchen'=>'Cozinha','pos'=>'PDV','courier'=>'Entregador'];
    $initials=strtoupper(substr(trim((string)$user['name']),0,2));
    ob_start(); ?>
    <details class="menu-perfil">
        <summary class="perfil"><i><?= e($initials) ?></i><span><strong><?= e($user['name']) ?></strong><small><?= e($roleLabels[$user['role']]??$user['role']) ?></small></span><b>⌄</b></summary>
        <div class="painel-perfil"><strong><?= e($user['name']) ?></strong><small><?= e($user['email']??'') ?></small><span class="rotulo-tema">APARÊNCIA DO PAINEL</span><div class="opcoes-tema" aria-label="Tema do painel"><button type="button" data-admin-theme="light">Claro</button><button type="button" data-admin-theme="dark">Escuro</button><button type="button" data-admin-theme="system">Sistema</button></div><form method="post" action="/sair"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="botao perigo" type="submit">Sair</button></form></div>
    </details>
    <?php return (string)ob_get_clean();
}

function admin_notifications_menu(array $orders, array $user): string
{
    $tenant=(int)($user['tenant_id']??0);$preferences=['notification_new_order'=>1,'notification_cancelled_order'=>1,'notification_low_stock'=>1,'minimum_stock_alert'=>5];
    if($tenant>0){$stmt=db()->prepare('SELECT notification_new_order,notification_cancelled_order,notification_low_stock,minimum_stock_alert FROM tenant_preferences WHERE tenant_id=:tenant');$stmt->execute(compact('tenant'));$preferences=array_merge($preferences,$stmt->fetch()?:[]);}
    $notifications=[];
    if($preferences['notification_new_order'])foreach($orders as$order)if(($order['status']??'')==='received')$notifications[]=['type'=>'order','label'=>($order['public_code']??'Pedido').' · '.($order['customer_name']??'Cliente'),'detail'=>'Novo pedido recebido','url'=>'/admin?section=orders'];
    if($preferences['notification_cancelled_order'])foreach($orders as$order)if(in_array(($order['status']??''),['cancelled','rejected'],true)&&strtotime((string)($order['updated_at']??''))>=time()-86400)$notifications[]=['type'=>'cancelled','label'=>($order['public_code']??'Pedido').' · '.($order['customer_name']??'Cliente'),'detail'=>($order['status']==='rejected'?'Pedido recusado':'Pedido cancelado'),'url'=>'/admin?section=orders'];
    if($tenant>0&&$preferences['notification_low_stock']&&in_array((string)($user['role']??''),['platform_admin','owner','manager','attendant','pos'],true)){$threshold=max(0,min(100000,(int)$preferences['minimum_stock_alert']));$stmt=db()->prepare('SELECT name,stock_quantity FROM products WHERE tenant_id=:tenant AND active=1 AND stock_quantity IS NOT NULL AND stock_quantity<=:threshold ORDER BY stock_quantity,name LIMIT 5');$stmt->execute(['tenant'=>$tenant,'threshold'=>$threshold]);foreach($stmt->fetchAll() as$product)$notifications[]=['type'=>'stock','label'=>$product['name'],'detail'=>'Estoque baixo · '.(int)$product['stock_quantity'].' unidade(s)','url'=>'/admin?section=stock'];}
    $notifications=array_slice($notifications,0,12);
    ob_start(); ?>
    <details class="menu-notificacoes">
        <summary class="icone notificacao" aria-label="<?= $notifications?count($notifications).' notificações':'Sem notificações' ?>"><span class="icone-sino" aria-hidden="true"><i></i></span><?php if($notifications):?><b><?= min(99,count($notifications)) ?></b><?php endif;?></summary>
        <div class="painel-notificacoes">
            <div class="cabecalho-painel-flutuante"><div><span class="sobretitulo">NOTIFICAÇÕES</span><strong><?= $notifications?'Atenção necessária':'Tudo tranquilo por aqui' ?></strong></div><?php if($notifications):?><span class="etiqueta amarelo"><?= count($notifications) ?></span><?php endif;?></div>
            <?php if(!$notifications):?><div class="estado-vazio compacto"><span class="icone-vazio">♢</span><b>Sem notificações</b><p>Novos pedidos e alertas operacionais aparecerão aqui.</p></div><?php else:?><div class="lista-notificacoes"><?php foreach($notifications as$notification):?><a href="<?= e($notification['url']) ?>" class="notificacao-<?= e($notification['type']) ?>"><span><b><?= e($notification['label']) ?></b><small><?= e($notification['detail']) ?></small></span></a><?php endforeach;?></div><a class="botao pequeno completo" href="/admin?section=settings#recursos-ativos">Configurar alertas</a><?php endif;?>
        </div>
    </details>
    <?php return (string)ob_get_clean();
}

function admin_header_actions(array $orders, array $user): string
{
    ob_start(); ?>
    <label class="busca-global"><span aria-hidden="true">⌕</span><input type="search" data-admin-search placeholder="Buscar nesta página…" aria-label="Buscar nesta página"></label>
    <button class="icone" type="button" data-admin-sound aria-label="Ativar ou desativar som" aria-pressed="false">♫</button>
    <?= admin_notifications_menu($orders,$user) ?>
    <?= admin_profile_menu($user) ?>
    <?php return (string)ob_get_clean();
}

function admin_store_card(array $store): string
{
    ob_start(); ?>
    <div class="cartao-loja"><span class="ponto-ativo <?= $store['store_open']?'':'inativo' ?>"></span><div><strong><?= $store['store_open']?'Loja aberta':'Loja fechada' ?></strong><small><?= $store['published_at']?'Catálogo publicado':'Publicação pendente' ?></small></div><?php if($store['published_at']):?><a href="/loja/<?= e($store['slug']) ?>?editar=1" target="_blank" rel="noopener">Editar loja</a><?php else:?><a href="/admin?section=settings">Configurar loja</a><?php endif;?></div>
    <?php return (string)ob_get_clean();
}

function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $value = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $value; }

function slugify(string $value): string
{
    $value = strtr($value, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N',
    ]);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '');
    return trim($value, '-') ?: 'loja';
}

function storefront_presets(): array
{
    return [
        'alimentacao-artesanal'=>['name'=>'Hamburgueria artesanal','business_types'=>['Alimentação'],'primary_color'=>'#F26B21','secondary_color'=>'#111111','hero_title'=>'Burger artesanal.','hero_highlight'=>'Feito na hora.','hero_cta_text'=>'Ver cardápio','nav_featured'=>'Combos','nav_catalog'=>'Cardápio','catalog_eyebrow'=>'BURGERS DA CASA','catalog_title'=>'Os favoritos da noite','catalog_search'=>'Buscar burger, combo ou acompanhamento','trust_item'=>'Preparado na brasa','theme_class'=>'tema-hamburgueria','color_scheme'=>'dark'],
        'pizzaria-italiana'=>['name'=>'Pizzaria italiana','business_types'=>['Alimentação'],'primary_color'=>'#C6402D','secondary_color'=>'#173F35','hero_title'=>'Pizza feita para compartilhar.','hero_highlight'=>'Massa leve, muito sabor.','hero_cta_text'=>'Ver cardápio','nav_featured'=>'Pizzas especiais','nav_catalog'=>'Cardápio','catalog_eyebrow'=>'DIRETO DO FORNO','catalog_title'=>'Pizzas para compartilhar','catalog_search'=>'Buscar pizza, sabor ou bebida','trust_item'=>'Assada na hora','theme_class'=>'tema-pizzaria','color_scheme'=>'light'],
        'pastelaria-brasileira'=>['name'=>'Pastelaria brasileira','business_types'=>['Alimentação'],'primary_color'=>'#E5A900','secondary_color'=>'#17304A','hero_title'=>'Pastel crocante.','hero_highlight'=>'Recheio de verdade.','hero_cta_text'=>'Ver cardápio','nav_featured'=>'Combos','nav_catalog'=>'Sabores','catalog_eyebrow'=>'PASTÉIS E PORÇÕES','catalog_title'=>'Seu pedido, bem recheado','catalog_search'=>'Buscar pastel, porção ou bebida','trust_item'=>'Frito na hora','theme_class'=>'tema-pastelaria','color_scheme'=>'light'],
        'almoco-caseiro'=>['name'=>'Almoço caseiro','business_types'=>['Alimentação'],'primary_color'=>'#C85F3D','secondary_color'=>'#314A3D','hero_title'=>'Comida caseira.','hero_highlight'=>'Pronta para o seu dia.','hero_cta_text'=>'Ver cardápio','nav_featured'=>'Pratos do dia','nav_catalog'=>'Cardápio','catalog_eyebrow'=>'ALMOÇO DO DIA','catalog_title'=>'Feito para a sua rotina','catalog_search'=>'Buscar prato, marmita ou acompanhamento','trust_item'=>'Cozinha feita no dia','theme_class'=>'tema-almoco','color_scheme'=>'light'],
        'moda-editorial'=>['name'=>'Moda editorial','business_types'=>['Moda e Vestuário'],'primary_color'=>'#B46A55','secondary_color'=>'#171311','hero_title'=>'SEU ESTILO.','hero_highlight'=>'SUA IDENTIDADE.','hero_cta_text'=>'Ver coleção','nav_featured'=>'Lançamentos','nav_catalog'=>'Coleção','catalog_eyebrow'=>'COLEÇÃO ATUAL','catalog_title'=>'Escolha seu próximo look','catalog_search'=>'Buscar peça ou coleção','trust_item'=>'Troca facilitada','theme_class'=>'tema-moda','color_scheme'=>'light'],
        'beleza-elegante'=>['name'=>'Beleza elegante','business_types'=>['Beleza'],'primary_color'=>'#C76582','secondary_color'=>'#211519','hero_title'=>'BELEZA QUE INSPIRA.','hero_highlight'=>'CUIDADO QUE TRANSFORMA.','hero_cta_text'=>'Conhecer produtos','nav_featured'=>'Novidades','nav_catalog'=>'Produtos','catalog_eyebrow'=>'CUIDADO E BELEZA','catalog_title'=>'Encontre seus essenciais','catalog_search'=>'Buscar produto de beleza','trust_item'=>'Escolha com confiança','theme_class'=>'tema-beleza','color_scheme'=>'light'],
        'pet-amigavel'=>['name'=>'Pet amigável','business_types'=>['Pet Shop'],'primary_color'=>'#E8782E','secondary_color'=>'#15231E','hero_title'=>'CARINHO PARA QUEM','hero_highlight'=>'FAZ PARTE DA FAMÍLIA.','hero_cta_text'=>'Ver produtos','nav_featured'=>'Destaques','nav_catalog'=>'Pet shop','catalog_eyebrow'=>'PARA O SEU PET','catalog_title'=>'Tudo para o melhor amigo','catalog_search'=>'Buscar para seu pet','trust_item'=>'Cuidado em cada escolha','theme_class'=>'tema-pet','color_scheme'=>'light'],
        'casa-acolhedora'=>['name'=>'Casa acolhedora','business_types'=>['Casa e Decoração'],'primary_color'=>'#B86F4B','secondary_color'=>'#1E1A16','hero_title'=>'SUA CASA.','hero_highlight'=>'DO SEU JEITO.','hero_cta_text'=>'Explorar ambientes','nav_featured'=>'Inspirações','nav_catalog'=>'Catálogo','catalog_eyebrow'=>'CASA E DECORAÇÃO','catalog_title'=>'Detalhes que transformam','catalog_search'=>'Buscar para sua casa','trust_item'=>'Curadoria para seu espaço','theme_class'=>'tema-casa','color_scheme'=>'light'],
        'farmacia-confiavel'=>['name'=>'Farmácia confiável','business_types'=>['Farmácia'],'primary_color'=>'#168A68','secondary_color'=>'#10221D','hero_title'=>'CUIDADO E BEM-ESTAR.','hero_highlight'=>'PERTO DE VOCÊ.','hero_cta_text'=>'Ver produtos','nav_featured'=>'Cuidados','nav_catalog'=>'Produtos','catalog_eyebrow'=>'SAÚDE E BEM-ESTAR','catalog_title'=>'Encontre o que precisa','catalog_search'=>'Buscar produto ou categoria','trust_item'=>'Compra segura','theme_class'=>'tema-farmacia','color_scheme'=>'light'],
        'esportes-energia'=>['name'=>'Esportes energia','business_types'=>['Esportes'],'primary_color'=>'#F05A28','secondary_color'=>'#101314','hero_title'=>'SUPERE SEUS LIMITES.','hero_highlight'=>'COMECE AGORA.','hero_cta_text'=>'Comprar agora','nav_featured'=>'Destaques','nav_catalog'=>'Equipamentos','catalog_eyebrow'=>'PERFORMANCE E MOVIMENTO','catalog_title'=>'Escolha seu próximo desafio','catalog_search'=>'Buscar esporte ou produto','trust_item'=>'Produtos para sua evolução','theme_class'=>'tema-esportes','color_scheme'=>'dark'],
        'informatica-tech'=>['name'=>'Informática tecnológica','business_types'=>['Informática'],'primary_color'=>'#3277F6','secondary_color'=>'#0D1320','hero_title'=>'TECNOLOGIA PARA','hero_highlight'=>'TODOS OS MOMENTOS.','hero_cta_text'=>'Explorar catálogo','nav_featured'=>'Ofertas','nav_catalog'=>'Catálogo','catalog_eyebrow'=>'TECNOLOGIA E ACESSÓRIOS','catalog_title'=>'Encontre a configuração ideal','catalog_search'=>'Buscar produto, marca ou modelo','trust_item'=>'Especificações claras','theme_class'=>'tema-informatica','color_scheme'=>'dark'],
        'comercio-versatil'=>['name'=>'Comércio versátil','business_types'=>['Outro'],'primary_color'=>'#F26B21','secondary_color'=>'#111111','hero_title'=>'TUDO O QUE VOCÊ PRECISA.','hero_highlight'=>'EM UM SÓ LUGAR.','hero_cta_text'=>'Explorar catálogo','nav_featured'=>'Destaques','nav_catalog'=>'Catálogo','catalog_eyebrow'=>'NOSSO CATÁLOGO','catalog_title'=>'Escolha seus produtos','catalog_search'=>'Buscar produto','trust_item'=>'Compra simples','theme_class'=>'tema-comercio','color_scheme'=>'dark'],
    ];
}

function onboarding_food_product(string $preset): ?array
{
    return match ($preset) {
        'pizzaria-italiana' => ['category'=>'Pizzas','name'=>'Pizza Margherita','description'=>'Massa artesanal, molho de tomate, muçarela e manjericão. Exemplo: personalize ingredientes, tamanho e quantidade de fatias antes de publicar.','price'=>3990],
        'alimentacao-artesanal' => ['category'=>'Hambúrgueres','name'=>'Hambúrguer artesanal','description'=>'Pão macio, hambúrguer artesanal, queijo, alface, tomate e molho da casa. Exemplo: revise os ingredientes e informe o peso do hambúrguer antes de publicar.','price'=>2490],
        'pastelaria-brasileira' => ['category'=>'Pastéis','name'=>'Pastel de queijo','description'=>'Massa crocante com recheio de queijo. Exemplo: informe o tamanho, os ingredientes e as opções disponíveis antes de publicar.','price'=>1290],
        'almoco-caseiro' => ['category'=>'Pratos','name'=>'Prato feito','description'=>'Arroz, feijão, proteína e salada. Exemplo: descreva a proteína do dia, os acompanhamentos e o tamanho da porção antes de publicar.','price'=>2290],
        default => null,
    };
}

// Called only inside the new-account transaction, never when switching presets.
function create_onboarding_product(PDO $pdo, int $tenant, string $preset): void
{
    $example=onboarding_food_product($preset);
    if (!$example) return;
    $check=$pdo->prepare('SELECT id FROM products WHERE tenant_id=:tenant LIMIT 1');
    $check->execute(compact('tenant'));
    if ($check->fetchColumn()) return;
    $pdo->prepare('INSERT INTO categories (tenant_id,name,active) VALUES (:tenant,:name,1)')->execute(['tenant'=>$tenant,'name'=>$example['category']]);
    $category=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO products (tenant_id,category_id,name,description,price_cents,active,is_demo) VALUES (:tenant,:category,:name,:description,:price,0,1)')->execute(['tenant'=>$tenant,'category'=>$category,'name'=>$example['name'],'description'=>$example['description'],'price'=>$example['price']]);
}

function storefront_preset_key_for_business_type(string $businessType): string
{
    foreach (storefront_presets() as $key=>$preset) if (in_array($businessType,$preset['business_types'],true)) return $key;
    return 'comercio-versatil';
}

function storefront_preset(string $key, string $businessType='Outro'): array
{
    $presets=storefront_presets();
    $preset=$presets[$key]??null;
    if($preset&&in_array($businessType,$preset['business_types'],true))return $preset;
    return $presets[storefront_preset_key_for_business_type($businessType)];
}

function storefront_presets_for_business_type(string $businessType): array
{
    return array_filter(storefront_presets(),static fn(array $preset): bool=>in_array($businessType,$preset['business_types'],true));
}

function client_ip_hash(): string
{
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $trusted = array_filter(array_map('trim', explode(',', (string)env('TRUSTED_PROXY_IPS', ''))));
    if (in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = array_values(array_filter(array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']))));
        $candidate = end($forwarded);
        if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_IP)) $remote = $candidate;
    }
    return hash_hmac('sha256', $remote, env('APP_KEY', 'development-only'));
}

function rate_limit(string $scope, int $max, int $seconds): bool
{
    $key = hash_hmac('sha256', $scope . '|' . client_ip_hash(), env('APP_KEY', 'development-only'));
    $pdo = db();
    $pdo->prepare('DELETE FROM rate_limits WHERE expires_at < NOW()')->execute();
    $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE rate_key=:key LIMIT 1');
    $stmt->execute(['key'=>$key]);
    $hits = $stmt->fetchColumn();
    if ($hits !== false && (int)$hits >= $max) return false;
    $expires = date('Y-m-d H:i:s', time() + $seconds);
    $stmt = $pdo->prepare('INSERT INTO rate_limits (rate_key,hits,expires_at) VALUES (:key,1,:expires) ON DUPLICATE KEY UPDATE hits=hits+1');
    $stmt->execute(['key'=>$key,'expires'=>$expires]);
    return true;
}

function password_algorithm(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function encrypt_secret(string $plaintext): string
{
    $key=hash('sha256',(string)env('APP_KEY',''),true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plaintext,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new RuntimeException('Não foi possível proteger o segredo.');
    return base64_encode($iv.$tag.$cipher);
}

function decrypt_secret(string $encoded): string
{
    $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Segredo protegido inválido.');$key=hash('sha256',(string)env('APP_KEY',''),true);$value=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($value===false)throw new RuntimeException('Não foi possível ler o segredo.');return $value;
}

function mercadopago_environment(): string
{
    $environment=(string)env('MERCADOPAGO_ENV','sandbox');
    return in_array($environment,['sandbox','production'],true)?$environment:'sandbox';
}

function mercadopago_configured(): bool
{
    return trim((string)env('MERCADOPAGO_ACCESS_TOKEN',''))!=='';
}

function mercadopago_public_key(): ?string
{
    $key=trim((string)env('MERCADOPAGO_PUBLIC_KEY',''));
    return preg_match('/^[A-Za-z0-9_-]{20,200}$/',$key)?$key:null;
}

function mercadopago_plan_id(string $plan): ?string
{
    $key=match($plan){'monthly'=>'MERCADOPAGO_MONTHLY_PLAN_ID','annual'=>'MERCADOPAGO_ANNUAL_PLAN_ID',default=>null};
    if($key===null)return null;$value=trim((string)env($key,''));
    return $value!==''&&strlen($value)<=190?$value:null;
}

function mercadopago_notification_url(): string
{
    $appUrl=rtrim(trim((string)env('APP_URL','')),'/');
    if(!public_https_url($appUrl))throw new RuntimeException('APP_URL inválida para notificações do Mercado Pago.');
    return $appUrl.'/webhooks/mercadopago?source_news=webhooks';
}

final class MercadoPagoRequestException extends RuntimeException
{
    public function __construct(public readonly string $providerCode,int $httpStatus)
    {
        parent::__construct('O Mercado Pago recusou a operação.',$httpStatus);
    }
}

function mercadopago_request(string $method,string $path,?array $payload=null,?string $idempotencyKey=null): array
{
    if(!mercadopago_configured())throw new RuntimeException('Mercado Pago não configurado.');
    $method=strtoupper($method);if(!in_array($method,['GET','POST','PUT'],true)||!preg_match('#^/[a-z0-9_/?=&.%-]+$#i',$path))throw new InvalidArgumentException('Requisição Mercado Pago inválida.');
    $token=trim((string)env('MERCADOPAGO_ACCESS_TOKEN',''));$handle=curl_init('https://api.mercadopago.com'.$path);if($handle===false)throw new RuntimeException('Falha ao iniciar a conexão de pagamento.');
    $headers=['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json'];
    if($idempotencyKey!==null){if(!preg_match('/^[A-Za-z0-9-]{16,64}$/',$idempotencyKey))throw new InvalidArgumentException('Chave de idempotência inválida.');$headers[]='X-Idempotency-Key: '.$idempotencyKey;}
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS];
    if($payload!==null)$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    curl_setopt_array($handle,$options);$body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($body===false||$error!=='')throw new RuntimeException('O provedor de pagamento está indisponível.');
    $decoded=json_decode((string)$body,true);if(!is_array($decoded))throw new RuntimeException('Resposta inválida do provedor de pagamento.');
    if($status<200||$status>=300){$providerCode=(string)($decoded['error']??$decoded['message']??($decoded['cause'][0]['code']??'unknown'));$providerCode=substr((string)preg_replace('/[^A-Za-z0-9._-]/','_', $providerCode),0,80);error_log('[mjdev] mercadopago http_status='.$status.' provider_code='.$providerCode);throw new MercadoPagoRequestException($providerCode,$status);}
    return $decoded;
}

function mercadopago_webhook_signature_valid(string $signature,string $requestId,string $dataId): bool
{
    $secret=trim((string)env('MERCADOPAGO_WEBHOOK_SECRET',''));if($secret===''||$signature===''||$requestId===''||$dataId===''||strlen($dataId)>190||strlen($requestId)>190)return false;
    $parts=[];foreach(explode(',',$signature)as$part){[$key,$value]=array_pad(explode('=',trim($part),2),2,'');if(in_array($key,['ts','v1'],true))$parts[$key]=$value;}
    if(!isset($parts['ts'],$parts['v1'])||!ctype_digit($parts['ts'])||!preg_match('/^[a-f0-9]{64}$/i',$parts['v1']))return false;
    $timestamp=(int)$parts['ts'];if($timestamp>9999999999)$timestamp=(int)floor($timestamp/1000);if(abs(time()-$timestamp)>600)return false;
    $manifest='id:'.$dataId.';request-id:'.$requestId.';ts:'.$parts['ts'].';';
    return hash_equals(strtolower($parts['v1']),hash_hmac('sha256',$manifest,$secret));
}

function mercadopago_checkout_url(string $url): bool
{
    if(strlen($url)>2048||!public_https_url($url))return false;
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    foreach(['mercadopago.com','mercadopago.com.br'] as$allowed){
        if($host===$allowed||str_ends_with($host,'.'.$allowed))return true;
    }
    return false;
}

function raw_query_parameter(string $name): ?string
{
    $query=(string)(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_QUERY)??'');
    foreach(explode('&',$query)as$part){
        [$key,$value]=array_pad(explode('=',$part,2),2,'');
        if(rawurldecode($key)===$name)return rawurldecode($value);
    }
    return null;
}

function mercadopago_datetime(mixed $value): ?string
{
    if(!is_string($value)||$value===''||strlen($value)>80)return null;
    try{return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');}
    catch(Throwable){return null;}
}

function public_https_url(string $url): bool
{
    $parts=parse_url($url);if(!$parts||strtolower($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||(isset($parts['port'])&&(int)$parts['port']!==443))return false;
    $ips=gethostbynamel($parts['host'])?:[];if(!$ips)return false;foreach($ips as$ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;return true;
}

function safe_configured_link(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\r\n\x00]/', $url)) return false;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && !empty($parts['host'])
        && !isset($parts['user'], $parts['pass'])
        && (!isset($parts['port']) || (int)$parts['port'] === 443);
}

function valid_social_url(string $network, string $url): bool
{
    if ($url === '') return true;
    if (!safe_configured_link($url) || str_starts_with($url, '/')) return false;
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $allowed = [
        'instagram' => ['instagram.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
    ];
    foreach ($allowed[$network] ?? [] as $domain) {
        if ($host === $domain || str_ends_with($host, '.'.$domain)) return true;
    }
    return false;
}

function queue_webhooks(int $tenantId,string $event,array $payload): void
{
    $stmt=db()->prepare("INSERT INTO webhook_deliveries (tenant_id,endpoint_id,event_name,payload,next_attempt_at) SELECT :tenant,id,:event,:payload,NOW() FROM webhook_endpoints WHERE tenant_id=:tenant_filter AND active=1 AND (event_name=:event_filter OR event_name='*')");$stmt->execute(['tenant'=>$tenantId,'event'=>$event,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'tenant_filter'=>$tenantId,'event_filter'=>$event]);
}

function money_to_cents(string $value): ?int
{
    $value = trim($value);
    if ($value === '' || !preg_match('/^\d{1,9}(?:[\.,]\d{1,2})?$/', $value)) return null;
    $normalized = str_replace(',', '.', $value);
    [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
    $fraction = str_pad($fraction, 2, '0');
    $cents = ((int)$whole * 100) + (int)substr($fraction, 0, 2);
    return $cents <= 2147483647 ? $cents : null;
}

function replace_two_factor_recovery_codes(PDO $pdo, int $tenantId, int $userId): array
{
    $codes=[];$pdo->prepare('DELETE FROM user_two_factor_recovery_codes WHERE tenant_id=:tenant AND user_id=:user')->execute(['tenant'=>$tenantId,'user'=>$userId]);
    $insert=$pdo->prepare('INSERT INTO user_two_factor_recovery_codes (tenant_id,user_id,code_hash) VALUES (:tenant,:user,:hash)');
    for($index=0;$index<8;$index++){
        $raw=strtoupper(bin2hex(random_bytes(4)));$code='MJ-'.substr($raw,0,4).'-'.substr($raw,4,4);
        $insert->execute(['tenant'=>$tenantId,'user'=>$userId,'hash'=>hash_hmac('sha256',$code,(string)env('APP_KEY',''))]);$codes[]=$code;
    }
    return $codes;
}

function audit(?int $tenantId, ?int $userId, string $action, string $entity, ?int $entityId = null, array $meta = []): void
{
    $stmt = db()->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity,entity_id,ip_hash,metadata,created_at) VALUES (:tenant,:user,:action,:entity,:entity_id,:ip,:metadata,NOW())');
    $stmt->execute(['tenant'=>$tenantId,'user'=>$userId,'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'ip'=>client_ip_hash(),'metadata'=>json_encode($meta, JSON_UNESCAPED_UNICODE)]);
}

function unique_slug(string $name): string
{
    $base = slugify($name); $slug = $base; $i = 2;
    $reserved = ['admin','api','assets','avaliar','brand','cadastro','entrar','esqueci-senha','health','loja','mesa','pedido','platform','privacidade','redefinir-senha','sair','sw-js','termos','verificar-acesso','webhooks'];
    $stmt = db()->prepare('SELECT 1 FROM tenants WHERE slug=:slug');
    while (true) { $stmt->execute(['slug'=>$slug]); if (!in_array($slug,$reserved,true)&&!$stmt->fetchColumn()) return $slug; $slug = $base . '-' . $i++; }
}

function valid_document(string $value): bool
{
    $digits = preg_replace('/\D/', '', $value) ?? '';
    if (strlen($digits) === 11) {
        if (preg_match('/^(\d)\1{10}$/', $digits)) return false;
        for ($t=9; $t<11; $t++) { $sum=0; for ($i=0; $i<$t; $i++) $sum += (int)$digits[$i] * (($t+1)-$i); $d=(10*$sum)%11; if ($d===10) $d=0; if ((int)$digits[$t] !== $d) return false; }
        return true;
    }
    if (strlen($digits) === 14) {
        if (preg_match('/^(\d)\1{13}$/', $digits)) return false;
        $weights=[[5,4,3,2,9,8,7,6,5,4,3,2],[6,5,4,3,2,9,8,7,6,5,4,3,2]];
        foreach ($weights as $index=>$w) { $sum=0; foreach ($w as $i=>$weight) $sum += (int)$digits[$i]*$weight; $d=$sum%11<2?0:11-($sum%11); if ((int)$digits[12+$index]!==$d) return false; }
        return true;
    }
    return false;
}

function request_json(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) json_response(['error'=>'JSON inválido.'], 400);
    return $data;
}

function store_image_upload(array $file, int $tenantId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > (int)env('UPLOAD_MAX_BYTES','5242880')) throw new RuntimeException('Imagem inválida ou acima do limite.');
    $tmp=(string)($file['tmp_name']??'');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])||@getimagesize($tmp)===false)throw new RuntimeException('Conteúdo ou formato de imagem inválido.');
    $dir=ROOT_PATH.'/storage/uploads/'.$tenantId;if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('Falha ao preparar armazenamento.');
    $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Falha ao salvar imagem.');
    return $tenantId.'/'.$name;
}

function remove_stored_image(?string $relativePath, int $tenantId): void
{
    if ($relativePath === null || !preg_match('#^' . preg_quote((string)$tenantId, '#') . '/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) return;
    $file = ROOT_PATH . '/storage/uploads/' . $relativePath;
    if (is_file($file)) @unlink($file);
}

function store_pwa_icon_file(int $tenantId, ?string $relativePath, int $size, string $background): string
{
    if (!in_array($size, [192, 512], true)) throw new InvalidArgumentException('Tamanho de ícone inválido.');
    $fallback = ROOT_PATH . '/public/assets/app-icon-' . $size . '.png';
    if (!extension_loaded('gd') || $relativePath === null || !preg_match('#^' . preg_quote((string)$tenantId, '#') . '/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) return $fallback;
    $sourceFile = ROOT_PATH . '/storage/uploads/' . $relativePath;
    if (!is_file($sourceFile)) return $fallback;
    $cacheKey = hash('sha256', $relativePath . '|' . filemtime($sourceFile) . '|' . filesize($sourceFile) . '|' . $size . '|' . strtoupper($background));
    $cacheFile = ROOT_PATH . '/storage/cache/pwa-icon-' . $tenantId . '-' . $size . '-' . $cacheKey . '.png';
    if (is_file($cacheFile)) return $cacheFile;
    $contents = file_get_contents($sourceFile);
    $source = $contents === false ? false : @imagecreatefromstring($contents);
    if ($source === false) return $fallback;
    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) { imagedestroy($source); return $fallback; }
    $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $background) ? substr($background, 1) : '111111';
    $fill = imagecolorallocate($canvas, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    imagefill($canvas, 0, 0, $fill);
    $sourceWidth = imagesx($source);$sourceHeight = imagesy($source);$available = (int)round($size * .76);
    $scale = min($available / max(1, $sourceWidth), $available / max(1, $sourceHeight));
    $targetWidth = max(1, (int)round($sourceWidth * $scale));$targetHeight = max(1, (int)round($sourceHeight * $scale));
    $targetX = (int)(($size - $targetWidth) / 2);$targetY = (int)(($size - $targetHeight) / 2);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    $temporary = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $saved = imagepng($canvas, $temporary, 9);
    imagedestroy($source);imagedestroy($canvas);
    if (!$saved) { @unlink($temporary); return $fallback; }
    if (!@rename($temporary, $cacheFile)) { @unlink($temporary); return $fallback; }
    @chmod($cacheFile, 0640);
    return $cacheFile;
}

