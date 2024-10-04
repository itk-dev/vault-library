<?php

namespace ItkDev\Vault\Tests;

use ItkDev\Vault\Model\Secret;
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
#[UsesClass(Secret::class)]
class VaultTest extends TestCase
{
    private string $vaultUrl = 'http://test-url.com';
    private Token $token;
    private CacheInterface $cacheMock;

    protected function setUp(): void
    {
        $this->setupInMemoryCache();
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
            'request_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
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

        $vault = new Vault(
            httpClient: $mockClient,
            requestFactory: $mockRequestFactory,
            streamFactory: $mockStreamFactory,
            cache: $this->cacheMock,
            vaultUrl: $this->vaultUrl,
        );

        $resToken = $vault->login($data['role_id'], $data['secret_id']);

        // Test token data.
        $this->assertInstanceOf(Token::class, $resToken);
        $this->assertEquals($token->token, $resToken->token);

        // Call login once more to test that cache work. If not, it should
        // trigger all the "once" expects above.
        $resToken2 = $vault->login($data['role_id'], $data['secret_id']);

        // Test that the same token is returned.
        $this->assertEquals($resToken, $resToken2);
    }

    public function testGetSecret(): void
    {
        $path = 'path';
        $secretName = 'secretName';
        $id = 'uniqueId';
        $token = new Token(
            token: 'hvs.CAESIO5SjAQ5ggMi7HxpZUm5TZeJWZQ5i9425SMFZ',
            expiresAt: (new \DateTimeImmutable())->add(new \DateInterval('PT300S')),
            renewable: false,
            roleName: 'test',
            numUsesLeft: 0,
        );

        $mockRequest = $this->createMock(RequestInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockClient = $this->createMock(ClientInterface::class);
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);

        $responseBody = [
            'data' => [
                'data' => [
                    $id => 'secretValue',
                ],
                'metadata' => [
                    'created_time' => '2022-02-16T20:46:22.151178411Z',
                    'version' => 1,
                ],
            ],
        ];

        $mockResponseBodyStream = $this->createMock(StreamInterface::class);
        $mockResponseBodyStream->method('__toString')->willReturn(json_encode($responseBody));

        $expectedSecret = new Secret(
            id: $id,
            value: 'secretValue',
            version: '1',
            createdAt: new \DateTimeImmutable('2022-02-16T20:46:22.151178411Z', new \DateTimeZone('UTC')),
        );

        $mockRequestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', $this->vaultUrl.'/v1/'.$path.'/data/'.$secretName)
            ->willReturn($mockRequest);

        $mockRequest->method('withHeader')
            ->willReturnCallback(function ($name, $value) use (&$sequence, $mockRequest, $token) {
                if (0 === $sequence) {
                    $this->assertSame('Content-Type', $name);
                    $this->assertSame('application/json', $value);
                } elseif (1 === $sequence) {
                    $this->assertSame('Authorization', $name);
                    $this->assertSame('Bearer '.$token->token, $value);
                }
                ++$sequence;

                return $mockRequest;
            }
            );

        $mockClient->expects($this->once())
            ->method('sendRequest')
            ->with($mockRequest)
            ->willReturn($mockResponse);

        $mockResponse->method('getBody')
            ->willReturn($mockResponseBodyStream);

        $vault = new Vault(
            httpClient: $mockClient,
            requestFactory: $mockRequestFactory,
            streamFactory: $this->createMock(StreamFactoryInterface::class),
            cache: $this->cacheMock,
            vaultUrl: $this->vaultUrl,
        );

        $secret = $vault->getSecret($token, $path, $secretName, $id);

        $this->assertEquals($expectedSecret, $secret);
    }

    /**
     * Sets up an in-memory cache.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    private function setupInMemoryCache(): void
    {
        include_once __DIR__.'/InMemoryCache.php';

        // Create the in-memory cache
        $cache = new InMemoryCache();

        // Mock the CacheInterface to return the in-memory cache
        $this->cacheMock = $this->createMock(CacheInterface::class);

        // Map the CacheInterface methods to the InMemoryCache methods
        $this->cacheMock->method('get')->willReturnCallback([$cache, 'get']);
        $this->cacheMock->method('set')->willReturnCallback([$cache, 'set']);
        $this->cacheMock->method('delete')->willReturnCallback([$cache, 'delete']);
        $this->cacheMock->method('clear')->willReturnCallback([$cache, 'clear']);
        $this->cacheMock->method('getMultiple')->willReturnCallback([$cache, 'getMultiple']);
        $this->cacheMock->method('setMultiple')->willReturnCallback([$cache, 'setMultiple']);
        $this->cacheMock->method('deleteMultiple')->willReturnCallback([$cache, 'deleteMultiple']);
        $this->cacheMock->method('has')->willReturnCallback([$cache, 'has']);
    }
}
