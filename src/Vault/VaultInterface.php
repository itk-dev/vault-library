<?php

namespace ItkDev\Vault;

use ItkDev\Vault\Exception\VaultException;
use ItkDev\Vault\Model\Secret;
use ItkDev\Vault\Model\Token;

interface VaultInterface
{
    /**
     * Get a token from the vault using the provided roleId and secretId.
     *
     * @param string $roleId
     *   Role ID for authentication
     * @param string $secretId
     *   Secret ID for authentication
     * @param string $enginePath
     *   The path to the authentication engine. Default is 'approle'.
     * @param bool $refreshCache
     *   By-pass cache and refresh the cache content. Default is false.
     *
     * @return Token
     *   Token that can be used to communicate with the vault
     *
     * @throws VaultException
     */
    public function login(string $roleId, string $secretId, string $enginePath = 'approle', bool $refreshCache = false): Token;

    /**
     * Retrieve a secret from the specified secret engine path.
     *
     * @param Token $token
     *   Authentication token required to access the secrets
     * @param string $path
     *   The path from which to retrieve the secrets
     * @param string $secret
     *   The type of secret being requested
     * @param string $key
     *   An id specifying which secret to receive
     * @param int|null $version
     *   The version of the secret
     * @param bool $useCache
     *   Optional parameter to indicate whether to use cached secrets. Defaults to false.
     * @param bool $refreshCache
     *   Optional parameter to indicate whether to refresh the cache. Defaults to false.
     * @param int $expire
     *   Optional parameter specifying cache expiration time in seconds. Defaults to 0.
     *
     * @return Secret
     *   The secret found
     *
     * @throws VaultException
     */
    public function getSecret(Token $token, string $path, string $secret, string $key, ?int $version = null, bool $useCache = false, bool $refreshCache = false, int $expire = 0): Secret;

    /**
     * Retrieves secrets from the specified secret engine path.
     *
     * @param Token $token
     *   Authentication token required to access the secrets
     * @param string $path
     *   The path from which to retrieve the secrets
     * @param string $secret
     *   The type of secret being requested
     * @param array<string> $keys
     *   An array of identifiers specifying which secrets to retrieve
     * @param int|null $version
     *   The version of the secrets
     * @param bool $useCache
     *   Optional parameter to indicate whether to use cached secrets. Defaults to false.
     * @param bool $refreshCache
     *   Optional parameter to indicate whether to refresh the cache. Defaults to false.
     * @param int $expire
     *   Optional parameter specifying cache expiration time in seconds. Defaults to 0.
     *
     * @return array
     *   An array containing the requested secrets
     *
     * @throws VaultException
     */
    public function getSecrets(Token $token, string $path, string $secret, array $keys, ?int $version = null, bool $useCache = false, bool $refreshCache = false, int $expire = 0): array;
}
