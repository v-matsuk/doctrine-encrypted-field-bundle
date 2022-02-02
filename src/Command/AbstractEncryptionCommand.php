<?php

declare(strict_types=1);

namespace VM\DoctrineEncryptedFieldBundle\Command\Encryption;

use VM\DoctrineEncryptedFieldBundle\Doctrine\DBAL\Types\EncryptedTextType;
use VM\DoctrineEncryptedFieldBundle\EventListener\ElasticSearchTimeSlotsUpdateSubscriber;
use VM\DoctrineEncryptedFieldBundle\EventListener\ElasticSearchTransformSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Webmozart\Assert\Assert;

abstract class AbstractEncryptionCommand extends Command
{
    protected const SUBSCRIBERS_TO_IGNORE = [
        ElasticSearchTimeSlotsUpdateSubscriber::class,
        ElasticSearchTransformSubscriber::class,
    ];

    private const BATCH_SIZE = 50;

    private EntityManagerInterface $em;

    private SymfonyStyle $io;

    private PropertyAccessorInterface $propertyAccessor;

    private bool $dryRun;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    abstract protected function getProcessName(): string;

    /**
     * @param object $entity
     * @param string[] $properties
     */
    abstract protected function processSingleEntity(object $entity, array $properties): void;

    abstract protected function beforeProcess(): void;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io = new SymfonyStyle($input, $output);
        $this->dryRun = (bool) $input->getOption('dry-run');
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->dryRun) {
            $this->io->info('Dry-run. No changes will be saved.');
        }

        $entitiesToEncrypt = $this->getEncryptionableEntitiesWithProperties();

        if (count($entitiesToEncrypt) == 0) {
            $this->io->info(sprintf('No entities found to %s.', $this->getProcessName()));

            return Command::SUCCESS;
        }

        $this->disableDoctrineSubscribers();
        $this->beforeProcess();

        $this->io->info(sprintf('%d entities found to %s.', count($entitiesToEncrypt), $this->getProcessName()));

        foreach ($entitiesToEncrypt as $entity => $properties) {
            $iterator = $this->em
                ->createQuery(sprintf('SELECT o FROM %s o', $entity))
                ->toIterable();

            $totalCount = (int) $this->em
                ->createQuery(sprintf('SELECT COUNT(o) FROM %s o', $entity))
                ->getSingleScalarResult();

            $this->io->writeln(sprintf('Processing <comment>%s</comment>: %d rows', $entity, $totalCount));
            $progressBar = new ProgressBar($output, $totalCount);
            $counter = 0;

            foreach ($iterator as $row) {
                $this->processSingleEntity($row, $properties);

                if (($counter % self::BATCH_SIZE) === 0) {
                    if (!$this->dryRun) {
                        $this->em->flush();
                    }

                    $this->em->clear();
                }

                $progressBar->advance();
                ++$counter;
            }

            $progressBar->finish();
            $this->io->newLine();

            if (!$this->dryRun) {
                $this->em->flush();
            }
        }

        return Command::SUCCESS;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }

    /**
     * @return array<string, array>
     */
    private function getEncryptionableEntitiesWithProperties(): array
    {
        $entities = [];

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $entityMetaData) {
            if ($entityMetaData instanceof ClassMetadataInfo && $entityMetaData->isMappedSuperclass) {
                continue;
            }

            $properties = $this->getEncryptionableProperties($entityMetaData);

            if (count($properties) == 0) {
                continue;
            }

            $entities[$entityMetaData->getName()] = $properties;
        }

        return $entities;
    }

    /**
     * @param ClassMetadata $entityMetaData
     *
     * @return string[]
     */
    private function getEncryptionableProperties(ClassMetadata $entityMetaData): array
    {
        $properties = [];

        foreach ($entityMetaData->getFieldNames() as $fieldName) {
            if ($entityMetaData->getTypeOfField($fieldName) === EncryptedTextType::TYPE_NAME) {
                $properties[] = $fieldName;
            }
        }

        return $properties;
    }

    /**
     * Disables doctrine subscribers to prevent reindexing or history changes.
     */
    private function disableDoctrineSubscribers(): void
    {
        foreach ($this->em->getEventManager()->getListeners() as $event => $listeners) {
            Assert::isArray($listeners);

            foreach ($listeners as $listener) {
                if (in_array(get_class($listener), self::SUBSCRIBERS_TO_IGNORE)) {
                    $this->em->getEventManager()->removeEventListener($event, $listener);
                }
            }
        }
    }
}
