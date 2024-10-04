<?php

namespace ItkDev\Vault\Tests;

use ItkDev\Vault\Model\Token;
use ItkDev\Vault\Vault;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Vault::class)]
#[UsesClass(Token::class)]
class VaultTest extends TestCase
{
    private string $vaultUrl = 'http://test-url.com';
    private Token $token;

    protected function setUp(): void
    {
    }

    public function testLogin()
    {
        $ttl = 3600;
        $now = new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
        $token = new Token(
            token: 'hvs.CAESIO5SjAQ5ggMi7HxpZUm5TZeJWZQ5i9425SMFZ',
            expiresAt: $now->add(new \DateInterval('PT'.$ttl.'S')),
            renewable: true,
            roleName: 'test-role-name',
            numUsesLeft: 2,
        );

        $expectedBody = [
            'request_id' => 'ab2d8d74-21bc-a5c4-3986-36c44c87cde9',
            'auth' => [
                'client_token' => $token->token,
                'metadata' => [
                    'role_name' => $token->roleName,
                ],
                'lease_duration' => $ttl,
                'renewable' => $token->renewable,
                'num_uses' => $token->usesLeft(),
            ],
        ];
        $data = [
            'role_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'secret_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ];

        $mockCache = $this->createMock(CacheInterface::class);

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockClient = $this->createMock(ClientInterface::class);
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $mockStreamFactory = $this->createMock(StreamFactoryInterface::class);
        $mockStream = $this->createMock(StreamInterface::class);
        $mockResponseBodyStream = $this->createMock(StreamInterface::class);

        $mockStreamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode($data))
            ->willReturn($mockStream);

        $mockRequestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $this->vaultUrl.'/v1/auth/approle/login')
            ->willReturn($mockRequest);

        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $mockRequest->expects($this->once())
            ->method('withBody')
            ->with($mockStream)
            ->willReturnSelf();

        $mockResponseBodyStream->method('__toString')
            ->willReturn(json_encode($expectedBody));

        $mockResponse->method('getStatusCode')
            ->willReturn(201);
        $mockResponse->method('getBody')
            ->willReturn($mockResponseBodyStream);

        $mockClient->expects($this->once())
            ->method('sendRequest')
            ->with($mockRequest)
            ->willReturn($mockResponse);

        $vault = new Vault($mockClient, $mockRequestFactory, $mockStreamFactory, $mockCache, $this->vaultUrl);
        $resToken = $vault->login($data['role_id'], $data['secret_id']);
        $this->assertInstanceOf(Token::class, $resToken);
        $this->assertEquals($token->token, $resToken->token);
    }
}
