<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Index;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class IndexDefinerRegistry implements ParallelIndexDefinerInterface
{
    /**
     * @var IndexDefinerInterface[]
     */
    private $definitions;

    /**
     * @param string $type
     * @param IndexDefinerInterface $definition
     */
    public function addDefinition(string $type, IndexDefinerInterface $definition): void
    {
        $this->definitions[$type] = $definition;
    }

    /**
     * Returns the index name for the given type.
     *
     * @param string $type
     * @return string|null
     */
    public function getIndexName(string $type): ?string
    {
        return array_key_exists($type, $this->definitions) ?
            $this->definitions[$type]->getIndexName($type) : null;
    }

    /**
     * @param string $type
     * @return string|null
     */
    public function getParallelIndexName(string $type): ?string
    {
        if (!array_key_exists($type, $this->definitions)) {
            return null;
        }

        $indexDefiner = $this->definitions[$type];
        return $indexDefiner instanceof ParallelIndexDefinerInterface ?
            $indexDefiner->getParallelIndexName($type) : null;
    }

    /**
     * @param string $type
     */
    public function switchToParallelIndex(string $type): void
    {
        if (!array_key_exists($type, $this->definitions)) {
            return;
        }

        $indexDefiner = $this->definitions[$type];
        if ($indexDefiner instanceof ParallelIndexDefinerInterface) {
            $indexDefiner->switchToParallelIndex($type);
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
        return array_key_exists($type, $this->definitions) ?
            $this->definitions[$type]->getIndexDefinition($type) : null;
    }

    /**
     * Returns all pipeline definitions for the given type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     *
     * @param string $type
     * @return array|null
     */
    public function getPipelineDefinitions(string $type): ?array
    {
        return array_key_exists($type, $this->definitions) ?
            $this->definitions[$type]->getPipelineDefinitions($type) : null;
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
        return array_key_exists($type, $this->definitions) ?
            $this->definitions[$type]->prepare($type, $document) : null;
    }
}
