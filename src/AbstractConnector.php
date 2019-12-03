<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector;

use Elasticsearch\Client;
use Eos\ElasticsearchConnector\Connection\ConnectionFactoryInterface;
use Eos\ElasticsearchConnector\Index\IndexDefinerInterface;
use RuntimeException;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
abstract class AbstractConnector
{
    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var Client
     */
    private $connection;

    /**
     * @var IndexDefinerInterface
     */
    private $indexDefiner;

    /**
     * @var int
     */
    private $bulkSize;

    /**
     * @var array|null
     */
    private $bulkBody;

    /**
     * @param ConnectionFactoryInterface $connectionFactory
     * @param IndexDefinerInterface $indexDefiner
     * @param int $bulkSize
     */
    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        IndexDefinerInterface $indexDefiner,
        int $bulkSize = 0
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->indexDefiner = $indexDefiner;
        $this->bulkSize = $bulkSize;
    }

    /**
     * @return bool
     */
    final protected function useBulk(): bool
    {
        return $this->bulkSize > 1;
    }

    /**
     * @return Client
     */
    final protected function getConnection(): Client
    {
        if (!$this->connection) {
            $this->connection = $this->connectionFactory->createConnection();
        }

        return $this->connection;
    }

    /**
     * @param string $type
     * @return string
     * @throws RuntimeException
     */
    final protected function getIndexName(string $type): string
    {
        $indexName = $this->indexDefiner->getIndexName($type);
        if (!$indexName) {
            throw new RuntimeException('No index defined for type ' . $type . '.');
        }

        return $indexName;
    }

    /**
     * @param string $indexName
     * @return string|null
     */
    final protected function extractTypeFromIndexName(string $indexName): ?string
    {
        foreach ($this->indexDefiner->getSupportedIndices() as $type => $supportedIndex) {
            if ($indexName === $supportedIndex) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param string $type
     * @return array
     * @throws RuntimeException
     */
    final protected function getIndexDefinition(string $type): array
    {
        $indexDefinition = $this->indexDefiner->getIndexDefinition($type);
        if (!$indexDefinition) {
            throw new RuntimeException('No index defined for type ' . $type . '.');
        }

        return $indexDefinition;
    }

    /**
     * @param string $type
     * @return array
     * @throws RuntimeException
     */
    final protected function getPipelineDefinitions(string $type): array
    {
        $pipelineDefinitions = $this->indexDefiner->getPipelineDefinitions($type);
        if (!$pipelineDefinitions) {
            return [];
        }

        return $pipelineDefinitions;
    }

    /**
     * @param string $type
     * @param array $data
     * @return array
     * @throws RuntimeException
     */
    final protected function prepareDocument(string $type, array $data): array
    {
        $prepared = $this->indexDefiner->prepare($type, $data);
        if (!$prepared) {
            throw new RuntimeException('Preparing documents of type ' . $type . ' is not possible.');
        }

        return $prepared;
    }

    /**
     * Creates an elasticsearch index
     *
     * @param string $indexName
     * @param string $type
     * @param bool $overwrite
     * @throws RuntimeException
     */
    final protected function executeCreateIndex(string $indexName, string $type, bool $overwrite = false): void
    {
        if ($this->getConnection()->indices()->exists(['index' => $indexName])) {
            if (!$overwrite) {
                throw new RuntimeException('Elasticsearch index ' . $indexName . ' does already exists.');
            }

            $this->getConnection()->indices()->delete(['index' => $indexName]);
        }

        foreach ($this->getPipelineDefinitions($type) as $pipeline => $definition) {
            $this->getConnection()->ingest()->putPipeline(
                [
                    'id' => $pipeline,
                    'body' => $definition
                ]
            );
        }

        $this->getConnection()->indices()->create(
            [
                'index' => $indexName,
                'body' => $this->getIndexDefinition($type)
            ]
        );
    }

    /**
     * Creates the elasticsearch index for the given type
     *
     * @param string $type
     * @param bool $overwrite
     */
    final protected function createIndex(string $type, bool $overwrite = false): void
    {
        $this->executeCreateIndex($this->getIndexName($type), $type, $overwrite);
    }

    /**
     * Drops an elasticsearch index
     *
     * @param string $indexName
     * @param string $type
     * @throws \RuntimeException
     */
    final protected function executeDropIndex(string $indexName, string $type): void
    {
        if (!$this->getConnection()->indices()->exists(['index' => $indexName])) {
            return;
        }

        foreach ($this->getPipelineDefinitions($type) as $pipeline => $definition) {
            $this->getConnection()->ingest()->deletePipeline(['id' => $pipeline]);
        }

        $this->getConnection()->indices()->delete(['index' => $indexName]);
    }

    /**
     * Drops the elasticsearch index for the given type
     *
     * @param string $type
     */
    final protected function dropIndex(string $type): void
    {
        $this->executeDropIndex($this->getIndexName($type), $type);
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @param array $parameters
     * @throws RuntimeException
     */
    final protected function storeDocument(string $type, string $id, array $data, array $parameters = []): void
    {
        $parameters = $this->prepareParameters($parameters, $type, $id);
        $body = $this->prepareDocument($type, $data);

        if ($this->useBulk()) {
            $this->bulkBody[] = ['index' => $parameters,];
            $this->bulkBody[] = $body;

            $this->executeBulk();
        } else {
            $parameters['body'] = $body;

            $this->getConnection()->index($parameters);
        }
    }

    /**
     * Removes a document from elasticsearch
     *
     * @param string $type
     * @param string $id
     * @param array $parameters
     * @throws RuntimeException
     */
    final protected function removeDocument(string $type, string $id, array $parameters = []): void
    {
        $parameters = $this->prepareParameters($parameters, $type, $id);

        if ($this->useBulk()) {
            $this->bulkBody[] = ['delete' => $parameters,];

            $this->executeBulk();
        } else {
            $this->getConnection()->delete($parameters);
        }
    }

    /**
     * @param array $parameters
     * @param string $type
     * @param string $id
     * @return array
     */
    final protected function prepareParameters(array $parameters, string $type, string $id): array
    {
        if ($this->useBulk()) {
            $parameters['_index'] = $this->getIndexName($type);
            $parameters['_id'] = $id;
        } else {
            $parameters['index'] = $this->getIndexName($type);
            $parameters['id'] = $id;
        }

        return $parameters;
    }

    /**
     * Executes all bulk actions if forced or bulk size is reached
     *
     * @param bool $force
     */
    final protected function executeBulk(bool $force = false): void
    {
        // don't execute on empty body
        if (!$this->bulkBody || !$this->useBulk()) {
            return;
        }

        // don't execute if size not reached and execution is not forced
        if (!$force && count($this->bulkBody) < $this->bulkSize) {
            return;
        }

        $this->getConnection()->bulk(['body' => $this->bulkBody, 'refresh' => true]);

        // clear bulk body
        $this->bulkBody = null;
    }

    /**
     * Executes all queued bulk actions before finishing the php process
     */
    public function __destruct()
    {
        $this->executeBulk(true);
    }
}
