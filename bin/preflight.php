<?php
declare(strict_types=1);

const PREFLIGHT_ROOT = __DIR__ . '/..';

function preflightLoadEnv(string $file): void
{
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) putenv($key . '=' . trim($value, "\"'"));
    }
}

function preflightEnv(string $key): string
{
    $value = getenv($key);
    return $value === false ? '' : trim($value);
}

preflightLoadEnv(PREFLIGHT_ROOT . '/.env');

if (preflightEnv('APP_ENV') !== 'production') {
    fwrite(STDOUT, "Preflight de produção ignorado: APP_ENV não é production.\n");
    exit(0);
}

$errors = [];
$require = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) $errors[] = $message;
};

$appKey = preflightEnv('APP_KEY');
$appUrl = preflightEnv('APP_URL');
$privacyEmail = preflightEnv('PRIVACY_CONTACT_EMAIL');
$dbPassword = preflightEnv('DB_PASSWORD');

$require(strlen($appKey) >= 32 && !str_contains($appKey, 'generate-'), 'APP_KEY deve ser um segredo aleatório com pelo menos 32 caracteres.');
$require(filter_var($appUrl, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($appUrl), 'https://'), 'APP_URL deve ser uma URL HTTPS válida.');
$require(preflightEnv('APP_DEBUG') === 'false', 'APP_DEBUG deve ser false.');
$require(preflightEnv('SESSION_SECURE_COOKIE') === 'true', 'SESSION_SECURE_COOKIE deve ser true.');
$require(strlen(preflightEnv('LEGAL_OPERATOR_NAME')) >= 3, 'LEGAL_OPERATOR_NAME deve identificar o operador legal.');
$require(filter_var($privacyEmail, FILTER_VALIDATE_EMAIL) !== false && !str_ends_with(strtolower($privacyEmail), '@example.invalid'), 'PRIVACY_CONTACT_EMAIL deve ser um endereço real.');
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
    $require(preflightEnv($key) !== '', $key . ' é obrigatório.');
}
$require(strlen($dbPassword) >= 16 && !str_contains($dbPassword, 'replace-with'), 'DB_PASSWORD deve ser um segredo forte e não pode usar o valor de exemplo.');
$require(extension_loaded('pdo_mysql'), 'A extensão pdo_mysql não está carregada.');
$require(extension_loaded('gd'), 'A extensão GD é obrigatória para gerar os ícones PWA das lojas.');
$mailEnabled=preflightEnv('MAIL_ENABLED')==='true';
$requirePrivilegedTwoFactor=preflightEnv('REQUIRE_PRIVILEGED_2FA')==='true';
$require(in_array(preflightEnv('REQUIRE_PRIVILEGED_2FA'),['true','false'],true),'REQUIRE_PRIVILEGED_2FA deve ser true ou false.');
$require(!$requirePrivilegedTwoFactor||$mailEnabled,'REQUIRE_PRIVILEGED_2FA exige MAIL_ENABLED=true para permitir a ativação e recuperação do acesso.');
if($mailEnabled){
    $require(preflightEnv('MAIL_HOST')!==''&&!str_ends_with(strtolower(preflightEnv('MAIL_HOST')),'.invalid'),'MAIL_HOST deve identificar o servidor SMTP real.');
    $require((int)preflightEnv('MAIL_PORT')>0&&(int)preflightEnv('MAIL_PORT')<=65535,'MAIL_PORT deve ser válida.');
    $require(in_array(preflightEnv('MAIL_ENCRYPTION'),['tls','ssl','none'],true),'MAIL_ENCRYPTION deve ser tls, ssl ou none.');
    $require(filter_var(preflightEnv('MAIL_FROM_ADDRESS'),FILTER_VALIDATE_EMAIL)!==false&&!str_ends_with(strtolower(preflightEnv('MAIL_FROM_ADDRESS')),'@example.invalid'),'MAIL_FROM_ADDRESS deve ser um endereço real.');
    $require((preflightEnv('MAIL_USERNAME')===''&&preflightEnv('MAIL_PASSWORD')==='')||(preflightEnv('MAIL_USERNAME')!==''&&preflightEnv('MAIL_PASSWORD')!==''),'MAIL_USERNAME e MAIL_PASSWORD devem ser configurados juntos.');
}

$turnstileSetting=preflightEnv('TURNSTILE_ENABLED');
$turnstileEnabled=$turnstileSetting==='true';
$require($turnstileSetting===''||in_array($turnstileSetting,['true','false'],true),'TURNSTILE_ENABLED deve ser true ou false.');
if($turnstileEnabled){
    $require(extension_loaded('curl'),'A extensão curl do PHP é obrigatória para o Cloudflare Turnstile.');
    $require(strlen(preflightEnv('TURNSTILE_SITE_KEY'))>=10,'TURNSTILE_SITE_KEY parece inválida.');
    $require(strlen(preflightEnv('TURNSTILE_SECRET_KEY'))>=10,'TURNSTILE_SECRET_KEY parece inválida.');
}

$mercadoPagoEnvironment=preflightEnv('MERCADOPAGO_ENV');
$require(in_array($mercadoPagoEnvironment,['sandbox','production'],true),'MERCADOPAGO_ENV deve ser sandbox ou production.');
$mercadoPagoToken=preflightEnv('MERCADOPAGO_ACCESS_TOKEN');
$mercadoPagoConfigured=$mercadoPagoToken!=='';
if($mercadoPagoConfigured){
    $require(extension_loaded('curl'),'A extensão curl do PHP é obrigatória para o Mercado Pago.');
    $require(strlen($mercadoPagoToken)>=32,'MERCADOPAGO_ACCESS_TOKEN parece inválido.');
    $require((bool)preg_match('/^[A-Za-z0-9_-]{20,200}$/',preflightEnv('MERCADOPAGO_PUBLIC_KEY')),'MERCADOPAGO_PUBLIC_KEY parece inválida.');
    $require($mercadoPagoEnvironment!=='production'||preflightEnv('MERCADOPAGO_WEBHOOK_SECRET')!=='','MERCADOPAGO_WEBHOOK_SECRET é obrigatório em produção.');
    $require($mercadoPagoEnvironment!=='production'||(preflightEnv('MERCADOPAGO_MONTHLY_PLAN_ID')!==''&&preflightEnv('MERCADOPAGO_ANNUAL_PLAN_ID')!==''),'Os IDs dos planos do Mercado Pago são obrigatórios em produção.');
}

foreach (['logs', 'uploads', 'cache'] as $directory) {
    $path = PREFLIGHT_ROOT . '/storage/' . $directory;
    $require(is_dir($path) && is_writable($path), 'O diretório storage/' . $directory . ' deve existir e ser gravável.');
}

$migrations = glob(PREFLIGHT_ROOT . '/migrations/*.sql') ?: [];
$require($migrations !== [], 'Nenhuma migration foi encontrada.');
$versions = array_map('basename', $migrations);
$sorted = $versions;
sort($sorted, SORT_NATURAL);
$require($versions === $sorted, 'As migrations devem estar em ordem numérica.');

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}\n");
    fwrite(STDERR, 'Preflight reprovado com ' . count($errors) . " problema(s).\n");
    exit(1);
}

fwrite(STDOUT, "OK - preflight de produção aprovado.\n");

