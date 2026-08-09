# Changelog

## v1.0.0 — Primeira atualização oficial

- refinada a visão geral do Dashboard com indicadores agrupados, módulos dinâmicos, saúde do sistema e atividades recentes;
- preservados o ID do Caixas e todos os vínculos existentes em `client_projects` durante a migração;

- substituído o cadastro legado Caixa Diário pelo projeto Caixas;
- preservados IDs e vínculos existentes em client_projects;
- adicionados nome completo, URL administrativa e categoria ao catálogo de projetos;
- adicionados cards de projeto com estado de implantação e ação administrativa;
- criadas listagem, detalhes e edição de Projetos GDA;
- atualizado o Dashboard com indicadores e destaque do Caixas;
- atualizado o cadastro de clientes para gerenciar projetos liberados;
- adicionadas URLs do Caixas às Configurações;
- homologada a migração idempotente em MariaDB para bases legada, vazia, duplicada, parcial e com falha controlada;
- mantidas autorização exclusiva de Super Administrador e proteção CSRF;
- atualizada a documentação operacional e de arquitetura.

Não foram adicionados SSO, provisionamento de clientes, sincronização de usuários ou atualização remota de módulos.
