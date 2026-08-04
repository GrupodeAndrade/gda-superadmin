# Módulo Caixas

## Identificação

- Nome comercial: **Caixas**
- Nome completo: **GDA Caixas**
- Identificador interno: `gda-caixas`
- Categoria: Gestão financeira
- Versão inicial planejada: `1.0.0`
- Status inicial: Em implantação
- URL pública: `https://caixas.gda.app.br`
- URL administrativa: `https://caixas.gda.app.br/admin-gda`

## Relação com clientes

O módulo utiliza o relacionamento existente `client_projects`. Cada cliente permanece cadastrado uma única vez e pode possuir Caixas e outros módulos simultaneamente. Marcar Caixas no formulário do cliente apenas cria ou reativa esse relacionamento; não provisiona dados no subdomínio do módulo.

## Substituição do legado

Caixas substitui o projeto legado Caixa Diário. A migração `20260801_register_gda_caixas` procura o registro legado e o atualiza no lugar, preservando seu `id` e, consequentemente, todos os vínculos existentes em `client_projects`. Se uma duplicidade anterior já existir, os vínculos são consolidados antes de remover somente o registro duplicado.

A migração é registrada na tabela `migrations`, pode ser reexecutada sem duplicar o módulo e também cria uma instalação nova quando nenhum registro existe.

## Integração atual

O botão **Administrar** abre a URL administrativa em outra aba. A indisponibilidade do subdomínio não interrompe nem produz erro técnico na GDA Plataforma, pois esta fase não realiza chamadas remotas ou health checks.

## Integração futura

SSO, entrada integrada do Super Administrador, provisionamento, API, HMAC, planos, licenças, modalidades, saúde do módulo e atualização remota estão fora desta fase. Esses itens exigirão contrato de segurança e autorização próprio antes da implementação.

