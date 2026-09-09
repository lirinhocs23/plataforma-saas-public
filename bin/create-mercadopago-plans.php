<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");
if (mercadopago_environment() !== 'sandbox') exit("Este comando cria somente planos de homologação em sandbox.\n");
if (!mercadopago_configured()) exit("Configure MERCADOPAGO_ACCESS_TOKEN no EasyPanel antes de continuar.\n");
if (strlen((string)env('APP_KEY', '')) < 32) exit("APP_KEY deve possuir pelo menos 32 caracteres.\n");

$appUrl = rtrim(trim((string)env('APP_URL', '')), '/');
if (!public_https_url($appUrl)) exit("APP_URL deve ser uma URL HTTPS pública válida.\n");

$plans = [
    'monthly' => [
        'environment_key' => 'MERCADOPAGO_MONTHLY_PLAN_ID',
        'reason' => 'MJDev Digital - Plano Mensal Fundadores',
        'amount_cents' => 5990,
        'frequency' => 1,
    ],
    'annual' => [
        'environment_key' => 'MERCADOPAGO_ANNUAL_PLAN_ID',
        'reason' => 'MJDev Digital - Plano Anual',
        'amount_cents' => 59900,
        'frequency' => 12,
    ],
];

$result = [];
try {
    foreach ($plans as $code => $plan) {
        $existing = mercadopago_plan_id($code);
        if ($existing !== null) {
            $result[$plan['environment_key']] = $existing;
            continue;
        }

        $idempotencyKey = 'mjdev-plan-' . substr(hash_hmac('sha256', $code . '-sandbox-v1', (string)env('APP_KEY', '')), 0, 40);
        $response = mercadopago_request('POST', '/preapproval_plan', [
            'reason' => $plan['reason'],
            'auto_recurring' => [
                'frequency' => $plan['frequency'],
                'frequency_type' => 'months',
                'transaction_amount' => $plan['amount_cents'] / 100,
                'currency_id' => 'BRL',
            ],
            'back_url' => $appUrl . '/admin?section=billing&provider=mercadopago',
        ], $idempotencyKey);

        $planId = trim((string)($response['id'] ?? ''));
        if ($planId === '' || strlen($planId) > 190) throw new RuntimeException('O provedor não retornou um identificador de plano válido.');
        $result[$plan['environment_key']] = $planId;
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Não foi possível criar os planos sandbox. Consulte os logs seguros da aplicação.\n");
    exit(1);
}

echo "Planos sandbox preparados. Copie somente estes IDs para as variáveis do EasyPanel:\n";
foreach ($result as $key => $value) echo $key . '=' . $value . "\n";
echo "Nenhum período grátis foi criado no gateway; os 14 dias continuam controlados pela MJDev após a publicação da vitrine.\n";
