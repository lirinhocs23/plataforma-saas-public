<?php
$operatorName = (string)env('LEGAL_OPERATOR_NAME', 'Não configurado');
$privacyEmail = (string)env('PRIVACY_CONTACT_EMAIL', '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $type === 'terms' ? 'Termos de Uso' : 'Política de Privacidade' ?> · MJDev Digital</title>
  <link rel="stylesheet" href="/assets/app.css?v=20260826-responsivo1">
</head>
<body><main class="pagina-legal"><article class="cartao-legal"><a href="/">← Início</a>
<?php if ($type === 'terms'): ?>
<h1>Termos de Uso</h1><p>Última atualização: 18 de agosto de 2026.</p>
<h2>1. Serviço</h2><p>A MJDev Digital fornece ferramentas para criação de catálogos e gestão de pedidos. Cada estabelecimento é responsável pela legalidade de seus produtos, informações, preços e atendimento.</p>
<h2>2. Conta</h2><p>O responsável deve fornecer dados verdadeiros, proteger suas credenciais e administrar as permissões da equipe. Atividades realizadas por usuários autorizados são atribuídas ao estabelecimento.</p>
<h2>3. Período gratuito e contratação</h2><p>O período gratuito de 14 dias começa quando o catálogo é publicado. Condições comerciais definitivas devem ser apresentadas antes de qualquer cobrança.</p>
<h2>4. Uso aceitável</h2><p>É proibido usar o serviço para fraude, conteúdo ilícito, invasão, spam ou violação de direitos. Contas podem ser suspensas para proteger usuários e a plataforma.</p>
<h2>5. Disponibilidade</h2><p>Manutenções e falhas externas podem afetar o serviço. O estabelecimento deve manter procedimentos operacionais alternativos.</p>
<h2>6. Responsabilidade</h2><p>Pagamentos, entregas, tributos, produtos e relacionamento com consumidores permanecem sob responsabilidade do estabelecimento, salvo obrigação legal da plataforma.</p>
<h2>7. Contato</h2><p>Operador: <?= e($operatorName) ?>. Contato jurídico e de privacidade: <a href="mailto:<?= e($privacyEmail) ?>"><?= e($privacyEmail) ?></a>.</p>
<?php else: ?>
<h1>Política de Privacidade</h1><p>Última atualização: 18 de agosto de 2026.</p>
<h2>1. Dados tratados</h2><p>Podemos tratar identificação, contato, empresa, credenciais protegidas, pedidos, endereços, registros de acesso e dados necessários à segurança e operação.</p>
<h2>2. Finalidades</h2><p>Os dados são usados para criar contas, prestar o serviço, processar pedidos, prevenir fraude, cumprir obrigações legais e atender solicitações dos titulares.</p>
<h2>3. Papéis</h2><p>Para dados dos consumidores de cada loja, o estabelecimento geralmente atua como controlador e a MJDev Digital como operadora, conforme contrato e legislação aplicável.</p>
<h2>4. Compartilhamento</h2><p>Dados são compartilhados somente com fornecedores necessários, autoridades quando exigido e integrações autorizadas pelo estabelecimento.</p>
<h2>5. Segurança e retenção</h2><p>Aplicamos controles técnicos e administrativos. Os dados são mantidos pelo período necessário às finalidades, contratos e obrigações legais.</p>
<h2>6. Direitos</h2><p>O titular pode solicitar confirmação, acesso, correção, eliminação quando aplicável, informação sobre compartilhamento e revisão de consentimento.</p>
<h2>7. Contato</h2><p>Operador: <?= e($operatorName) ?>. Solicitações de titulares e assuntos de privacidade: <a href="mailto:<?= e($privacyEmail) ?>"><?= e($privacyEmail) ?></a>.</p>
<?php endif; ?>
</article></main></body></html>
