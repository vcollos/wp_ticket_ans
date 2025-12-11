<?php
namespace ANS\Tickets\Domain\ValueObjects;

final class Status
{
    private string $value;

    private const VALID = [
        'aberto',
        'em_triagem',
        'aguardando_informacoes_solicitante',
        'em_analise',
        'em_execucao',
        'aguardando_terceiros',
        'aguardando_aprovacao',
        'solucao_proposta',
        'resolvido',
        'fechado',
        'aguardando_acao',
        'novo',
        'atendimento',
        'financeiro',
        'comercial',
        'assistencial',
        'ouvidoria',
        'concluido',
        'arquivado',
        'pendente_cliente',
    ];

    public function __construct(string $value)
    {
        $clean = strtolower(trim($value));
        if (!in_array($clean, self::VALID, true)) {
            throw new \InvalidArgumentException('Status inválido');
        }
        $this->value = $clean;
    }

    public function value(): string
    {
        return $this->value;
    }
}
