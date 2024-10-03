<?php

namespace ItkDev\Vault;

interface VaultInterface
{
    public function login(string $roleId, string $secretId, string $enginePath = 'approle', bool $reset = false): Token;


    public function getSecret(Token $token, string $path, string $secret, array $id): Secret;

    /**
     * Retrieves secrets from a given path.
     *
     * @param Token $token
     *   The token used for authentication.
     * @param string $path
     *   The path where secrets are stored.
     * @param string $secret
     *   The specific secret to retrieve.
     * @param array $ids
     *   An array of IDs to identify the secrets.
     *
     * @return array<Secret>
     *   An array of retrieved secrets.
     */
    public function getSecrets(Token $token, string $path, string $secret, array $ids): array;
}
