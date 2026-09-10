# ⚠️ PROJETO NÃO FINALIZADO (TRABALHO EM PROGRESSO)

> **Atenção:** Este repositório contém a versão em desenvolvimento de uma Plataforma SaaS de Cardápio Digital. O código atual **não está finalizado**, encontra-se em fase beta/testes e **pode conter bugs, instabilidades ou funcionalidades incompletas**.

---

# MJDev Digital — Plataforma de Cardápio Digital


Aplicação PHP 8.3 + MySQL 8 para criação de catálogos digitais e recebimento de pedidos. O frontend preserva a identidade visual do protótipo original, enquanto autenticação, preços, pedidos e isolamento entre estabelecimentos são processados no servidor.

## Recursos implementados

- cadastro público de estabelecimento e proprietário;
- login, logout, sessões seguras e limitação de tentativas;
- RBAC para proprietário, gerente, atendente, garçom, cozinha, PDV e entregador;
- isolamento das consultas por `tenant_id`;
- categorias, produtos, áreas de entrega, equipe e configurações;
- publicação controlada do catálogo e início do período gratuito de 14 dias;
- catálogo público por `/loja/{slug}`;
- presets de vitrine por segmento, persistidos por estabelecimento e substituíveis sem apagar a identidade personalizada;
- matriz de módulos por segmento aplicada no menu, nas rotas e nas mutações do servidor (recursos de salão, cozinha e garçom ficam restritos a estabelecimentos de alimentação);
- vocabulário de catálogo por segmento, reutilizando as mesmas regras seguras de variações, preços, kits e estoque sem criar domínios paralelos;
- SKU e estoque individual por variação, isolados por estabelecimento, com bloqueio e baixa dentro da transação do pedido;
- entrega ou retirada;
- pedidos persistidos e valores recalculados no backend;
- cupom, taxa, estoque e totais validados no servidor;
- pagamento manual no atendimento (Pix, dinheiro ou maquininha), sem simular gateway online;
- atualização de status e acompanhamento com token aleatório, expirável e revogável;
- auditoria básica, CSRF, CSP, headers e rate limiting;
- migrations com checksum;
- container PHP/Apache, MySQL e health check.

O pedido e o acompanhamento funcionam sem integrações externas. Gateways online, e-mail e WhatsApp não são apresentados como recursos ativos nesta versão.

## Requisitos

- Docker Engine com Compose; ou
- PHP 8.3 com `pdo_mysql`, Apache e MySQL 8.4.

## Instalação local com Docker

1. Copie o exemplo:

   ```bash
   cp .env.example .env
   ```

2. Substitua todos os placeholders. Gere chaves longas e aleatórias para `APP_KEY`, `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD`; informe também `LEGAL_OPERATOR_NAME` e `PRIVACY_CONTACT_EMAIL`.
3. Para desenvolvimento local, configure:

   ```dotenv
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost:8080
   SESSION_SECURE_COOKIE=false
   ```

4. Inicie:

   ```bash
   docker compose up -d --build
   docker compose exec app php bin/migrate.php
   ```

5. Acesse `http://localhost:8080/cadastro` e crie a primeira empresa. O primeiro usuário dessa empresa será proprietário.

## Primeiro administrador da plataforma

Não existe senha padrão. Depois de criar a primeira empresa, execute interativamente:

```bash
docker compose exec app php bin/create-admin.php
```

O comando pede nome, e-mail e senha com no mínimo 15 caracteres, oculta a senha no terminal Linux do container e grava somente o hash. Ele se recusa a criar outro administrador da plataforma quando um já existe.

## Endereços

- landing page: `/`
- cadastro: `/cadastro`
- login administrativo: `/entrar`
- painel protegido: `/admin`
- painel global exclusivo do fundador: `/platform`
- catálogo: `/loja/{slug}`
- acompanhamento: `/pedido/{token-seguro}`
- health check: `/health`

## Migrations

Na imagem Docker, o entrypoint aguarda o MySQL e aplica automaticamente somente as migrations pendentes antes de iniciar o Apache. Para executar manualmente ou conferir a situação:

```bash
php bin/migrate.php
```

O executor registra versão e checksum. Uma migration já aplicada nunca deve ser editada; crie outro arquivo numerado.

Antes de iniciar o Apache, a imagem executa `php bin/preflight.php`. Em produção, essa verificação bloqueia a inicialização quando HTTPS, cookies seguros, contato LGPD, segredos, MySQL ou diretórios persistentes estiverem configurados incorretamente. Para validar manualmente no container:

```bash
php bin/preflight.php
```

Para entregar webhooks pendentes, configure no EasyPanel uma tarefa agendada (por exemplo, a cada minuto):

```bash
php bin/process-webhooks.php 50
```

O worker aceita somente HTTPS na porta 443, bloqueia redes privadas, não segue redirecionamentos, assina o corpo com HMAC SHA-256 e repete falhas com espera progressiva.

Para ativar a recuperação de senha, configure `MAIL_ENABLED=true` e as variáveis SMTP de `.env.example`. Depois, agende também a cada minuto:

```bash
php bin/process-email-queue.php 20
```

Agende ainda uma manutenção diária, independente do SMTP, para remover somente registros técnicos expirados ou concluídos em lotes controlados:

```bash
php bin/cleanup-expired-data.php 1000
```

A rotina conserva auditorias, pedidos e dados comerciais. Ela remove tentativas antigas, rate limits vencidos, tokens expirados ou usados, sessões antigas e mensagens transacionais concluídas conforme os prazos documentados no próprio script.

O e-mail fica em uma fila persistente com conteúdo criptografado, tentativas limitadas e espera progressiva. A mesma configuração entrega confirmação de e-mail, recuperação de senha e códigos de 2FA. Enquanto `MAIL_ENABLED=false`, esses fluxos ficam sinalizados como indisponíveis e não bloqueiam contas existentes. O 2FA só pode ser ativado por administrador da plataforma, proprietário ou gerente com e-mail confirmado. Ao ativar, são emitidos oito códigos de recuperação de uso único; somente os hashes ficam no banco e a lista em texto é mostrada uma única vez.

Cada login cria também um registro revogável em `user_sessions`. O token bruto permanece apenas na sessão PHP; banco, histórico e auditoria recebem somente hashes e um rótulo resumido do dispositivo. Em Configurações, o usuário pode encerrar acessos individuais ou todos os outros dispositivos.

Contas com e-mail confirmado podem receber alertas de novo dispositivo. O reconhecimento usa somente o hash do agente do navegador, nunca o IP ou o agente em texto aberto, e a preferência pode ser alterada pelo próprio usuário em Configurações.

Em Configurações, cada usuário visualiza também os últimos eventos de segurança da própria conta. A consulta é limitada pelo `tenant_id` e pelo usuário autenticado e não expõe IP, hashes ou metadados técnicos.

O login limita falhas por origem e também o total agregado por identidade. Ao detectar cinco falhas recentes de uma conta existente, registra um evento seguro e, quando o e-mail transacional está ativo e confirmado, envia no máximo um alerta a cada 30 minutos. A resposta exibida no login permanece genérica e nunca confirma se a conta existe.

As respostas web aplicam CSP, bloqueio de MIME sniffing, política de referência, isolamento de origem e restrição das permissões do navegador. A aplicação só pode ser incorporada por páginas da própria origem, preservando a prévia interna da vitrine e impedindo framing externo. Scripts executáveis permanecem restritos a arquivos da própria aplicação. Estilos inline continuam permitidos porque gráficos, banners e identidade visual usam valores dinâmicos por estabelecimento; essa exceção deve ser removida somente após migrar esses valores para classes ou arquivos gerados com segurança.

Defina `REQUIRE_PRIVILEGED_2FA=true` para exigir 2FA de administradores da plataforma, proprietários e gerentes. Contas ainda não configuradas ficam limitadas aos controles indispensáveis de segurança até concluírem a ativação. A política exige SMTP funcional e não pode ser usada para simular entrega de códigos.

## Testes

Execute as verificações automatizadas com PHP 8.3+:

```bash
php tests/run.php
```

Para validar a sintaxe de todos os arquivos PHP:

```bash
find . -path ./vendor -prune -o -name '*.php' -exec php -l {} \;
```

## EasyPanel

1. Crie um serviço MySQL privado e persistente.
2. Crie a aplicação usando o `Dockerfile` deste repositório.
3. Cadastre no serviço da aplicação as variáveis descritas em `.env.example`, exceto `MYSQL_ROOT_PASSWORD`; não faça upload do `.env`.
4. Configure `MYSQL_ROOT_PASSWORD` somente no serviço MySQL. A aplicação usa exclusivamente `DB_USERNAME` e `DB_PASSWORD` com privilégios limitados.
5. Monte volume persistente em `/var/www/html/storage`.
6. Configure o domínio e HTTPS.
7. Acompanhe os logs e confirme que o entrypoint concluiu o preflight e `php bin/migrate.php`.
8. Configure o health check em `/health`.
9. Valide login, catálogo, criação de pedido, atualização e isolamento entre dois tenants.

Se o proxy reverso substituir o IP remoto, preencha `TRUSTED_PROXY_IPS` somente com os IPs internos confiáveis do proxy. Nunca aceite `X-Forwarded-For` de qualquer origem.

O checklist detalhado está em [DEPLOY_EASYPANEL.md](DEPLOY_EASYPANEL.md).

### Criar os planos de homologação do Mercado Pago

Com `MERCADOPAGO_ENV=sandbox`, `MERCADOPAGO_ACCESS_TOKEN` e `APP_URL` configurados no EasyPanel, abra o terminal do container da aplicação e execute uma única vez:

```bash
php bin/create-mercadopago-plans.php
```

O comando cria apenas os planos sandbox que ainda não possuem ID configurado e imprime `MERCADOPAGO_MONTHLY_PLAN_ID` e `MERCADOPAGO_ANNUAL_PLAN_ID`. Copie somente esses IDs para as variáveis da aplicação e faça um novo deploy. Configure também `MERCADOPAGO_PUBLIC_KEY` com a Public Key do mesmo ambiente. O token nunca deve ser copiado para logs, arquivos ou conversas.

O cadastro do cartão usa o `MercadoPago.js` oficial. O navegador envia os dados sensíveis diretamente ao Mercado Pago e entrega ao PHP somente um token temporário. A aplicação valida novamente plano, preço, tenant, função e CSRF no servidor; a assinatura permanece pendente até o webhook autenticado confirmar o estado no provedor.

O plano mensal de fundadores é provisionado a R$ 59,90 e o anual a R$ 599,00. Os 14 dias grátis não são cadastrados no Mercado Pago: continuam começando na primeira publicação da vitrine, evitando conceder dois períodos gratuitos. A mudança automática do mensal para R$ 99,00 após a terceira cobrança será habilitada somente junto do webhook e do controle persistente da assinatura.

## Segurança e operação

- nunca exponha MySQL publicamente;
- mantenha `APP_DEBUG=false` em produção;
- use HTTPS e cookies seguros;
- faça backup do MySQL e de `storage/` em destino externo;
- teste a restauração regularmente;
- não armazene `.env`, tokens, dumps ou logs no Git;
- confirme permissões e isolamento por tenant após cada mudança;
- configure `TRACKING_LINK_TTL_DAYS`; um link comprometido pode ser revogado por uma rotina administrativa controlada preenchendo `orders.tracking_revoked_at`.

