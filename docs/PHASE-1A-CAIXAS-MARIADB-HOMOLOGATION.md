# Fase 1A — Homologação da migração Caixas em MariaDB

Data: 2026-08-01  
Banco: MariaDB 11.4.4, distribuição oficial Windows x64, execução local descartável em `127.0.0.1:3407`.

## Ambiente

O servidor foi inicializado em diretório temporário, sem serviço do Windows e sem credenciais reais. Foram usados bancos independentes: `gda_legacy`, `gda_empty`, `gda_duplicate`, `gda_partial`, `gda_failure`, `gda_installer` e `gda_http`. Os seis primeiros foram removidos automaticamente pela suíte; `gda_http` foi usado para o instalador e removido na limpeza final.

## Ajustes encontrados pela homologação

1. O campo `full_name` havia sido incluído por engano em `users`, não em `projects`. O schema foi corrigido.
2. A primeira consolidação usava `INSERT … SELECT … ON DUPLICATE KEY UPDATE` sobre a própria `client_projects`. MariaDB reportou `Column 'client_projects.status' in UPDATE is ambiguous`. A transação abortou antes de exclusões. A lógica foi substituída por leitura e prepared statements explícitos por vínculo, dentro da mesma transação.

## Resultados

| Cenário | ID antes/depois | Vínculos antes/depois | Resultado |
|---|---:|---:|---|
| Base legada + reexecução | 1 / 1 | 2 / 2 | Registro atualizado no lugar; segunda execução sem mudanças |
| Base vazia | — / 1 | 0 / 0 | Exatamente um `gda-caixas` |
| Duplicidade | legado 1; duplicado 2 | 4 linhas / 3 clientes únicos | Todos consolidados no ID 1; duplicado removido depois da consolidação |
| Estado parcial | 1 / 1 | 1 / 1 | Nome, slug, versão e URLs convergiram; reexecução estável |
| Falha controlada | legado 1; duplicado 2 | 2 / 2 após falha | Rollback preservou ambos os projetos e vínculos; reexecução convergiu ao ID 1 |
| Instalação nova | — / 1 | 0 / 0 | Um único Caixas, `1.0.0`, estado `deploying` |

O instalador HTTP também criou em `gda_http` exatamente um projeto Caixas, um Super Administrador e nenhum slug legado. O cadastro continha URL pública, URL administrativa e categoria corretas.

## Falha controlada e transações

O teste lançou uma exceção depois de copiar vínculos ao ID legado e antes de excluir vínculos ou projeto duplicado. O rollback retornou a duas linhas de vínculo e dois projetos. Após remover a causa, a mesma migração foi executada com sucesso.

As alterações DDL de compatibilidade (`ALTER TABLE`) ocorrem antes da transação de dados porque MariaDB realiza commit implícito em DDL. Elas são idempotentes e podem permanecer após uma falha. Renomeação do slug, consolidação e exclusões usam InnoDB e permanecem na transação.

## Aplicação e interface

- Lint PHP 8.3: 52 arquivos aprovados.
- Autorização: cinco casos aprovados.
- CSRF: token válido aceito; inválido e ausente rejeitados.
- Suítes estruturais gerais e do módulo: aprovadas.
- Instalador HTTP: banco e cadastro confirmados no MariaDB.
- Navegação autenticada e responsividade visual completa: não executadas; o navegador local bloqueou o envio/navegação pela política de URL antes da continuação. Nenhum resultado visual foi declarado como aprovado.

## Conclusão

A migração de dados do módulo Caixas está homologada no MariaDB 11.4.4 para os cenários legado, vazio, duplicado, parcial, falha e reexecução. A preservação real de `client_projects` foi comprovada. A homologação visual/HTTP autenticada permanece pendente em ambiente de navegador que permita a aplicação local.

