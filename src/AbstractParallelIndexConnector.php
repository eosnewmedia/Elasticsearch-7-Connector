<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector;

use Eos\ElasticsearchConnector\Connection\ConnectionFactoryInterface;
use Eos\ElasticsearchConnector\Index\ParallelIndexDefinerInterface;
use RuntimeException;
use Throwable;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
abstract class AbstractParallelIndexConnector extends AbstractConnector
{
    /**
     * @var ParallelIndexDefinerInterface
     */
    private $parallelIndexDefiner;

    /**
     * @param ConnectionFactoryInterface $connectionFactory
     * @param ParallelIndexDefinerInterface $indexDefiner
     * @param int $bulkSize
     */
    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        ParallelIndexDefinerInterface $indexDefiner,
        int $bulkSize = 0
    ) {
        parent::__construct($connectionFactory, $indexDefiner, $bulkSize);
        $this->parallelIndexDefiner = $indexDefiner;
    }

    /**
     * @param string $type
     * @return string
     * @throws RuntimeException
     */
    final protected function getParallelIndexName(string $type): string
    {
        $indexName = $this->parallelIndexDefiner->getParallelIndexName($type);
        if (!$indexName) {
            throw new RuntimeException('No parallel index defined for type ' . $type . '.');
        }

        return $indexName;
    }

    /**
     * @param string $type
     */
    final protected function switchToParallelIndex(string $type): void
    {
        $this->parallelIndexDefiner->switchToParallelIndex($type);
    }

    /**
     * Creates the parallel index, migrates all documents from current index to parallel index,
     * switches the current index to parallel index and then deletes the old index (if remove old index is true)
     *
     * @param string $type
     * @param bool $removeOldIndex
     * @throws Throwable
     */
    protected function migrateToParallelIndex(string $type, bool $removeOldIndex = true): void
    {
        try {
            $currentIndexName = $this->getIndexName($type);
            $parallelIndexName = $this->getParallelIndexName($type);

            // create new index
            $this->executeCreateIndex($parallelIndexName, $type, true);

            // fetch documents from current index and store to new index
            $searchResult = $this->getConnection()->search(
                [
                    'index' => $currentIndexName,
                    'scroll' => '1m',
                ]
            );
            $this->reindexDocumentsToParallelIndex($searchResult['hits']['hits'], $type);

            $context = $searchResult['_scroll_id'];
            while (true) {
                $scrollResult = $this->getConnection()->scroll([
                    'scroll_id' => $context,
                    'scroll' => '1m',
                ]);

                if (count($scrollResult['hits']['hits']) === 0) {
                    break;
                }

                $this->reindexDocumentsToParallelIndex($scrollResult['hits']['hits'], $type);

                $context = $scrollResult['_scroll_id'];
            }

            // switch current index to new index
            $this->switchToParallelIndex($type);

            if ($removeOldIndex) {
                // delete old index (without pipelines!)
                $this->getConnection()->indices()->delete(
                    [
                        'index' => $currentIndexName,
                    ]
                );
            }

        } catch (Throwable $e) {
            $this->getLogger()->error(
                'Failed to switch index for type "' . $type . '"',
                ['reason' => $e->getMessage()]
            );
            throw $e;
        }
    }

    /**
     * @param array $hits
     * @param string $type
     */
    protected function reindexDocumentsToParallelIndex(array $hits, string $type): void
    {
        $documents = [];
        foreach ($hits as $document) {
            try {
                $documents[] = [
                    'index' => [
                        '_index' => $this->getParallelIndexName($type),
                        '_id' => $document['_id'],
                    ],
                ];
                $documents[] = $this->prepareDocument($type, $document['_source']);
            } catch (Throwable $e) {

            }
        }

        if (count($documents) > 0) {
            try {
                $response = $this->getConnection()->bulk(['body' => $documents]);
                $this->handleBulkResponse($response);
            } catch (Throwable $e) {
                $this->getLogger()->error(
                    'Failed to reindex documents',
                    ['reason' => $e->getMessage()]
                );
            }
        }
    }
}
