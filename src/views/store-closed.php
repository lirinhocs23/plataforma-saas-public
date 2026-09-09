<?php
$preset=storefront_preset((string)($preferences['storefront_preset']??''),(string)$store['business_type']);
$primary=(string)($preferences['primary_color']??$preset['primary_color']);
$weekdays=['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
?>
<!doctype html>
<html lang="pt-BR" data-theme="<?= e($preset['color_scheme']) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noarchive"><meta name="theme-color" content="<?= e($primary) ?>">
    <title><?= e($store['name']) ?> · Fechado no momento</title>
    <link rel="stylesheet" href="/assets/store-closed.css?v=20260904-closed1">
    <style>:root{--brand:<?= e($primary) ?>}</style>
</head>
<body data-store="<?= e($store['slug']) ?>" data-store-open="0">
<main class="closed-store">
    <?php if(!empty($preferences['banner_path'])):?><img class="store-banner" src="/brand/<?= e($preferences['banner_path']) ?>" alt=""><?php endif;?>
    <section class="closed-content">
        <?php if(!empty($preferences['logo_path'])):?><img class="store-logo" src="/brand/<?= e($preferences['logo_path']) ?>" alt="Logo de <?= e($store['name']) ?>"><?php endif;?>
        <p class="store-name"><?= e($store['name']) ?></p>
        <span class="closed-label">FECHADO NO MOMENTO</span>
        <h1>Estamos fechados no momento</h1>
        <p>Nosso catálogo estará disponível quando abrirmos novamente.</p>
        <?php if($hours):?><section aria-labelledby="hours-title"><h2 id="hours-title">Horário de atendimento</h2><dl>
            <?php foreach($hours as$day):?><div><dt><?= e($weekdays[(int)$day['weekday']]) ?></dt><dd><?= $day['closed']?'Fechado':e(substr((string)$day['opens_at'],0,5).' – '.substr((string)$day['closes_at'],0,5)) ?></dd></div><?php endforeach;?>
        </dl></section><?php endif;?>
        <a class="refresh" href="/<?= e($store['slug']) ?>">Verificar abertura</a>
        <p class="tracking-note">Já fez um pedido? Seu acompanhamento continua disponível pelo link seguro recebido na confirmação.</p>
    </section>
</main>
<script src="/assets/store-status.js?v=20260904-closed1" defer></script>
</body></html>

