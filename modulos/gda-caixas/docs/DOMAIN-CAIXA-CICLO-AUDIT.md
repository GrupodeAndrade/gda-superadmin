# Auditoria de domínio: Caixa versus Ciclo

## Situação atual

A tabela `cycles` representa hoje o **caixa operacional inteiro** apresentado ao usuário. Cada registro reúne identidade (`name`), empresa (`company_id`), modalidade, início, competência (`year` e `month`), valor diário, regra de pagamento da Administração, estado e observações.

Portanto, na implementação 1.0.0, a classe `Cycle` e a tabela `cycles` são o Caixa do ponto de vista funcional. “Ciclo” é um nome técnico herdado, não uma entidade temporal separada.

## Dependências por `cycle_id`

- `cycle_participants`: participantes vinculados e credenciais do portal;
- `cycle_points`: distribuição dos pontos por dia;
- `contributions`: aportes recebidos;
- `payouts`: pagamentos realizados ou pendentes;
- `message_templates`: modelos opcionais por caixa;
- `message_queue`: fila e histórico de mensagens;
- `payment_receipts`: comprovantes enviados e sua análise.

Participantes pertencem à empresa e são associados aos caixas por `cycle_participants`. Usuários e configurações também pertencem à empresa.

## Caixa com múltiplos ciclos

Conceitualmente, um Caixa duradouro poderá possuir vários ciclos ou competências. Essa separação será útil quando o mesmo acordo entre participantes continuar por períodos sucessivos, preservando uma identidade única de Caixa enquanto cada período recebe calendário, pontos, aportes e pagamentos próprios.

Hoje isso não existe: criar outro período significa criar outro registro em `cycles`, tratado como outro caixa.

## Modalidades semanal, quinzenal e mensal

As modalidades já aparecem no enum e na configuração do registro, mas compartilham o mesmo modelo diário. Antes de ativá-las funcionalmente será necessário definir:

- duração e unidade temporal de cada ciclo;
- geração e ordenação de pontos;
- vencimentos e proporcionalidade dos aportes;
- transição entre competências;
- relatórios acumulados do Caixa e relatórios de um Ciclo;
- comportamento de participantes que entram ou saem entre ciclos.

A separação Caixa/Ciclo tende a reduzir condicionais por modalidade, pois o Caixa concentra identidade e membros, enquanto o Ciclo concentra calendário e movimentação.

## Recomendação

Separar as entidades apenas antes da implementação funcional da segunda modalidade ou quando surgir o requisito confirmado de renovar um mesmo caixa por múltiplas competências. A mudança deve ser uma fase própria, com modelo de dados, migração, compatibilidade de relatórios e testes de reconciliação financeira.

Uma direção possível é introduzir `cashboxes` para identidade, empresa e participantes, mantendo `cycles` para cada período. As tabelas financeiras continuariam ligadas ao ciclo; configurações permanentes e associação de participantes migrariam gradualmente para o caixa.

## Por que não separar agora

- todas as rotas, consultas e serviços atuais tratam `cycles.id` como identificador do caixa;
- sete estruturas persistentes dependem direta ou indiretamente de `cycle_id`;
- migração incorreta pode misturar empresas, duplicar associações ou perder rastreabilidade financeira;
- links do participante são atualmente específicos por caixa/ciclo;
- backup, restauração, mensagens, comprovantes e exclusão de caixa vazio dependem desse limite transacional;
- não há regra de negócio homologada para renovação entre ciclos ou modalidades não diárias.

Por esses motivos, a Fase 0 preserva integralmente o schema e registra a dívida de nomenclatura sem alterar dados ou cálculos.
