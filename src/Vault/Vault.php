<?php

namespace ItkDev\Vault;

use ItkDev\Vault\Model\Secret;
use ItkDev\Vault\Model\Token;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

readonly class Vault implements VaultInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private CacheInterface $cache,
        private string $vaultUrl,
    ) {
    }

    /**
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function login(string $roleId, string $secretId, string $enginePath = 'approle', bool $refreshCache = false): Token
    {
        $cacheKey = 'itkdev_vault_token';
        $token = $this->cache->get($cacheKey);

        if ($refreshCache || is_null($token) || $token->isExpired()) {
            $loginUrl = sprintf('%s/v1/auth/%s/login', $this->vaultUrl, $enginePath);

            $body = $this->streamFactory->createStream(json_encode([
                'role_id' => $roleId,
                'secret_id' => $secretId,
            ]));
            $request = $this->requestFactory->createRequest('POST', $loginUrl)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body);

            $response = $this->httpClient->sendRequest($request);

            $data = json_decode($response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

            $ttl = (int) $data['auth']['lease_duration'];
            $now = new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
            $token = new Token(
                token: $data['auth']['client_token'],
                expiresAt: $now->add(new \DateInterval('PT'.$ttl.'S')),
                renewable: (bool) $data['auth']['renewable'],
                roleName: $data['auth']['metadata']['role_name'],
                numUsesLeft: (int) $data['auth']['num_uses'],
            );

            $this->cache->set($cacheKey, $token, $ttl);
        }

        return $token;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getSecret(Token $token, string $path, string $secret, string $id, bool $useCache = false, bool $refreshCache = false, int $expire = 0): Secret
    {
        $secret = $this->getSecrets(
            token: $token,
            path: $path,
            secret: $secret,
            ids: [$id],
            useCache: $useCache,
            refreshCache: $refreshCache,
            expire: $expire
        );

        return reset($secret);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getSecrets(Token $token, string $path, string $secret, array $ids, bool $useCache = false, bool $refreshCache = false, int $expire = 0): array
    {
        $cacheKey = 'itkdev_vault_secret_'.$secret;
        $data = $this->cache->get($cacheKey);

        if (!$useCache || is_null($data) || $refreshCache) {
            $url = sprintf('%s/v1/%s/data/%s', $this->vaultUrl, $path, $secret);

            $request = $this->requestFactory->createRequest('GET', $url)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer '.$token->token);
            $response = $this->httpClient->sendRequest($request);

            $res = json_decode($response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

            $created = new \DateTimeImmutable($res['data']['metadata']['created_time'], new \DateTimeZone('UTC'));
            $version = $res['data']['metadata']['version'];

            $data = [];
            if (!empty($ids)) {
                $secrets = $res['data']['data'];
                foreach ($ids as $id) {
                    if (isset($secrets[$id])) {
                        $data[$id] = new Secret(
                            id: $id,
                            value: $secrets[$id],
                            version: $version,
                            createdAt: $created
                        );
                    } else {
                        throw new \InvalidArgumentException(sprintf('Secret with ID "%s" not found.', $id));
                    }
                }
            }

            $this->cache->set($cacheKey, $data, $expire);
        }

        return $data;
    }
}
