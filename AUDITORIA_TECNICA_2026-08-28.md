# Auditoria técnica do projeto — 28/08/2026

## Escopo e método

Revisão estática do projeto PHP/MySQL, preservando o frontend aprovado e considerando a operação multi-tenant no EasyPanel. O Windows desta tarefa não possui PHP, MySQL ou Docker para executar a suíte; migrations, testes de integração e smoke tests devem ser confirmados pelo CI do GitHub e pelo ambiente de homologação.

## Resumo executivo

O projeto já possui uma base funcional relevante: cadastro público, autenticação por sessão, RBAC, isolamento por `tenant_id`, catálogo, pedidos com recálculo no servidor, estoque, cupons, mesas e QR, PDV, modos operacionais, relatórios, auditoria, solicitações LGPD, PWA, webhooks de saída, preferências de notificações, links rápidos, redes sociais e programa de indicação.

Ainda não deve ser apresentado como integralmente concluído. Pagamento online/assinatura recorrente, WhatsApp Cloud API, recuperação de conta, verificação de e-mail, 2FA, multiunidade, NFS-e, pixels com consentimento, automações de relacionamento e impressão térmica dependem de implementação ou integração adicional.

## Painel administrador da plataforma

**Estado anterior:** já existia em `/platform`, protegido por `platform_admin`, com total de lojas, usuários, pedidos, volume e suspensão/reativação. Não havia acompanhamento suficiente do responsável, teste grátis e assinatura. A consulta de lojas também podia multiplicar o volume quando um tenant possuía mais de um usuário.

**Implementado nesta revisão:** métricas de testes ativos e próximos do vencimento, assinaturas ativas e inadimplentes, responsável e e-mail da loja, último pedido, plano, período e estado da assinatura. A agregação de usuários e pedidos foi separada para impedir duplicação do faturamento. A migration `023_tenant_billing_accounts.sql` prepara o estado real da assinatura sem simular cobrança.

**Implementado no bloco seguinte:** busca por loja, slug, cidade ou responsável; filtros por situação; paginação no servidor; página de detalhe segura com responsáveis, equipe, operação, teste e assinatura. O detalhe não carrega dados pessoais de clientes finais.

**Próximos incrementos:** histórico de mudanças da assinatura e integração do provedor escolhido por webhook autenticado.

## Correção visual desta revisão

- O agrupamento flutuante da vitrine passou a centralizar seus filhos com `justify-items: center`.
- O botão de tema mantém 48 px e o WhatsApp 58 px, mas ambos agora compartilham o mesmo eixo central.
- A versão do CSS da vitrine foi atualizada para evitar cache antigo em produção.
- Foi adicionada uma verificação estática para impedir a regressão do alinhamento.

## Prioridade crítica — antes de ampliar a operação

### 1. Recuperação de conta e autenticação reforçada

**Estado:** login, logout, hash de senha, sessão protegida, troca de senha e limitação de tentativas existem. Recuperação de senha, verificação de e-mail e 2FA não estão concluídas.

**Implementar:** tokens aleatórios de uso único, hash do token no banco, validade curta, revogação após uso, respostas sem enumeração de conta, SMTP configurado por ambiente e 2FA para contas privilegiadas.

**Testar:** token expirado, reutilizado, adulterado e de outro usuário; enumeração por e-mail; invalidação de sessões após troca; rate limiting; acesso direto a rotas administrativas.

### 2. Pagamento online e assinatura recorrente

**Estado:** pagamentos no atendimento e regras de valores mínimos são funcionais. A tela de assinatura informa corretamente que a cobrança online está em preparação.

**Implementar:** provedor escolhido, estado de pagamento separado do pedido, checkout criado no backend, webhooks assinados e idempotentes, reconciliação, cancelamento, renovação, inadimplência e auditoria. Nunca armazenar dados de cartão.

**Testar:** webhook inválido, duplicado e fora de ordem; pagamento aprovado, recusado, estornado e expirado; troca de plano; isolamento entre tenants; valores recalculados no servidor.

### 3. WhatsApp manual atual e automação oficial futura

**Estado atual aprovado:** o pedido é persistido no sistema, recebe um link seguro de acompanhamento e o cliente pode abrir o WhatsApp com uma mensagem pronta para o número cadastrado pela empresa. O envio depende de uma ação consciente do cliente e a empresa responde manualmente. Este fluxo usa `wa.me`, não precisa da API da Meta e nunca substitui o acompanhamento pelo site.

**Automação futura e opcional:** confirmação automática, mensagens de mudança de status e leitura programática de mensagens dependerão da WhatsApp Cloud API oficial. Não apresentar essa automação como requisito para lançar a primeira versão.

**Implementar:** cadastro incorporado da Meta, associação segura de WABA e `phone_number_id` ao tenant, tokens protegidos, consentimento, modelos aprovados, janela de 24 horas, fila, retentativas e webhooks assinados.

**Testar:** assinatura inválida, número de outro tenant, mensagem duplicada, modelo indisponível, opt-out, token revogado e indisponibilidade da Meta sem bloquear o pedido.

### 4. Migrations e recuperação operacional

**Estado:** migrations possuem controle por checksum e são aplicadas no deploy. Como DDL do MySQL não é plenamente transacional, uma migration com várias alterações pode ficar parcialmente aplicada.

**Implementar:** migrations pequenas e idempotentes, backup antes de alterações destrutivas, política de rollback compatível com cada mudança e exercício periódico de restauração.

**Testar:** banco vazio, banco já atualizado, migration interrompida, reexecução segura e restauração completa em ambiente separado.

## Prioridade alta

### 5. Testes de segurança e multi-tenant

A suíte atual contém boa cobertura estática e smoke test de cadastro/autenticação. Ampliar com testes reais de integração para IDOR, papéis, alterações de status, concorrência de estoque, cupons, mesas, entregadores, webhooks e APIs públicas. Adicionar testes de navegador nos breakpoints celular, tablet e desktop para tema claro/escuro e fluxos principais.

### 6. Upload de imagens

O upload valida tamanho, MIME, dimensões básicas, extensão permitida, nome aleatório e ownership. Acrescentar limite de pixels, reprocessamento da imagem para remover metadados e conteúdo excedente, quotas por tenant e limpeza segura de arquivos órfãos.

### 7. CSP e dependências externas

A aplicação envia cabeçalhos de segurança, mas a CSP ainda permite estilos inline e fontes externas. Hospedar fontes localmente e migrar estilos inline para classes ou nonce/hash, reduzindo gradualmente `unsafe-inline`. Ativar HSTS somente depois de confirmar HTTPS permanente em todos os subdomínios necessários.

### 8. Logs, monitoramento e incidentes

Auditoria funcional está presente. Falta formalizar retenção, mascaramento, rotação, alertas de erro, indisponibilidade, filas paradas e falhas de webhook. Criar runbook de incidente, responsáveis, comunicação, revogação de segredos e evidência de recuperação.

### 9. LGPD

Há páginas legais e fila de solicitações. Completar política de retenção, execução auditável de exportação/correção/exclusão/anônimização, confirmação de identidade, inventário de operadores e suboperadores, base legal por finalidade e exclusão de backups conforme política documentada.

## Prioridade média — evolução e manutenção

- Dividir gradualmente `public/index.php`, hoje muito concentrado, em controladores/serviços por domínio sem mudar rotas ou visual.
- Reduzir views administrativas duplicadas por meio de layouts e componentes PHP seguros.
- Fixar versões ou digest das imagens Docker e adicionar verificação de vulnerabilidades no CI.
- Automatizar backup, retenção e teste de restauração; o projeto hoje documenta o processo, mas não contém utilitário dedicado.
- Definir limites de CPU, memória, disco e crescimento de logs no EasyPanel.
- Evitar limpeza de registros expirados no caminho crítico de requisições; mover manutenção para tarefa agendada.
- Atualizar a auditoria de paridade visual antiga, pois indicação, redes sociais, links rápidos e notificações do painel já possuem persistência e não devem continuar descritos como totalmente pendentes.

## Recursos que permanecem deliberadamente bloqueados

- Gateway de pagamento e link externo sem integração validada.
- WhatsApp Cloud API automática.
- Carrinho abandonado e win-back.
- Pixels/analytics sem gestão de consentimento.
- Multiunidade.
- API pública com tokens e escopos.
- NFS-e.
- Impressora térmica.
- Verificação em duas etapas.

Os cartões desabilitados são uma proteção correta enquanto backend, banco, permissões, auditoria e integração segura não existirem.

## Atualizações recomendadas para a Skill `micro-saas-delivery`

Não alterar a Skill sem autorização específica. Na próxima revisão autorizada, acrescentar:

1. exigir teste visual nos três breakpoints para toda mudança de tema ou layout;
2. exigir inventário explícito de recursos: funcional, parcial, visual ou bloqueado;
3. exigir migration pequena/idempotente e plano de recuperação para DDL;
4. exigir teste de restauração, não apenas existência de backup;
5. exigir reprocessamento e limite de pixels em uploads de imagem;
6. exigir recuperação de senha, verificação de contato e 2FA antes de considerar autenticação completa;
7. exigir SAST, análise de dependências e imagem de contêiner no CI antes da produção;
8. registrar que documentos de auditoria devem ser atualizados quando um cartão planejado se tornar funcional.
9. distinguir explicitamente o modo WhatsApp manual atual (`wa.me` + link seguro + resposta humana) da automação futura pela Cloud API; não exigir API oficial para lançar o fluxo manual.

## Critério para a próxima publicação

1. conferir somente os arquivos alterados;
2. executar validações estáticas e `git diff --check` apenas como inspeção local;
3. publicar na `main` pelo plugin GitHub, sem force push;
4. aguardar migrations, lint, testes e smoke test do CI;
5. homologar vitrine e painel no EasyPanel em 390 px, 768 px e 1440 px, nos temas claro e escuro;
6. confirmar health check, logs e ausência de regressão no checkout.
