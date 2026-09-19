<?php

namespace App\Enums;

enum EventTypeSlug: string
{
    case CriacaoPedido = 'criacao_pedido';
    case MudancaStatus = 'mudanca_status';
    case AlteracaoResponsavel = 'alteracao_responsavel';
    case AlteracaoPrioridade = 'alteracao_prioridade';
    case AlteracaoPrevisao = 'alteracao_previsao';
    case Cancelamento = 'cancelamento';
    case Entrega = 'entrega';
}
