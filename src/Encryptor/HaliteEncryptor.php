<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Encryptor;

use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;

class HaliteEncryptor implements EncryptorInterface
{
    private string $encryptionKeyFile;

    private ?EncryptionKey $encryptionKey = null;

    public function __construct(string $keyFile)
    {
        $this->encryptionKeyFile = $keyFile;
    }

    public function encrypt(string $data): string
    {
        return Crypto::encrypt(new HiddenString($data), $this->getKey());
    }

    public function decrypt(string $data): string
    {
        return Crypto::decrypt($data, $this->getKey())->getString();
    }

    private function getKey(): EncryptionKey
    {
        if (null === $this->encryptionKey) {
            try {
                $this->encryptionKey = KeyFactory::loadEncryptionKey($this->encryptionKeyFile);
            } catch (CannotPerformOperation $e) {
                $this->encryptionKey = KeyFactory::generateEncryptionKey();
                KeyFactory::save($this->encryptionKey, $this->encryptionKeyFile);
            }
        }

        return $this->encryptionKey;
    }
}
