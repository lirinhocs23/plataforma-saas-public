<?php
$money=fn(int $value): string=>'R$ '.number_format($value/100,2,',','.');
$statusLabels=['received'=>'Recebido','confirmed'=>'Confirmado','preparing'=>'Em preparo','ready'=>'Pronto','out_for_delivery'=>'Saiu para entrega','delivered'=>'Entregue','cancelled'=>'Cancelado','rejected'=>'Recusado'];
$channelLabels=['delivery'=>'Vitrine digital','pickup'=>'Retirada / PDV','dine_in'=>'Salão'];
$chart=function(array $data,bool $moneyValues=false) use ($money): string {
    $max=max(1,...array_map(fn($row)=>(int)$row['value'],$data));
    ob_start(); ?>
    <div class="grafico grafico-real" role="img" aria-label="Gráfico com <?= count($data) ?> períodos">
        <?php foreach($data as $row):$value=(int)$row['value'];$label=(string)$row['label']; ?>
            <div class="barra" style="height:<?= max(4,(int)round($value/$max*100)) ?>%" title="<?= e($label) ?>: <?= $moneyValues?e($money($value)):$value ?>"><span><?= e(strlen($label)>5?substr($label,5):$label) ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php return (string)ob_get_clean();
};
$steps=[
    (bool)$store['description'],
    trim((string)($store['whatsapp']??''))!=='',
    !empty($categories),
    !empty($products),
    !empty($hours),
    !empty($zones),
];
$completed=count(array_filter($steps));
$percent=(int)round($completed/6*100);
$catalogUrl=rtrim(env('APP_URL','http://localhost'),'/').'/'.$store['slug'];
$activeOrders=array_values(array_slice(array_filter($orders,fn($order)=>!in_array($order['status'],['delivered','cancelled','rejected'],true)),0,7));
$weekdays=['Sunday'=>'DOMINGO','Monday'=>'SEGUNDA-FEIRA','Tuesday'=>'TERÇA-FEIRA','Wednesday'=>'QUARTA-FEIRA','Thursday'=>'QUINTA-FEIRA','Friday'=>'SEXTA-FEIRA','Saturday'=>'SÁBADO'];
$months=['January'=>'JANEIRO','February'=>'FEVEREIRO','March'=>'MARÇO','April'=>'ABRIL','May'=>'MAIO','June'=>'JUNHO','July'=>'JULHO','August'=>'AGOSTO','September'=>'SETEMBRO','October'=>'OUTUBRO','November'=>'NOVEMBRO','December'=>'DEZEMBRO'];
$dashboardDate=($weekdays[date('l')]??strtoupper(date('l'))).', '.date('d').' DE '.($months[date('F')]??strtoupper(date('F')));
$onboardingSteps=[
    ['Sua identidade visual','Adicione nome, descrição, logo e cores da sua marca.','settings'],
    ['Conectar o WhatsApp','Configure o número que receberá novos pedidos.','settings'],
    ['Criar categorias','Organize o catálogo para facilitar a compra.','categories'],
    ['Cadastrar produtos','Adicione fotos, descrições, preços e estoque.','products'],
    ['Definir funcionamento','Informe dias, horários e pedidos agendados.','settings'],
    ['Configurar entregas','Cadastre bairros, taxas, prazos e pedido mínimo.','zones'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Dashboard de gestão da <?= e($store['name']) ?>">
    <title>Dashboard · MJDev Digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="/assets/admin-theme.js?v=20260826-tema1"></script><link rel="stylesheet" href="/assets/admin.css?v=20260903-review1">
</head>
<body>
<div class="aplicativo">
    <aside class="barra-lateral" id="sidebar" aria-label="Navegação principal">
        <div class="marca"><span class="simbolo-marca">MJ</span><div><strong>MJDev Digital</strong><small>PAINEL DE GESTÃO</small></div><button class="icone movel" id="closeMenu" type="button" aria-label="Fechar menu">×</button></div>
        <nav><?= admin_navigation($user['role'],'dashboard') ?></nav>
        <?= admin_store_card($store) ?>
        <form class="sair-painel" method="post" action="/sair"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="botao perigo" type="submit">Sair do painel</button></form>
    </aside>
    <div class="fundo-sobreposto fundo-menu" id="menuBackdrop"></div>
    <main class="estrutura">
        <header class="barra-superior">
            <button class="icone movel" id="openMenu" type="button" aria-label="Abrir menu">☰</button>
            <div class="cabecalho-pagina"><span><?= e($dashboardDate) ?></span><h1>Dashboard</h1></div>
            <?= admin_header_actions($orders,$user) ?>
        </header>
        <section class="conteudo">
            <?php require __DIR__.'/onboarding-product-notice.php'; ?>
            <?php if($flash):?><div class="aviso <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif;?>
            <article class="cartao cartao-controle">
                <div class="cabecalho-cartao"><div><span class="sobretitulo">CONTROLE DA LOJA</span><h3>Status operacional</h3></div><form method="post" action="/admin/store/status"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="botao-status-loja <?= $store['store_open']?'aberto':'fechado' ?>" type="submit" aria-label="<?= $store['store_open']?'Pausar novos pedidos':'Liberar novos pedidos' ?>" title="<?= $store['store_open']?'Clique para pausar novos pedidos':'Clique para abrir a loja' ?>">● <?= $store['store_open']?'ABERTO':'FECHADO' ?></button></form></div>
                <div class="linha-link-loja"><label class="busca"><input value="<?= e($catalogUrl) ?>" readonly aria-label="Link da loja"></label><button class="botao copiar-link" type="button" data-copy-value="<?= e($catalogUrl) ?>">Copiar link</button></div>
                <div class="acoes-rapidas"><a class="botao pequeno" href="/admin?section=categories">Cardápio</a><a class="botao pequeno" href="/admin?section=products">Produtos</a><a class="botao pequeno" href="/admin?section=orders">Pedidos</a><a class="botao pequeno" href="/admin?section=settings">Assistência</a><?php if($store['published_at']):?><a class="botao pequeno" href="/loja/<?= e($store['slug']) ?>?editar=1" target="_blank" rel="noopener">Editar loja</a><?php endif;?></div>
            </article>
            <?php $activeQuickLinks=array_values(array_filter($quickLinks??[],fn($link)=>(bool)$link['active']));if($activeQuickLinks):?><nav class="links-rapidos-personalizados" aria-label="Links rápidos personalizados"><?php foreach($activeQuickLinks as$link):$external=!str_starts_with((string)$link['url'],'/');?><a href="<?= e($link['url']) ?>" style="--cor-link:<?= e($link['color']) ?>" <?= $external?'target="_blank" rel="noopener noreferrer"':'' ?>><span><?= e($link['emoji']) ?></span><b><?= e($link['label']) ?></b></a><?php endforeach;?></nav><?php endif;?>
            <article class="cartao primeiros-passos-painel">
                <div class="cabecalho-primeiros-passos"><div><span class="sobretitulo">CONFIGURE SUA LOJA</span><h2>Primeiros passos</h2><p>Complete estas etapas para publicar sua vitrine e começar a receber pedidos.</p></div><div class="percentual-configuracao"><strong><?= $percent ?>%</strong><small><?= $completed ?> de 6 concluídas</small></div></div>
                <div class="progresso"><i style="width:<?= $percent ?>%"></i></div>
                <div class="grade-passos"><?php foreach($onboardingSteps as $index=>$step):?><a class="passo-configuracao <?= $steps[$index]?'concluido':'' ?>" href="/admin?section=<?= e($step[2]) ?>"><i><?= $steps[$index]?'✓':$index+1 ?></i><span><b><?= e($step[0]) ?></b><small><?= e($step[1]) ?></small><em><?= $steps[$index]?'Revisar':'Configurar' ?></em></span></a><?php endforeach;?></div>
            </article>
            <div class="grade metricas metricas-dashboard">
                <article class="metrica"><small>Pedidos hoje</small><strong><?= (int)($dashboardStats['orders_today']??0) ?></strong><span class="variacao">Dados em tempo real</span></article>
                <article class="metrica"><small>Faturamento hoje</small><strong><?= $money((int)($dashboardStats['revenue_today']??0)) ?></strong><span class="variacao">Pedidos válidos</span></article>
                <article class="metrica"><small>Ticket médio</small><strong><?= $money((int)($dashboardStats['average_ticket']??0)) ?></strong><span class="variacao">Média de hoje</span></article>
                <article class="metrica"><small>Em aberto agora</small><strong><?= (int)($dashboardStats['active_orders']??0) ?></strong><span class="variacao">Operação atual</span></article>
                <article class="metrica"><small>Faturamento do mês</small><strong><?= $money((int)($dashboardStats['month_revenue']??0)) ?></strong><span class="variacao">Acumulado mensal</span></article>
                <article class="metrica"><small>Cancelamentos hoje</small><strong><?= (int)($dashboardStats['cancelled_today']??0) ?></strong><span class="variacao <?= (int)($dashboardStats['cancelled_today']??0)>0?'ruim':'' ?>">Cancelados e recusados</span></article>
            </div>
            <div class="grade painel-operacional">
                <article class="cartao pedidos-andamento"><div class="cabecalho-cartao"><h3>Pedidos em andamento</h3><span class="etiqueta amarelo"><?= count($activeOrders) ?> ativos</span></div><div class="lista-pedidos"><?php if(!$activeOrders):?><div class="estado-vazio compacto"><b>Nenhum pedido em andamento</b><p>Os novos pedidos aparecerão aqui em tempo real.</p></div><?php else:foreach($activeOrders as $order):?><a class="pedido" href="/admin?section=orders"><span><b><?= e($order['public_code']) ?> · <?= e($order['customer_name']) ?></b><small><?= e($channelLabels[$order['order_type']]??$order['order_type']) ?> · <?= $money((int)$order['total_cents']) ?></small></span><span class="etiqueta"><?= e($statusLabels[$order['status']]??$order['status']) ?></span></a><?php endforeach;endif;?></div></article>
                <article class="cartao"><div class="cabecalho-cartao"><h3>Pedidos por hora · hoje</h3><span class="etiqueta">Tempo real</span></div><?= $hourlyOrders?$chart($hourlyOrders):'<div class="estado-vazio compacto"><b>Aguardando o primeiro pedido</b><p>O gráfico será montado com dados reais.</p></div>' ?></article>
            </div>
            <div class="grade tres-colunas paineis-analiticos">
                <article class="cartao"><h3>Funil do pedido · hoje</h3><?php if(!$statusCounts):?><div class="estado-vazio compacto"><b>Sem movimentação hoje</b><p>Os estágios aparecerão conforme os pedidos avançarem.</p></div><?php else:$statusTotal=max(1,array_sum(array_column($statusCounts,'value')));foreach($statusCounts as $row):?><p class="linha-funil"><span><?= e($statusLabels[$row['label']]??$row['label']) ?></span><b><?= (int)$row['value'] ?></b><span class="progresso"><i style="width:<?= (int)round((int)$row['value']/$statusTotal*100) ?>%"></i></span></p><?php endforeach;endif;?></article>
                <article class="cartao"><h3>Top produtos · 30 dias</h3><?php if(!$topProducts):?><div class="estado-vazio compacto"><b>Ainda sem ranking</b><p>Produtos vendidos formarão esta lista.</p></div><?php else:?><ol class="lista-ranking"><?php foreach($topProducts as $product):?><li><span><b><?= e($product['label']) ?></b><small><?= $money((int)$product['revenue_cents']) ?></small></span><strong><?= (int)$product['value'] ?></strong></li><?php endforeach;?></ol><?php endif;?></article>
                <article class="cartao"><h3>Canais de venda · 30 dias</h3><?php if(!$channelCounts):?><div class="estado-vazio compacto"><b>Ainda sem canais medidos</b><p>A divisão usará somente pedidos reais.</p></div><?php else:$channelTotal=max(1,array_sum(array_column($channelCounts,'value')));foreach($channelCounts as $row):?><p class="linha-canal"><span><?= e($channelLabels[$row['label']]??$row['label']) ?></span><b><?= (int)round((int)$row['value']/$channelTotal*100) ?>%</b></p><?php endforeach;endif;?></article>
            </div>
            <article class="cartao painel-receita"><div class="cabecalho-cartao"><div><span class="sobretitulo">ANALYTICS DE FATURAMENTO</span><h3>Receita · últimos 14 dias</h3></div><span class="etiqueta">14d</span></div><?= $dailyRevenue?$chart($dailyRevenue,true):'<div class="estado-vazio compacto"><b>Sem vendas no período</b><p>Este painel não usa dados demonstrativos.</p></div>' ?></article>
            <div class="grade metricas metricas-avaliacoes"><article class="metrica"><small>Avaliações publicadas</small><strong><?= (int)($dashboardStats['review_count']??0) ?></strong></article><article class="metrica"><small>Média geral</small><strong><?= number_format((float)($dashboardStats['average_rating']??0),1,',','.') ?></strong></article><article class="metrica"><small>Avaliações positivas</small><strong><?= (int)($dashboardStats['positive_reviews']??0) ?></strong></article><article class="metrica"><small>Avaliações negativas</small><strong><?= (int)($dashboardStats['negative_reviews']??0) ?></strong></article></div>
        </section>
    </main>
</div>
<script src="/assets/admin-ui.js?v=20260903-review1" defer></script>
</body>
</html>

