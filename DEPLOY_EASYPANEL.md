# Checklist de publicação — VPS com EasyPanel

## Estado atual

O código executável está em `public/`, `src/`, `bin/` e `migrations/`. O diretório `outputs/` é apenas o protótipo visual legado e não faz parte da imagem Docker nem do deploy.

O núcleo funcional inclui cadastro, autenticação, confirmação de e-mail, recuperação segura de senha, 2FA por fila SMTP e códigos de recuperação descartáveis, sessões revogáveis por dispositivo, RBAC, isolamento por `tenant_id`, catálogo, estoque, clientes, opções, combos, salão e mesas, produtos, áreas, cupons, pedidos, modos operacionais, avaliações, financeiro, LGPD, acompanhamento, auditoria, migrations e health check. Pagamento online e WhatsApp Cloud API ainda dependem da definição final de fornecedores e credenciais e não devem ser anunciados como recursos ativos. Os recursos de e-mail só devem ser habilitados depois de configurar e validar o SMTP e o worker.

Ao publicar a migration `028_user_sessions.sql`, acessos autenticados anteriores serão encerrados uma única vez, pois ainda não possuem o novo token persistente. Isso é esperado: após um novo login, cada dispositivo passa a ter uma sessão individual, expiração configurável e revogação pelo painel.

A migration `029_new_device_alerts.sql` ativa por padrão a preferência de alerta de novo dispositivo. O envio somente ocorre quando `MAIL_ENABLED=true`, o endereço do usuário está confirmado e o worker de e-mail está em execução. Falha no SMTP não bloqueia o login; o evento continua registrado na auditoria.

`REQUIRE_PRIVILEGED_2FA` deve permanecer `false` até que SMTP, worker, confirmação de e-mail, códigos de recuperação e restauração de acesso tenham sido homologados. Depois disso, defina como `true` para restringir administradores da plataforma, proprietários e gerentes que ainda não ativaram o 2FA.

Não liberar tráfego real antes de concluir os testes deste checklist em homologação, especialmente isolamento entre tenants, restauração de backup, uploads, concorrência de estoque e fluxo completo de pedidos.

## 1. Preparação da VPS e do EasyPanel

- [ ] Usar uma versão suportada do sistema operacional e aplicar todas as atualizações de segurança.
- [ ] Permitir no firewall somente SSH administrativo, HTTP e HTTPS.
- [ ] Restringir SSH por chave; desabilitar autenticação por senha quando possível.
- [ ] Instalar o EasyPanel pelo procedimento oficial vigente.
- [ ] Proteger a conta administrativa do EasyPanel com senha exclusiva e 2FA, quando disponível.
- [ ] Criar ambientes separados para homologação e produção.
- [ ] Configurar política de reinício automático do serviço web.
- [ ] Não expor a porta do MySQL publicamente.

## 2. Serviços no EasyPanel

Criar serviços separados:

1. aplicação PHP;
2. MySQL em rede interna;
3. worker/fila somente quando a implementação exigir processamento assíncrono;
4. armazenamento persistente para uploads, logs necessários e artefatos operacionais.

- [ ] Confirmar que a raiz pública da aplicação é `public/`.
- [ ] Garantir que `.env`, código-fonte privado, migrations, logs e backups não sejam servidos pelo servidor web.
- [ ] Fixar versões de runtime e imagens; não depender de tags flutuantes como `latest` em produção.
- [ ] Definir limites de CPU, memória e armazenamento, além de alertas de capacidade.

## 3. Variáveis de ambiente

Usar `.env.example` apenas como catálogo de nomes. Os valores reais devem ser cadastrados como secrets/variáveis no EasyPanel e nunca enviados ao Git.

- [ ] Gerar `APP_KEY`, `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD` com fonte criptograficamente segura.
- [ ] Configurar `LEGAL_OPERATOR_NAME` e `PRIVACY_CONTACT_EMAIL` com dados reais do operador.
- [ ] Usar credenciais diferentes em homologação e produção.
- [ ] Configurar `APP_ENV=production` e `APP_DEBUG=false`.
- [ ] Configurar URLs HTTPS definitivas e fuso `America/Sao_Paulo`.
- [ ] Configurar cookies `Secure`, `HttpOnly` e `SameSite`.
- [ ] Para recuperação de senha, configurar as variáveis `MAIL_*`, definir `MAIL_ENABLED=true` e validar remetente, TLS e entrega real.
- [ ] Agendar a cada minuto `php /var/www/html/bin/process-email-queue.php 20`; manter `MAIL_ENABLED=false` enquanto esse worker não estiver ativo.
- [ ] Agendar diariamente `php /var/www/html/bin/cleanup-expired-data.php 1000` e acompanhar a saída sem registrar conteúdo sensível.
- [ ] Rotacionar segredos após exposição suspeita ou troca de responsáveis.
- [ ] Verificar que nenhuma variável secreta aparece em HTML, JavaScript, respostas de erro ou logs.
- [ ] Não copiar literalmente nenhum valor de exemplo para produção.
- [ ] Em homologação, manter `MERCADOPAGO_ENV=sandbox` e executar `php bin/create-mercadopago-plans.php` somente no terminal do container da aplicação.
- [ ] Copiar os dois IDs retornados para `MERCADOPAGO_MONTHLY_PLAN_ID` e `MERCADOPAGO_ANNUAL_PLAN_ID`; nunca copiar o access token para logs, arquivos ou conversas.
- [ ] Configurar `MERCADOPAGO_PUBLIC_KEY` com a Public Key das mesmas credenciais usadas pelo Access Token. O formulário tokeniza o cartão no navegador e nunca envia número ou CVV ao PHP.
- [ ] Não configurar teste grátis no Mercado Pago: os 14 dias são controlados pela aplicação a partir da primeira publicação da vitrine.
- [ ] Configurar no Mercado Pago o tópico `subscription_preapproval` apontando para `https://SEU-DOMINIO/webhooks/mercadopago`; cada assinatura também envia explicitamente `https://SEU-DOMINIO/webhooks/mercadopago?source_news=webhooks`, como exigido para Assinaturas.
- [ ] Copiar a assinatura secreta gerada pelo Mercado Pago para `MERCADOPAGO_WEBHOOK_SECRET` no ambiente correspondente; nunca reutilizar o segredo de sandbox em produção.
- [ ] Simular uma notificação no painel do Mercado Pago e confirmar resposta HTTP 200/202 e evento processado sem dados sensíveis nos logs.

## 4. Banco MySQL

- [ ] Criar banco e usuário exclusivos para a aplicação.
- [ ] Conceder apenas os privilégios necessários; evitar uso de `root` pela aplicação.
- [ ] Usar conexão pela rede interna do EasyPanel.
- [ ] Definir `utf8mb4` e timezone consistente.
- [ ] Ativar conexões seguras quando o banco trafegar fora da rede privada.
- [ ] Confirmar índices para `tenant_id`, chaves estrangeiras e consultas críticas.
- [ ] Verificar que toda consulta de recurso pertencente a estabelecimento aplica isolamento pelo tenant autenticado.
- [ ] Usar PDO e prepared statements; nunca concatenar entrada do usuário em SQL.

## 5. Migrations seguras

- [ ] Manter migrations numeradas, versionadas e imutáveis após aplicadas.
- [ ] Criar tabela de controle com versão, checksum, data, duração e resultado.
- [ ] Executar migrations por uma tarefa administrativa única, nunca em cada requisição web.
- [ ] Fazer backup verificável antes de migration destrutiva ou mudança estrutural relevante.
- [ ] Testar a migration em cópia anonimizada ou estrutura equivalente à produção.
- [ ] Preferir mudanças compatíveis em etapas: adicionar, migrar dados, atualizar aplicação e somente depois remover estrutura antiga.
- [ ] Evitar bloqueios longos; avaliar tamanho das tabelas antes de `ALTER TABLE`.
- [ ] Documentar rollback ou procedimento de restauração para cada release.
- [ ] Interromper o deploy se migration, checksum ou teste de integridade falhar.

## 6. Domínio e HTTPS

- [ ] Definir os domínios definitivos, por exemplo painel e catálogo público.
- [ ] Apontar registros DNS para o IP correto da VPS.
- [ ] Configurar domínio no serviço correspondente do EasyPanel.
- [ ] Emitir certificado TLS e verificar renovação automática.
- [ ] Redirecionar HTTP para HTTPS.
- [ ] Confirmar que o HTTPS público responde com HSTS por um ano, sem `includeSubDomains` ou `preload` até que todos os subdomínios sejam auditados.
- [ ] Confirmar em produção os cabeçalhos CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, isolamento de origem e `X-Frame-Options: SAMEORIGIN`.
- [ ] Verificar que páginas externas não incorporam o sistema e que a prévia interna da vitrine no painel continua funcionando.
- [ ] Configurar corretamente proxies confiáveis para não aceitar cabeçalhos forjados de origem/protocolo.

## 7. Health check

O endpoint `GET /health` implementado deve:

- responda rapidamente;
- não exija autenticação;
- não revele versões, credenciais, caminhos ou mensagens internas;
- retorne `200` somente quando a aplicação estiver pronta para receber tráfego;
- verifique a conexão essencial com o banco usando operação mínima e com timeout;
- não adicione dependência de serviços externos ao health check do núcleo.

Resposta pública sugerida:

```json
{"status":"ok"}
```

- [ ] Configurar o EasyPanel para consultar `/health` por HTTPS ou pela rede interna.
- [ ] Definir intervalo, timeout e tentativas antes de reiniciar o serviço.
- [ ] Criar checagens internas separadas para filas, espaço em disco, backups e integrações.

## 8. Armazenamento persistente

- [ ] Montar volume persistente no caminho definido por `STORAGE_PATH`.
- [ ] Persistir uploads e outros arquivos que não possam ser recriados.
- [ ] Não depender do filesystem efêmero do container.
- [ ] Manter uploads fora da raiz pública ou servi-los por controlador seguro.
- [ ] Validar MIME real, tamanho, extensão permitida e nome aleatório dos uploads.
- [ ] Bloquear execução de PHP e scripts no diretório de uploads.
- [ ] Definir quotas por tenant e alertas de uso.
- [ ] Incluir o volume persistente na estratégia de backup.

## 9. Backups

- [ ] Automatizar backup consistente do MySQL.
- [ ] Fazer backup dos uploads persistentes e das configurações indispensáveis.
- [ ] Criptografar backups em repouso e em trânsito.
- [ ] Armazenar ao menos uma cópia fora da VPS e fora do EasyPanel.
- [ ] Aplicar retenção diária, semanal e mensal conforme necessidade e LGPD.
- [ ] Restringir o acesso e registrar operações administrativas sobre backups.
- [ ] Monitorar execução, tamanho, duração e falhas; ausência de alerta não significa sucesso.
- [ ] Nunca incluir `.env` em backup sem criptografia e controle de acesso apropriados.

## 10. Restauração e continuidade

- [ ] Documentar responsáveis, localização dos backups e procedimento de recuperação.
- [ ] Definir RPO e RTO aceitáveis para o negócio.
- [ ] Restaurar primeiro em ambiente isolado.
- [ ] Validar checksum, schema, contagens, tenants, pedidos e arquivos após restauração.
- [ ] Trocar credenciais se houver suspeita de comprometimento.
- [ ] Realizar teste completo de restauração antes do primeiro lançamento e periodicamente.
- [ ] Registrar tempo real de recuperação e corrigir o procedimento quando ultrapassar o RTO.

## 11. Logs, monitoramento e incidentes

- [ ] Produzir logs estruturados com timestamp, nível, request ID e identificadores internos mínimos.
- [ ] Mascarar senhas, tokens, cookies, documentos, telefones, e-mails, endereços e conteúdo de pagamento.
- [ ] Separar logs operacionais de trilha de auditoria.
- [ ] Definir retenção e rotação para evitar esgotamento de disco.
- [ ] Alertar para indisponibilidade, erros 5xx, falha de login anormal, filas acumuladas, disco, banco e backup.
- [ ] Não retornar stack traces ao usuário em produção.
- [ ] Criar procedimento de resposta a incidentes: conter, preservar evidências, rotacionar segredos, recuperar, comunicar e revisar.

## 12. Ordem do deploy

- [ ] Congelar e identificar a versão que será publicada.
- [ ] Confirmar backup e restauração testada.
- [ ] Publicar código compatível com o schema atual.
- [ ] Executar migrations administrativas.
- [ ] Reiniciar/atualizar aplicação e workers de forma controlada.
- [ ] Verificar `/health`.
- [ ] Executar smoke tests.
- [ ] Monitorar métricas e logs durante a janela de estabilização.
- [ ] Manter versão anterior pronta para rollback de código, sem tentar desfazer dados de maneira insegura.

## 13. Testes após publicar

### Plataforma e segurança

- [ ] HTTPS válido, redirecionamento e cabeçalhos de segurança.
- [ ] `.env`, logs, backups e diretórios privados inacessíveis pela web.
- [ ] Cadastro, login, logout, expiração e recuperação de senha.
- [ ] CSRF, rate limiting e bloqueio de acesso sem permissão.
- [ ] Upload válido e rejeição de conteúdo malicioso ou fora dos limites.

### Multi-tenant e permissões

- [ ] Criar dois tenants de teste e confirmar isolamento completo.
- [ ] Tentar acessar IDs e URLs do outro tenant; a operação deve ser negada sem revelar existência.
- [ ] Validar proprietário, gerente, atendente, garçom, cozinha, PDV e entregador.
- [ ] Confirmar que esconder um botão não é o único controle de autorização.

### Catálogo, pedidos e pagamentos

- [ ] Publicar catálogo e resolver corretamente o slug do estabelecimento.
- [ ] Criar pedido de entrega, retirada e mesa conforme canais habilitados.
- [ ] Alterar preço e total no navegador; o servidor deve ignorar e recalcular.
- [ ] Validar cupom, adicionais, taxa, estoque e estados do pedido.
- [ ] Confirmar idempotência de finalização, webhooks e retentativas.
- [ ] Usar apenas ambientes de teste dos gateways antes de liberar pagamentos reais.

### Operação e recuperação

- [ ] Confirmar que o fluxo de pedidos não exige gateway, Meta ou SMTP.
- [ ] Confirmar fallback de acompanhamento pelo site.
- [ ] Executar backup manual de validação e uma restauração isolada.
- [ ] Confirmar alertas, rotação de logs e persistência após reiniciar/recriar containers.

## Critério de liberação

A publicação para clientes reais somente deve ocorrer quando todos os itens críticos aplicáveis estiverem concluídos e houver evidência dos testes em homologação. Integrações ainda não configuradas devem permanecer desabilitadas e não podem ser apresentadas como disponíveis.


