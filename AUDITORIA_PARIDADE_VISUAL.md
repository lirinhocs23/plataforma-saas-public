# Auditoria de paridade visual — protótipo x aplicação PHP

Fonte visual aprovada: `outputs/`. Fonte funcional: `src/views/`, `public/assets/` e `public/index.php`.

## Critério

Uma tela só é considerada migrada quando preserva o casco visual, hierarquia, responsividade e estados do protótipo, usando dados reais do tenant e ações validadas no servidor. Elementos demonstrativos não podem ser copiados como se fossem funcionalidades reais.

## Matriz de auditoria

| Área | Estado | Diferenças ou ação necessária |
|---|---|---|
| Landing, login e cadastro | Parcialmente equivalente | Revisar todos os breakpoints, mensagens de teste grátis, modal/cadastro e estados de erro reais. |
| Dashboard | Migrado, requer QA visual | Casco, controle da loja, primeiros passos, métricas e gráficos usam dados reais. Comparar espaçamentos em desktop/tablet/celular. |
| Estoque, movimentações e clientes | Migrado, requer QA visual | Dados e formulários são reais; revisar toolbar, tabela, vazio e paginação. |
| Catálogo, adicionais, combos e cupons | Migrado, requer QA visual | Operações reais foram preservadas e o módulo recebeu cabeçalho, busca, ação principal, cartões e responsividade no padrão aprovado. Ainda comparar densidade e formulários em produção. |
| Pedidos | Migrado, requer QA visual | Kanban, busca, estados e cartões usam pedidos reais; exportação por período está protegida por função e tenant. Revisar somente densidade nos breakpoints. |
| Salão, mesas e QR Code | Migrado, requer QA visual | Cadastro, setores, comandas e QR seguro existem; revisar abas e tabela no desktop. |
| Cozinha, Garçom, Entregador e PDV | Migrado para URLs independentes | Casco independente e operações reais existem; comparar cartões, controles e responsividade com os quatro protótipos. |
| Vitrine pública | Migrada, requer QA visual completo | Catálogo, modal, carrinho, cupom, checkout e acompanhamento usam backend. Hero configurável, cupom e CTA responsivos, menu móvel completo e barra persistente da sacola foram restaurados; ainda revisar imagens vazias e breakpoints intermediários. |
| Configurações gerais | Migrada visualmente, backend parcial | Dados, identidade, canais, pagamentos, horários, taxas, cashback e fidelidade são reais. Os demais cartões aprovados estão visíveis e explicitamente bloqueados até receberem banco, permissões ou integrações seguras. |
| PWA e QR da vitrine | Migrado, requer QA visual | Manifesto, instalação, compartilhamento e QR por tenant são reais. O QR possui tela própria, download SVG, impressão, cópia e link público real. |
| Assinatura e pagamento | Migrada visualmente nesta etapa | Oferta aprovada foi incorporada. Cobrança permanece bloqueada até gateway e webhooks reais. |
| Relatórios e DRE | Migrado, requer QA visual | Métricas reais existem; alinhar composição visual e estados vazios. |
| Webhooks, LGPD e auditoria | Migrado, requer QA visual | Agora usam painel dedicado, métricas reais, formulários protegidos, estados vazios e linha do tempo por tenant. |
| Perfil, temas e notificações | Parcialmente equivalente | Perfil e notificações existem; validar temas claro/escuro/sistema e estado vazio do sino. |
| Avaliações, lista de espera e assinaturas | Migrado, requer QA visual | Módulos dedicados usam métricas, busca, estados vazios, moderação, agenda e formulários reais isolados por tenant. Cobrança recorrente não é simulada. |

## Configurações avançadas ainda não concluídas

- conexão oficial do WhatsApp pela Meta;
- gateway e link de pagamento externo;
- recuperação de carrinho e win-back;
- programa de indicação;
- redes sociais, pixels e analytics;
- multiunidade;
- API pública administrável;
- NFS-e;
- atalhos personalizados do dashboard;
- preferências de notificações;
- autenticação em duas etapas;
- impressora térmica de rede.

Esses itens exigem banco, validação, permissões, integrações ou infraestrutura. Devem ser implementados de forma incremental; não podem permanecer como interruptores que fingem estar ativos.

## Bloco migrado nesta auditoria

- tela de assinatura aprovada, mantendo cobrança desativada até existir gateway real;
- página PWA com instruções, estado, compartilhamento e identidade do tenant;
- página exclusiva do QR da vitrine, com download, impressão e cópia funcionais;
- remoção do item PWA duplicado na navegação;
- interruptores reais de pagamentos, taxas, cashback e fidelidade controlando os próprios campos, sem botão separado de expandir;
- menu móvel da vitrine e barra persistente da sacola sincronizados com o carrinho real.
- webhooks, LGPD e auditoria removidos da tela genérica e migrados para composição visual dedicada com dados reais.
- avaliações, lista de espera e assinaturas removidas da tela genérica, preservando moderação e persistência reais.
- catálogo funcional recebeu cabeçalho, busca e ação principal no padrão aprovado, sem reintroduzir registros demonstrativos;
- configurações exibe a arquitetura visual dos recursos avançados ainda pendentes, com controles bloqueados e estados transparentes;
- versões de cache dos arquivos CSS e JavaScript foram renovadas em todas as views para evitar que o EasyPanel sirva o visual anterior após o deploy.
- banner da vitrine agora persiste título, destaque e CTA por tenant, mantendo a composição aprovada sem fixar o sistema em hamburguerias;
- cupom e chamada principal da vitrine compartilham o alinhamento aprovado no desktop e se reorganizam em telas menores;
- pedidos recebeu busca real no Kanban e exportação CSV por período com autorização administrativa e isolamento por tenant.

## Ordem de migração restante

1. Concluir a comparação visual assistida em tablet e celular quando o navegador de QA aceitar a emulação de viewport.
2. Executar o conjunto PHP completo no ambiente de CI/EasyPanel.
3. Corrigir somente divergências comprovadas pela comparação, sem substituir dados reais por conteúdo demonstrativo.
4. Manter WhatsApp oficial, gateway, NFS-e, API pública, multiunidade e 2FA bloqueados até suas implementações seguras.

## Auditoria responsiva de 24/08/2026

- landing funcional conferida em desktop: composição, hierarquia, marca MJDev Digital e ausência de rolagem horizontal preservadas;
- breakpoints existentes revisados por código em landing, autenticação, vitrine, acompanhamento e painel;
- tabelas administrativas permanecem dentro de envoltórios com rolagem própria em larguras reduzidas;
- modais administrativos passam para uma coluna abaixo de 620 px;
- menu lateral do painel se converte em gaveta abaixo de 820 px;
- vitrine transforma hero, catálogo, navegação móvel, modal e barra da sacola abaixo de 620 px;
- aplicativos operacionais passam para uma coluna abaixo de 950 px e preservam os controles reais;
- emblema móvel residual `NL` foi corrigido para `MJ` no CSS funcional;
- o navegador integrado manteve viewport fixo durante esta rodada; por isso tablet e celular não foram declarados visualmente aprovados apenas com capturas desktop.
