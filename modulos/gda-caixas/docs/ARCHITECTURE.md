# Arquitetura do GDA Caixas

Versão de referência: **1.0.0**.

## Camadas

- `Gda\Core`: banco, HTTP, sessão, segurança, autenticação e migrações.
- `Gda\AdminGlobal`: administração transversal, usuários globais, auditoria, diagnóstico e atualização.
- `Gda\ClientArea`: contexto obrigatório da empresa autenticada.
- `Gda\Modules\Caixas`: serviços, repositórios, modelos e políticas do domínio de caixas.

O autoload transforma classes com prefixo `Gda\` em caminhos sob `app/`. Não há mapeamento ou classe dependente do namespace legado.

## Modalidades

`app/Modules/Caixas/Modalidades/Diario` é a fronteira reservada para extração progressiva das regras diárias. A versão 1.0.0 mantém a lógica homologada no módulo principal para evitar duplicação e mudança financeira prematura.

## Persistência

O schema existente e as migrações `001` a `006` são preservados. `company_id` isola empresas, enquanto `cycle_id` delimita o caixa operacional atual. A decisão futura de separar os conceitos Caixa e Ciclo está documentada em `DOMAIN-CAIXA-CICLO-AUDIT.md`.

## Segurança

As sessões cliente e global possuem escopos separados. Repositórios de cliente recebem `ClientContext`; links de participante armazenam apenas hash do token; atualizações aceitam somente o produto `gda-caixas`.
