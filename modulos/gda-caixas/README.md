# GDA Caixas

Versão inicial: **1.0.0**

O GDA Caixas é o módulo multiempresa da GDA Plataforma para administração de caixas, participantes, pontos, aportes, pagamentos, mensagens, comprovantes e portais individuais.

- Produto interno: `gda-caixas`
- Aplicação: `https://caixas.gda.app.br`
- Administração Global: `https://caixas.gda.app.br/admin-gda`
- Requisitos: PHP 8.3, MariaDB 10.6+, Apache com `mod_rewrite` e extensões `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `session` e `zip`

## Instalação

1. Publique o conteúdo na raiz do subdomínio.
2. Copie `config/local.example.php` para `config/local.php`.
3. Configure banco, URL e o token temporário de ativação global.
4. Acesse `index.php?page=install` para criar a empresa, o primeiro caixa e o administrador do cliente.
5. Ative o primeiro administrador global em `/admin-gda/ativar` e remova o token temporário.

## Estrutura

- `app/AdminGlobal`: Administração Global isolada.
- `app/ClientArea`: contexto autenticado de cada empresa.
- `app/Core`: infraestrutura compartilhada.
- `app/Modules/Caixas`: domínio do módulo.
- `app/Modules/Caixas/Modalidades/Diario`: ponto de extração progressiva da modalidade diária.
- `database`: schema homologado e migrações `001` a `006`.
- `tests`: testes de domínio, administração e isolamento.

## Origem histórica

A fundação foi importada do código final do projeto anterior, preservado e inventariado em `BASELINE-CAIXA-DIARIO-v0.99.json`. Essa referência existe somente para rastreabilidade; o GDA Caixas é uma nova linhagem e não aceita pacotes de atualização do produto anterior.

Credenciais, `config/local.php`, dados de runtime e bancos locais não fazem parte do código distribuído.
