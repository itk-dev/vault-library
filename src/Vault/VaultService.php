<?php

namespace Vault;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

readonly class VaultService implements VaultServiceInterface
{
    public function __construct(
        private ClientInterface         $httpClient,
        private RequestFactoryInterface $requestFactory,
        private string                  $vaultUrl,
    ) {}

    public function login(string $roleId, string $secretId, string $enginePath = 'approle'): Token
    {
        $loginUrl = sprintf('%s/v1/auth/%s/login', $this->vaultUrl, $enginePath);

        $body = $this->httpClient->createStream(json_encode([
            'role_id' => $roleId,
            'secret_id' => $secretId,
        ]));
        $request = $this->requestFactory->createRequest('POST', $loginUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
        $response = $this->httpClient->sendRequest($request);

        $data = json_decode($response->getBody(), associative:  true, flags: JSON_THROW_ON_ERROR);

        // @TODO: should we have support for timezone.
        $now = new DateTimeImmutable(timezone: new DateTimeZone('UTC'));

        return new Token(
            token: $data['auth']['client_token'],
            expiresAt: $now->add(new DateInterval('PT' . (int) $data['auth']['lease_duration'] . 'S')),
            renewable: (bool) $data['auth']['renewable'],
            roleName: $data['auth']['metadata']['role_name'],
            numUsesLeft: (int) $data['auth']['num_uses'],
        );
    }

    public function getSecrets(Token $token, string $path, string $secret, array $ids): array
    {
        $url = sprintf('%s/v1/%s/data/%s', $this->vaultUrl, $path, $secret);

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token->token);
        $response = $this->httpClient->sendRequest($request);

        $res = json_decode($response->getBody(), associative:  true, flags: JSON_THROW_ON_ERROR);

        $data = [];
        if (!empty($ids)) {
            $secrets = $res['data']['data'];
            foreach ($ids as $id) {
                if (isset($secrets[$id])) {
                   $data[$id] = $secrets[$id];
                } else {
                    throw new \InvalidArgumentException(sprintf('Secret with ID "%s" not found.', $id));
                }
            }
        }

        return $data;
    }
}
