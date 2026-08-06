# Dashboard do Super Administrador

## Objetivo

O Dashboard oferece uma visão operacional da GDA Plataforma sem criar métricas ou integrações externas. Ele reúne dados já registrados no banco e verificações locais do ambiente da aplicação.

## Indicadores e origem dos dados

- Clientes totais, ativos e inativos: tabela `clients`, desconsiderando registros excluídos.
- Módulos totais, em produção e em implantação: tabela `projects`, com status traduzidos somente na apresentação.
- Versão: arquivo `version.json`.
- Último backup: ZIP mais recente em `storage/backups`.
- Última atualização: registro mais recente com status `completed` em `system_updates`.

## Módulos GDA

São exibidos até quatro projetos, priorizando produção e implantação. A quantidade de clientes considera vínculos ativos em `client_projects` e clientes não excluídos. O Dashboard abre somente os detalhes internos do projeto.

## Estados vazios

Na ausência de clientes, backups, atualizações, módulos ou atividades, a interface informa explicitamente que não há registros. O botão de primeiro cadastro aparece somente quando nenhum cliente existe.

## Situação do sistema

As verificações cobrem conexão com o banco, diretórios de backups, cache, logs, temporários, atualizações e arquivos locais de configuração. Nenhum caminho absoluto é exibido. Serviços externos como Coolify, Cloudflare, GitHub e módulos remotos não são inferidos.

## Limitações atuais

- Não há gráficos ou séries históricas.
- Não há verificação de infraestrutura externa.
- Não há métrica de último acesso de clientes.
- O Dashboard não executa ações de escrita.
- A disponibilidade do banco é confirmada pela própria leitura que monta o Dashboard.
