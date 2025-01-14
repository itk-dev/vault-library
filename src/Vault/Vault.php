<?php

namespace ItkDev\Vault;

use ItkDev\Vault\Exception\NotFoundException;
use ItkDev\Vault\Exception\UnknownErrorException;
use ItkDev\Vault\Exception\VaultException;
use ItkDev\Vault\Model\Secret;
use ItkDev\Vault\Model\Token;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

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
     * @throws VaultException
     * @throws \DateMalformedStringException
     * @throws InvalidArgumentException
     * @throws \DateMalformedIntervalStringException
     */
    public function login(string $roleId, string $secretId, string $enginePath = 'approle', bool $refreshCache = false): Token
    {
        $cacheKey = 'itkdev_vault_token'.$roleId;
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

            try {
                $response = $this->httpClient->sendRequest($request);
                $data = json_decode($response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (ClientExceptionInterface $e) {
                throw new VaultException(sprintf('Vault login failed: %s', $e->getMessage()), previous: $e);
            } catch (\JsonException $e) {
                throw new VaultException(sprintf('Vault data decode failed: %s', $e->getMessage()), previous: $e);
            }

            if (isset($data['errors'])) {
                throw new VaultException(sprintf('Vault login failed: %s', reset($data['errors'])));
            }

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
     * @throws VaultException
     * @throws \DateMalformedStringException
     * @throws InvalidArgumentException
     * @throws UnknownErrorException
     */
    public function getSecret(Token $token, string $path, string $secret, string $key, ?int $version = null, bool $useCache = false, bool $refreshCache = false, int $expire = 0): Secret
    {
        $secret = $this->getSecrets(
            token: $token,
            path: $path,
            secret: $secret,
            keys: [$key],
            version: $version,
            useCache: $useCache,
            refreshCache: $refreshCache,
            expire: $expire
        );

        return reset($secret);
    }

    /**
     * @throws VaultException
     * @throws UnknownErrorException
     * @throws \DateMalformedStringException
     * @throws InvalidArgumentException
     */
    public function getSecrets(Token $token, string $path, string $secret, array $keys, ?int $version = null, bool $useCache = false, bool $refreshCache = false, int $expire = 0): array
    {
        $cacheKey = 'itkdev_vault_secret_'.$path.'_'.$secret.'_'.implode('_', $keys).($version ?? 0);
        $data = $this->cache->get($cacheKey);

        if (!$useCache || is_null($data) || $refreshCache) {
            $url = sprintf('%s/v1/%s/data/%s', $this->vaultUrl, $path, $secret);
            if (!is_null($version)) {
                $url .= '?version='.$version;
            }

            $request = $this->requestFactory->createRequest('GET', $url)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer '.$token->token);

            try {
                $response = $this->httpClient->sendRequest($request);
                $res = json_decode($response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (ClientExceptionInterface $e) {
                throw new VaultException(sprintf('Vault fetch failed: %s', $e->getMessage()), previous: $e);
            } catch (\JsonException $e) {
                throw new VaultException(sprintf('Vault data decode failed: %s', $e->getMessage()), previous: $e);
            }

            if (isset($res['errors'])) {
                // If secret is not found an empty error array is returned.
                if (empty($res['errors'])) {
                    throw new UnknownErrorException('Unknown error.');
                }
                preg_match('/.*:\n\t\* (.+)\n\n$/', reset($res['errors']), $matches);
                throw new VaultException(sprintf('Vault failed: %s', $matches[1] ?? ''));
            }

            $created = new \DateTimeImmutable($res['data']['metadata']['created_time'], new \DateTimeZone('UTC'));
            $version = $res['data']['metadata']['version'];
            $data = [];
            if (!empty($keys)) {
                $secrets = $res['data']['data'];
                foreach ($keys as $key) {
                    if (isset($secrets[$key])) {
                        $data[$key] = new Secret(
                            key: $key,
                            value: $secrets[$key],
                            version: $version,
                            createdAt: $created
                        );
                    } else {
                        throw new NotFoundException(sprintf('Secret with key "%s" not found.', $key));
                    }
                }
            }

            $this->cache->set($cacheKey, $data, $expire);
        }

        return $data;
    }
}
