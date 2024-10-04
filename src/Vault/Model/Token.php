<?php

namespace ItkDev\Vault\Model;

final class Token
{
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly bool $renewable,
        public readonly string $roleName,
        private int $numUsesLeft,
    ) {
    }

    /**
     * Decrements the number of uses left.
     */
    public function used(): void
    {
        --$this->numUsesLeft;
    }

    /**
     * Retrieves the number of uses left for the token.
     *
     * @return int
     *   The number of uses remaining
     */
    public function usesLeft(): int
    {
        return $this->numUsesLeft;
    }

    /**
     * Determines if the token is expired based on the provided grace period.
     *
     * @param int $tokenGracePeriod
     *   The grace period in seconds added to the token's expiration time. Defaults to 60 seconds.
     *
     * @return bool
     *   Returns true if the token is expired, false otherwise
     *
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    public function isExpired(int $tokenGracePeriod = 60): bool
    {
        return $this->expiresAt->sub(new \DateInterval('PT'.$tokenGracePeriod.'S')) < new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
    }
}
