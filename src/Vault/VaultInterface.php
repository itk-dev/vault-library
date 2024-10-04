<?php

namespace ItkDev\Vault;

use ItkDev\Vault\Model\Secret;
use ItkDev\Vault\Model\Token;

interface VaultInterface
{
    public function login(string $roleId, string $secretId, string $enginePath = 'approle', bool $reset = false): Token;


    public function getSecret(Token $token, string $path, string $secret, array $id, bool $useCache = false, bool $reset = false, int $expire = 0): Secret;

    /**
     *   An array of retrieved secrets.
     */
    public function getSecrets(Token $token, string $path, string $secret, array $ids, bool $useCache = false, bool $reset = false, int $expire = 0): array;
}
