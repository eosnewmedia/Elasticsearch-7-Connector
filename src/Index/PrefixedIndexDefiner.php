<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Index;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class PrefixedIndexDefiner implements ParallelIndexDefinerInterface
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var IndexDefinerInterface
     */
    private $indexDefiner;

    /**
     * @param string $prefix
     * @param IndexDefinerInterface $indexDefiner
     */
    public function __construct(string $prefix, IndexDefinerInterface $indexDefiner)
    {
        $this->prefix = $prefix;
        $this->indexDefiner = $indexDefiner;
    }

    /**
     * Returns the index name for the given type.
     *
     * @param string $type
     * @return string|null
     */
    public function getIndexName(string $type): ?string
    {
        $indexName = $this->indexDefiner->getIndexName($type);
        return $indexName ? $this->prefix . $indexName : null;
    }

    /**
     * Returns an array where the type is used as key and the index name is used as value.
     *
     * @return string[]
     */
    public function getSupportedIndices(): array
    {
        $supportedIndices = [];
        foreach (array_keys($this->indexDefiner->getSupportedIndices()) as $type) {
            $supportedIndices[$type] = $this->getIndexName($type);
        }

        return $supportedIndices;
    }

    /**
     * @param string $type
     * @return string|null
     */
    public function getParallelIndexName(string $type): ?string
    {
        return $this->indexDefiner instanceof ParallelIndexDefinerInterface ?
            $this->indexDefiner->getParallelIndexName($type) : null;
    }

    /**
     * @param string $type
     */
    public function switchToParallelIndex(string $type): void
    {
        if ($this->indexDefiner instanceof ParallelIndexDefinerInterface) {
            $this->indexDefiner->switchToParallelIndex($type);
        }
    }

    /**
     * Returns the index definition for the given type.
     *
     * @param string $type
     * @return array|null
     */
    public function getIndexDefinition(string $type): ?array
    {
        return $this->indexDefiner->getIndexDefinition($type);
    }

    /**
     * Returns all pipeline definitions for the given type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     *
     * @param string $type
     * @param string|null $pipelineNamePrefix
     * @return array|null
     */
    public function getPipelineDefinitions(string $type, ?string $pipelineNamePrefix = null): ?array
    {
        return $this->indexDefiner->getPipelineDefinitions($type, $pipelineNamePrefix);
    }

    /**
     * @param string $type
     * @param string|null $pipelineNamePrefix
     * @return string|null
     */
    public function getDefaultPipelineName(string $type, ?string $pipelineNamePrefix = null): ?string
    {
        return $this->indexDefiner->getDefaultPipelineName($type, $pipelineNamePrefix);
    }

    /**
     * Prepares a given document to be stored in the index for the given type.
     * (removes not configured keys from document to prevent elasticsearch errors)
     *
     * @param string $type
     * @param array $document
     * @return array|null
     */
    public function prepare(string $type, array $document): ?array
    {
        return $this->indexDefiner->prepare($type, $document);
    }
}
