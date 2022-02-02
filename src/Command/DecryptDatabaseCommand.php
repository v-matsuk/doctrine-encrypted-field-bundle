<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Command\Encryption;

use VM\DoctrineEncryptedFieldBundle\Doctrine\DBAL\Types\EncryptedTextType;
use Doctrine\DBAL\Types\Type;

class DecryptDatabaseCommand extends AbstractEncryptionCommand
{
    protected static $defaultName = 'app:database:decrypt';

    protected function getProcessName(): string
    {
        return 'decrypt';
    }

    protected function beforeProcess(): void
    {
        /** @var EncryptedTextType $doctrineType */
        $doctrineType = Type::getType(EncryptedTextType::TYPE_NAME);

        $doctrineType->disableEncryption();
    }

    protected function processSingleEntity(object $entity, array $properties): void
    {
        // The idea is to force update object even if nothing was changed. We change original property value
        // so Doctrine will think that object was changed and will generate update query. Based EncryptedTextType
        // value in property will not be encrypted because of encryptionEnabled = false
        foreach ($properties as $property) {
            if (null !== $this->getPropertyAccessor()->getValue($entity, $property)) {
                $this->getEntityManager()
                    ->getUnitOfWork()
                    ->setOriginalEntityProperty(spl_object_hash($entity), $property, '__fake_value__');
            }
        }
    }
}
