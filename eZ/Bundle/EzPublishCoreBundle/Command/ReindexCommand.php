<?php

/**
 * This file is part of the eZ Publish Kernel package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\SPI\Search\Indexing;
use eZ\Publish\SPI\Search\Indexing\ContentIndexing;
use eZ\Publish\SPI\Search\Indexing\LocationIndexing;
use eZ\Publish\SPI\Persistence\Content\ContentInfo;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use RuntimeException;
use PDO;

class ReindexCommand extends ContainerAwareCommand
{
    /**
     * @var \eZ\Publish\SPI\Search\Indexing\ContentIndexing | \eZ\Publish\SPI\Search\Indexing\LocationIndexing
     */
    private $searchHandler;

    /**
     * @var \eZ\Publish\SPI\Persistence\Handler
     */
    private $persistenceHandler;
    /**
     * @var \eZ\Publish\Core\Persistence\Database\DatabaseHandler
     */
    private $databaseHandler;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * Initialize objects required by {@see execute()}.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->logger = $this->getContainer()->get('logger');
        $this->searchHandler = $this->getContainer()->get('ezpublish.spi.search');
        $this->persistenceHandler = $this->getContainer()->get('ezpublish.api.persistence_handler');
        $this->databaseHandler = $this->getContainer()->get('ezpublish.connection');
        if (!$this->searchHandler instanceof Indexing) {
            throw new RuntimeException(
                'Expected to find Search Engine Handler implementing Indexing but found something else.'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ezplatform:reindex')
            ->setDescription('Recreate search engine index')
            ->addArgument('bulk_count', InputArgument::OPTIONAL, 'Number of objects to be indexed at once', 5)
            ->setHelp(
                <<<EOT
The command <info>%command.name%</info> indexes current configured database in configured search engine index.
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $bulkCount = $input->getArgument('bulk_count');

        $output->writeln('Creating search index for the engine: ' . get_parent_class($this->searchHandler));

        $this->searchHandler->purgeIndex();

        if ($this->searchHandler instanceof ContentIndexing) {
            $this->createContentIndex($bulkCount);
        }

        if ($this->searchHandler instanceof LocationIndexing) {
            $this->createLocationsIndex($bulkCount);
        }

        $output->writeln(PHP_EOL . 'Finished creating search index for the engine: ' . get_parent_class($this->searchHandler));
    }

    /**
     * Wrapper for indexing Content.
     *
     * @param int $bulkCount a number of object rows to fetch in single batch
     */
    private function createContentIndex($bulkCount)
    {
        $query = $this->databaseHandler->createSelectQuery();
        $query->select('count(id)')
            ->from('ezcontentobject')
            ->where($query->expr->eq('status', ContentInfo::STATUS_PUBLISHED));
        $stmt = $query->prepare();
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();

        $query = $this->databaseHandler->createSelectQuery();
        $query->select('id', 'current_version')
            ->from('ezcontentobject')
            ->where($query->expr->eq('status', ContentInfo::STATUS_PUBLISHED));

        $stmt = $query->prepare();
        $stmt->execute();

        $this->searchHandler->purgeIndex();

        $this->output->writeln('Indexing Content...');

        /** @var \Symfony\Component\Console\Helper\ProgressBar $progress */
        $progress = new ProgressBar($this->output);
        $progress->start($totalCount);
        $i = 0;
        do {
            $contentObjects = [];

            for ($k = 0; $k <= $bulkCount; ++$k) {
                if (!$row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    break;
                }

                try {
                    $contentObjects[] = $this->persistenceHandler->contentHandler()->load(
                        $row['id'],
                        $row['current_version']
                    );
                } catch (NotFoundException $e) {
                    $this->logWarning($progress, "Could not load current version of Content with id ${row['id']}, so skipped for indexing. Full exception: " . $e->getMessage());
                }
            }

            foreach ($contentObjects as $contentObject) {
                try {
                    $this->searchHandler->indexContent($contentObject);
                } catch (NotFoundException $e) {
                    $this->logWarning($progress, 'Content with id ' . $contentObject->versionInfo->id . ' has missing data, so skipped for indexing. Full exception: ' . $e->getMessage());
                }
            }

            $progress->advance($k);
        } while (($i += $bulkCount) < $totalCount);

        $progress->finish();
    }

    /**
     * Wrapper for indexing locations.
     *
     * @param int $bulkCount a number of node rows to fetch in a single batch
     */
    private function createLocationsIndex($bulkCount)
    {
        $query = $this->databaseHandler->createSelectQuery();
        $query
            ->select('count(node_id)')
            ->from('ezcontentobject_tree')
            ->where(
                $query->expr->neq(
                    $this->databaseHandler->quoteColumn('contentobject_id'),
                    $query->bindValue(0, null, PDO::PARAM_INT)
                )
            );
        $stmt = $query->prepare();
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();

        $query = $this->databaseHandler->createSelectQuery();
        $query
            ->select('node_id')
            ->from('ezcontentobject_tree')
            ->where(
                $query->expr->neq(
                    $this->databaseHandler->quoteColumn('contentobject_id'),
                    $query->bindValue(0, null, PDO::PARAM_INT)
                )
            );

        $stmt = $query->prepare();
        $stmt->execute();

        $this->output->writeln('Indexing Locations...');

        /** @var \Symfony\Component\Console\Helper\ProgressBar $progress */
        $progress = new ProgressBar($this->output);
        $progress->start($totalCount);
        $i = 0;
        do {
            $locations = [];

            for ($k = 0; $k <= $bulkCount; ++$k) {
                if (!$row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    break;
                }

                try {
                    $locations[] = $this->persistenceHandler->locationHandler()->load($row['node_id']);
                } catch (NotFoundException $e) {
                    $this->logWarning($progress, "Could not load Location with id ${row['node_id']}, so skipped for indexing. Full exception: " . $e->getMessage());
                }
            }

            foreach ($locations as $location) {
                try {
                    $this->searchHandler->indexLocation($location);
                } catch (NotFoundException $e) {
                    $this->logWarning($progress, 'Location with id ' . $location->id . ' has missing data, so skipped for indexing. Full exception: ' . $e->getMessage());
                }
            }

            $progress->advance($k);
        } while (($i += $bulkCount) < $totalCount);

        $progress->finish();
    }

    /**
     * Log warning while progress bar is shown.
     *
     * @param \Symfony\Component\Console\Helper\ProgressBar $progress
     * @param $message
     */
    private function logWarning(ProgressBar $progress, $message)
    {
        $progress->clear();
        $this->logger->warning($message);
        $progress->display();
    }
}
