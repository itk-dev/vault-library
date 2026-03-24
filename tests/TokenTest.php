<?php

namespace ItkDev\Vault\Tests;

use ItkDev\Vault\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Token::class)]
class TokenTest extends TestCase
{
    public function testIsExpiredReturnsTrueForExpiredToken(): void
    {
        $token = new Token(
            token: 'test-token',
            expiresAt: new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')),
            renewable: false,
            roleName: 'test',
            numUsesLeft: 0,
        );

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsFalseForValidToken(): void
    {
        $token = new Token(
            token: 'test-token',
            expiresAt: new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')),
            renewable: false,
            roleName: 'test',
            numUsesLeft: 0,
        );

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredRespectsGracePeriod(): void
    {
        // Token expires in 30 seconds — not expired with 0 grace, but expired with 60s grace (default)
        $token = new Token(
            token: 'test-token',
            expiresAt: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->add(new \DateInterval('PT30S')),
            renewable: false,
            roleName: 'test',
            numUsesLeft: 0,
        );

        $this->assertTrue($token->isExpired(60));
        $this->assertFalse($token->isExpired(0));
    }

    public function testUsedDecrementsCounter(): void
    {
        $token = new Token(
            token: 'test-token',
            expiresAt: new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')),
            renewable: false,
            roleName: 'test',
            numUsesLeft: 3,
        );

        $this->assertSame(3, $token->usesLeft());

        $token->used();
        $this->assertSame(2, $token->usesLeft());

        $token->used();
        $this->assertSame(1, $token->usesLeft());
    }
}
