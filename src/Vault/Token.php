<?php

namespace ItkDev\Vault;

final class Token
{
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly bool $renewable,
        public readonly string $roleName,
        private int $numUsesLeft,
    ) {}

    public function used(): void
    {
        $this->numUsesLeft--;
    }

    public function usesLeft(): int
    {
        return $this->numUsesLeft;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

}
