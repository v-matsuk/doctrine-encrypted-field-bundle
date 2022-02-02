<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Doctrine\DBAL\Types;

use VM\DoctrineEncryptedFieldBundle\Encryptor\EncryptorInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EncryptedTextType extends Type
{
    public const TYPE_NAME = 'encrypted_text';
    public const ENCRYPTION_MARKER = '<ENC>';

    private ?EncryptorInterface $encryptor = null;

    private bool $encryptionEnabled = true;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (!$this->encryptionEnabled) {
            return $value;
        }

        if ($value === '' || null === $value) {
            return $value;
        }

        if (mb_substr($value, -mb_strlen(self::ENCRYPTION_MARKER)) !== self::ENCRYPTION_MARKER) {
            return $this->getEncryptor()->encrypt($value) . self::ENCRYPTION_MARKER;
        }

        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === '' || null === $value) {
            return $value;
        }

        if (mb_substr($value, -mb_strlen(self::ENCRYPTION_MARKER)) === self::ENCRYPTION_MARKER) {
            return $this->getEncryptor()->decrypt(mb_substr($value, 0, -mb_strlen(self::ENCRYPTION_MARKER)));
        }

        return $value;
    }

    public function setEncryptor(EncryptorInterface $encryptor): void
    {
        $this->encryptor = $encryptor;
    }

    public function disableEncryption(): void
    {
        $this->encryptionEnabled = false;
    }

    private function getEncryptor(): EncryptorInterface
    {
        if (null === $this->encryptor) {
            throw new \RuntimeException('Encryptor is not available.');
        }

        return $this->encryptor;
    }
}
