<?php
namespace ANS\Tickets\Domain\ValueObjects;

final class Prioridade
{
    private string $value;

    private const VALID = ['baixa', 'media', 'alta'];

    public function __construct(string $value)
    {
        $clean = strtolower(trim($value));
        if (!in_array($clean, self::VALID, true)) {
            throw new \InvalidArgumentException('Prioridade inválida');
        }
        $this->value = $clean;
    }

    public function value(): string
    {
        return $this->value;
    }
}
