<?php

namespace Vault;

interface VaultServiceInterface
{
    public function login(string $roleId, string $secretId): Token;

    public function getSecrets(Token $token, string $path, string $secret, array $ids);
}
