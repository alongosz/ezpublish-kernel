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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    const CONTENTOBJECT_TABLE = 'ezcontentobject';
    const CONTENTOBJECT_TREE_TABLE = 'ezcontentobject_tree';

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
                sprintf('Expected to find Search Engine Handler implementing "%s" to be able to re index, but currently configured handler "%s" does not implement it', Indexing::class, get_parent_class($this->searchHandler))
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
            ->addOption('iteration-count', 'c', InputOption::VALUE_OPTIONAL, 'Number of objects to be indexed in a single iteration', 20)
            ->addOption('no-commit', null, InputOption::VALUE_NONE, 'Do not commit after each iteration')
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
        $iterationCount = $input->getOption('iteration-count');
        $noCommit = $input->getOption('no-commit');

        $output->writeln('Creating search index for the engine: ' . get_parent_class($this->searchHandler));

        if ($this->searchHandler instanceof ContentIndexing) {
            $this->searchHandler->purgeIndex();
            $this->createContentIndex($iterationCount, empty($noCommit));
            // Make changes available for search
            $this->searchHandler->commit();
        } else {
            $output->writeln('Search Handler ' . get_class($this->searchHandler) . ' does not support ContentIndexing. Nothing to do.');
        }

        $output->writeln('Finished creating search index for the engine: ' . get_parent_class($this->searchHandler));
    }

    /**
     * Wrapper for indexing Content.
     *
     * @param int $iterationCount a number of object items to be indexed at once
     * @param bool $commit commit search index after each iteration
     */
    private function createContentIndex($iterationCount, $commit)
    {
        $stmt = $this->getContentDbFieldsStmt(['count(id)']);
        $totalCount = intval($stmt->fetchColumn());

        $stmt = $this->getContentDbFieldsStmt(['id', 'current_version']);

        $this->searchHandler->purgeIndex();

        /** @var \Symfony\Component\Console\Helper\ProgressBar $progress */
        $progress = new ProgressBar($this->output);
        $progress->start($totalCount);
        $i = 0;
        do {
            $contentObjects = [];
            $locations = [];

            for ($k = 0; $k <= $iterationCount; ++$k) {
                if (!$row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    break;
                }

                try {
                    $content = $this->persistenceHandler->contentHandler()->load(
                        $row['id'],
                        $row['current_version']
                    );
                    $contentObjects[] = $content;

                    // Skip location indexing if search engine does not use it, or if content does not have locations
                    if (!($this->searchHandler instanceof LocationIndexing) || empty($content->versionInfo->contentInfo->mainLocationId)) {
                        continue;
                    }
                    $locationIds = $this->getContentLocationIds($row['id']);
                    foreach ($locationIds as $locationId) {
                        try {
                            $locations[] = $this->persistenceHandler->locationHandler()->load($locationId);
                        } catch (NotFoundException $e) {
                            $this->logWarning($progress, "Could not load Location with id $locationId, so skipped for indexing. Full exception: " . $e->getMessage());
                        }
                    }
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

            foreach ($locations as $location) {
                try {
                    $this->searchHandler->indexLocation($location);
                } catch (NotFoundException $e) {
                    $this->logWarning($progress, 'Location with id ' . $location->id . ' has missing data, so skipped for indexing. Full exception: ' . $e->getMessage());
                }
            }

            if ($commit) {
                $this->searchHandler->commit();
            }

            $progress->advance($k);
        } while (($i += $iterationCount) < $totalCount);

        $progress->finish();
        $this->output->writeln('');
    }

    /**
     * Get PDOStatement to fetch metadata about content objects to be indexed.
     *
     * @param array $fields Select fields
     * @return \PDOStatement
     */
    private function getContentDbFieldsStmt(array $fields)
    {
        $query = $this->databaseHandler->createSelectQuery();
        $query->select($fields)
            ->from($this->databaseHandler->quoteTable(self::CONTENTOBJECT_TABLE))
            ->where($query->expr->eq('status', ContentInfo::STATUS_PUBLISHED));
        $stmt = $query->prepare();
        $stmt->execute();

        return $stmt;
    }

    /**
     * Fetch location Ids for the given content object.
     *
     * @param int $contentObjectId
     * @return array Location nodes Ids
     */
    private function getContentLocationIds($contentObjectId)
    {
        $query = $this->databaseHandler->createSelectQuery();
        $query->select('node_id')
            ->from($this->databaseHandler->quoteTable(self::CONTENTOBJECT_TREE_TABLE))
            ->where($query->expr->eq('contentobject_id', $contentObjectId));

        $stmt = $query->prepare();
        $stmt->execute();
        $nodeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return is_array($nodeIds) ? array_map('intval', $nodeIds) : [];
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
