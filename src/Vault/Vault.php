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

        if ($refreshCache || !$token instanceof Token || $token->isExpired()) {
            $loginUrl = sprintf('%s/v1/auth/%s/login', $this->vaultUrl, $enginePath);

            $body = $this->streamFactory->createStream(json_encode([
                'role_id' => $roleId,
                'secret_id' => $secretId,
            ], JSON_THROW_ON_ERROR));

            $request = $this->requestFactory->createRequest('POST', $loginUrl)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body);

            try {
                $response = $this->httpClient->sendRequest($request);
                /** @var array<string, mixed> $data */
                $data = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (ClientExceptionInterface $e) {
                throw new VaultException(sprintf('Vault login failed: %s', $e->getMessage()), previous: $e);
            } catch (\JsonException $e) {
                throw new VaultException(sprintf('Vault data decode failed: %s', $e->getMessage()), previous: $e);
            }

            if (isset($data['errors'])) {
                /** @var array<int, string> $errors */
                $errors = $data['errors'];
                throw new VaultException(sprintf('Vault login failed: %s', reset($errors)));
            }

            /** @var array{auth: array{lease_duration: int, client_token: string, renewable: bool, metadata: array{role_name: string}, num_uses: int}} $data */
            $ttl = (int) $data['auth']['lease_duration'];
            $now = new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
            $token = new Token(
                token: $data['auth']['client_token'],
                expiresAt: $now->add(new \DateInterval('PT'.$ttl.'S')),
                renewable: $data['auth']['renewable'],
                roleName: $data['auth']['metadata']['role_name'],
                numUsesLeft: $data['auth']['num_uses'],
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
        $secrets = $this->getSecrets(
            token: $token,
            path: $path,
            secret: $secret,
            keys: [$key],
            version: $version,
            useCache: $useCache,
            refreshCache: $refreshCache,
            expire: $expire
        );

        return $secrets[$key];
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string, Secret>
     *
     * @throws VaultException
     * @throws UnknownErrorException
     * @throws \DateMalformedStringException
     * @throws InvalidArgumentException
     */
    public function getSecrets(Token $token, string $path, string $secret, array $keys, ?int $version = null, bool $useCache = false, bool $refreshCache = false, int $expire = 0): array
    {
        $cacheKey = 'itkdev_vault_secret_'.$path.'_'.$secret.'_'.implode('_', $keys).($version ?? 0);
        $data = $this->cache->get($cacheKey);

        if (!$useCache || !is_array($data) || $refreshCache) {
            $url = sprintf('%s/v1/%s/data/%s', $this->vaultUrl, $path, $secret);
            if (!is_null($version)) {
                $url .= '?version='.$version;
            }

            $request = $this->requestFactory->createRequest('GET', $url)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Bearer '.$token->token);

            try {
                $response = $this->httpClient->sendRequest($request);
                /** @var array<string, mixed> $res */
                $res = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (ClientExceptionInterface $e) {
                throw new VaultException(sprintf('Vault fetch failed: %s', $e->getMessage()), previous: $e);
            } catch (\JsonException $e) {
                throw new VaultException(sprintf('Vault data decode failed: %s', $e->getMessage()), previous: $e);
            }

            if (isset($res['errors'])) {
                /** @var array<int, string> $errors */
                $errors = $res['errors'];
                // If secret is not found an empty error array is returned.
                if (empty($errors)) {
                    throw new UnknownErrorException('Unknown error.');
                }
                preg_match('/.*:\n\t\* (.+)\n\n$/', (string) reset($errors), $matches);
                throw new VaultException(sprintf('Vault failed: %s', $matches[1] ?? ''));
            }

            /** @var array{data: array{data: array<string, string>, metadata: array{created_time: string, version: string}}} $res */
            $created = new \DateTimeImmutable($res['data']['metadata']['created_time'], new \DateTimeZone('UTC'));
            $secretVersion = (string) $res['data']['metadata']['version'];
            $data = [];
            if (!empty($keys)) {
                $secrets = $res['data']['data'];
                foreach ($keys as $key) {
                    if (isset($secrets[$key])) {
                        $data[$key] = new Secret(
                            key: $key,
                            value: $secrets[$key],
                            version: $secretVersion,
                            createdAt: $created
                        );
                    } else {
                        throw new NotFoundException(sprintf('Secret with key "%s" not found.', $key));
                    }
                }
            }

            $this->cache->set($cacheKey, $data, $expire);
        }

        /** @var array<string, Secret> $data */
        return $data;
    }
}
