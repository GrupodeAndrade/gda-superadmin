# Fase 2D — Gerenciamento multi-caixa

Status: concluída e incorporada à versão de homologação v0.12.0.

## Modelo

`companies` possui muitos `cycles`. Cada caixa registra empresa, nome, modalidade, data de início, período (`year`/`month`), valor diário, Administração pagante, status, observações e criação. A modalidade implementada é somente `DIARIO`; os demais valores do enum reservam a evolução futura sem lógica financeira nesta fase.

Participantes pertencem à empresa e são associados aos caixas por `cycle_participants`. Essa tabela preserva o cadastro empresarial sem misturar a participação, os pontos ou os movimentos entre caixas.

## Isolamento

O navegador nunca fornece `company_id`. Criação, edição, seleção e arquivamento usam o `company_id` da sessão através de `ClientContext`. Toda seleção de caixa combina `id` e empresa. Aportes, pontos, pagamentos, comprovantes, relatórios e mensagens são filtrados pelo `cycle_id` selecionado.

## Arquivamento

Arquivar muda `cycles.status` para `archived`; nenhum registro financeiro ou relacionamento é apagado. O caixa continua selecionável para consultas. Rotas de escrita operacional retornam conflito enquanto um caixa arquivado estiver selecionado.

Arquivar é a ação correta para qualquer caixa que possua participantes reais, pontos operacionais, aportes, pagamentos, comprovantes ou mensagens. Um caixa arquivado pode ser **reativado** pelo `client_admin`; a reativação apenas devolve o status `active`, preservando integralmente o histórico e liberando novamente as operações.

## Exclusão de caixa vazio

Excluir é reservado a um caixa criado por engano que nunca recebeu movimentação. O backend verifica contribuições, pagamentos, comprovantes, fila de mensagens, participantes reais e pontos além do ponto automático da Administração. Havendo qualquer dependência, a exclusão é recusada com HTTP 409 e o usuário deve arquivar o caixa.

A exclusão permitida ocorre em transação e remove apenas modelos específicos, ponto automático, vínculos do caixa e o próprio caixa. A Administração automática só é removida quando não está vinculada a outro caixa e não possui movimentação. Se o caixa excluído era o selecionado, outro caixa ativo da mesma empresa é selecionado; na ausência dele, `current_cycle_id` é limpo.

## Modalidades futuras

O enum está preparado para `SEMANAL`, `QUINZENAL`, `MENSAL` e `PERSONALIZADO`, mas o formulário aceita apenas `DIARIO`. Implementações futuras deverão introduzir estratégias próprias de calendário e vencimento sem condicionar a regra diária existente em páginas ou controllers.

## Backup

O backup existente continua delimitado por `cycle_id` e não foi alterado nesta fase.
