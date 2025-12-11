<?php
namespace ANS\Tickets\Domain\ValueObjects;

final class DepartamentoId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('DepartamentoId inválido');
        }
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
