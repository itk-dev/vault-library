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

    public function isExpired(int $tokenGracePeriod = 60): bool
    {
        return $this->expiresAt->sub(new \DateInterval('PT' . $tokenGracePeriod .'S')) < new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
    }

}
