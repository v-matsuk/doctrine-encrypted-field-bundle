<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Encryptor;

interface EncryptorInterface
{
    public function encrypt(string $data): string;

    public function decrypt(string $data): string;
}
