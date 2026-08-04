# Fase 0 — Fundação interna do GDA Caixas

## Escopo

Fundação da versão `1.0.0` criada exclusivamente em `D:\Documents\GDA Plataforma\modulos\gda-caixas`, a partir da base inventariada. O projeto original e seus pacotes permanecem fora desta árvore.

## Identidade

| Campo | Valor |
|---|---|
| Nome | GDA Caixas |
| Slug/produto | `gda-caixas` |
| Versão | `1.0.0` |
| URL | `https://caixas.gda.app.br` |
| Administração | `https://caixas.gda.app.br/admin-gda` |

## Alterações estruturais

- `app/Modules/CaixaDiario` foi renomeado para `app/Modules/Caixas`;
- `Gda\Modules\CaixaDiario` foi substituído por `Gda\Modules\Caixas` em classes, bootstrap e testes;
- não foram mantidas classes duplicadas ou alias de compatibilidade;
- `Modalidades/Diario` foi criado apenas como fronteira documental para extração futura;
- schema e migrações `001` a `006` foram preservados.

## Linhagem do atualizador

O atualizador aceita somente `product: gda-caixas`. Identificadores legados e quaisquer outros produtos são rejeitados. Backups lógicos desta linhagem usam o formato `gda-caixas-cycle-backup`.

## Referências históricas permitidas

Termos e versões do produto anterior podem permanecer exclusivamente:

- no arquivo `BASELINE-CAIXA-DIARIO-v0.99.json`;
- em documentos históricos importados;
- em notas explícitas que expliquem a origem e a ausência de compatibilidade.

Não são permitidos em código executável, configuração pública, interface ou manifestos.

## Verificação

Resultados obtidos em 1º de agosto de 2026:

- lint: 75 arquivos PHP sem erro;
- autoload: 40 classes únicas carregadas, sem duplicação;
- gestão de caixas: 21 verificações aprovadas;
- isolamento multiempresa: 21 verificações aprovadas;
- Administração Global: 24 verificações aprovadas;
- homologação funcional da Fase 0: 39 verificações aprovadas;
- instalação limpa: schema criado e todas as tabelas obrigatórias confirmadas;
- migrações: 001 a 006 executadas e reexecutadas de forma idempotente;
- legado executável: zero ocorrência e zero dependência do namespace anterior;
- responsividade: login verificado em viewports móvel e desktop, sem estouro dos componentes da aplicação;
- ambiente: MariaDB descartável iniciado em diretório temporário, bancos de teste removidos, processo encerrado e datadir apagado;
- origem: hashes dos pacotes Install, Update e Source novamente confirmados.

O MariaDB local disponível pelo XAMPP era 10.4.32, inferior ao requisito de produção 10.6+. A compatibilidade de SQL e a idempotência foram validadas, mas a certificação específica em 10.6+ continua sendo uma limitação ambiental.

O teste histórico mariadb_migration_test.php depende de um ZIP da Fase 1 que não integra esta cópia. Ele não foi executado porque a Fase 0 proíbe gerar ZIP; seu escopo atual foi coberto diretamente por schema.sql, pelas migrações 001 a 006 e por phase0_functional_test.php.

Nenhuma publicação, integração externa, geração de ZIP, alteração de hospedagem, commit ou push foi realizada.
