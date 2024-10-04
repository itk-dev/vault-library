<?php

namespace ItkDev\Vault;

final class Secret
{
    public function __construct(
        public string $id,
        public string $value,
        public string $version,
        public \DateTimeImmutable $createdAt,
    )
    {

    }

}
