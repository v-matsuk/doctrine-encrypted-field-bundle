<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Command\Encryption;

class EncryptDatabaseCommand extends AbstractEncryptionCommand
{
    protected static $defaultName = 'app:database:encrypt';

    protected function getProcessName(): string
    {
        return 'encrypt';
    }

    protected function beforeProcess(): void
    {
        //nothing to do
    }

    protected function processSingleEntity(object $entity, array $properties): void
    {
        // The idea is to force update object even if nothing was changed. We change original property value
        // so Doctrine will think that object was changed and will generate update query. Based EncryptedTextType
        // value in property will be encrypted and stored in DB
        foreach ($properties as $property) {
            if (null !== $this->getPropertyAccessor()->getValue($entity, $property)) {
                $this->getEntityManager()
                    ->getUnitOfWork()
                    ->setOriginalEntityProperty(spl_object_hash($entity), $property, '__fake_value__');
            }
        }
    }
}
