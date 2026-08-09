# GDA Plataforma v1.0.0

Primeira atualização oficial da GDA Plataforma.

## Destaques

- O projeto Caixa Diário é substituído por Caixas, com slug gda-caixas, versão 1.0.0 e estado inicial Em implantação.
- Os vínculos de clientes existentes são preservados em client_projects.
- Projetos recebem cards, listagem, detalhes, edição, status, URL pública e URL administrativa.
- Dashboard, Clientes e Configurações passam a apresentar os dados do Caixas.
- Instalações novas já recebem o cadastro oficial do projeto.
- O Dashboard apresenta indicadores agrupados, módulos dinâmicos, situação do sistema e atividades recentes.

## Migração

A migração 20260801_register_gda_caixas atualiza o registro legado no mesmo ID. Quando encontra duplicidade, consolida os vínculos por cliente dentro de uma transação antes de remover somente o registro duplicado. A execução é idempotente.

## Compatibilidade

- Versão mínima para o pacote Update: 0.1.0.
- Requisitos: PHP 8.3+, MySQL 8+ ou MariaDB 10.6+.
- O pacote não modifica config/local.php nem dados de runtime.

## Fora do escopo

Esta versão não implementa SSO, provisionamento, sincronização, atualização remota ou endpoint no módulo Caixas.
