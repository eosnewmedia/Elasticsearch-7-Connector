<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Index;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
abstract class AbstractIndexDefiner implements IndexDefinerInterface
{
    /**
     * @return string
     */
    abstract protected function getType(): string;

    /**
     * Returns the index name for the given type.
     *
     * @param string $type
     * @return string|null
     */
    public function getIndexName(string $type): ?string
    {
        if ($this->getType() !== $type) {
            return null;
        }

        // split camelCaseType into underscore_type
        return strtolower(implode('_', preg_split('/(?=[A-Z])/', $type)));
    }

    /**
     * Returns an array where the type is used as key and the index name is used as value.
     *
     * @return string[]
     */
    public function getSupportedIndices(): array
    {
        return [$this->getType() => $this->getIndexName($this->getType())];
    }

    /**
     * Returns the index definition for the given type.
     *
     * @param string $type
     * @param string|null $pipelineNamePrefix
     * @return array|null
     */
    final public function getIndexDefinition(string $type, ?string $pipelineNamePrefix = null): ?array
    {
        if ($this->getType() !== $type) {
            return null;
        }

        return $this->getIndexDefinitionForType($pipelineNamePrefix);
    }

    /**
     * Returns the index definition for the type.
     *
     * @param string|null $pipelineNamePrefix
     * @return array
     */
    abstract protected function getIndexDefinitionForType(?string $pipelineNamePrefix = null): array;

    /**
     * Returns all pipeline definitions for the given type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     * Is you wish to always execute a pipeline for a type, define "index.default_pipeline" in your index settings
     *
     * @param string $type
     * @param string|null $pipelineNamePrefix
     * @return array|null
     */
    final public function getPipelineDefinitions(string $type, ?string $pipelineNamePrefix = null): ?array
    {
        if ($this->getType() !== $type) {
            return null;
        }

        return $this->getPipelineDefinitionsForType($pipelineNamePrefix);
    }

    /**
     * Returns all pipeline definitions for the type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     * Is you wish to always execute a pipeline for a type, define "index.default_pipeline" in your index settings
     *
     * @param string|null $pipelineNamePrefix
     * @return array
     */
    abstract protected function getPipelineDefinitionsForType(?string $pipelineNamePrefix = null): array;

    /**
     * @param string $type
     * @param string|null $pipelineNamePrefix
     * @return string|null
     */
    public function getDefaultPipelineName(string $type, ?string $pipelineNamePrefix = null): ?string
    {
        return null;
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
        if ($this->getType() !== $type) {
            return null;
        }

        $definition = (array)$this->getIndexDefinition($type);

        if (!array_key_exists('mappings', $definition)) {
            return $document;
        }

        if (!array_key_exists('properties', $definition['mappings'])) {
            return $document;
        }

        return $this->prepareObject($definition['mappings']['properties'], $document);
    }

    /**
     * @param $definition
     * @param array $object
     *
     * @return array
     */
    private function prepareObject(array $definition, array $object): array
    {
        foreach ($object as $key => $value) {
            if (!array_key_exists($key, $definition)) {
                unset($object[$key]);
                continue;
            }

            if ($definition[$key]['type'] === 'object') {
                if (!is_array($value) && $value !== null) {
                    unset($object[$key]);
                    continue;
                }

                if (!array_key_exists('properties', $definition[$key]) || count($value) === 0) {
                    continue;
                }

                // if array is object
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $object[$key] = $this->prepareObject((array)$definition[$key]['properties'], $value);
                } else { // if array is list of objects
                    $object[$key] = [];
                    foreach ($value as $item) {
                        $object[$key][] = $this->prepareObject((array)$definition[$key]['properties'], $item);
                    }
                }
            }
        }

        return $object;
    }
}
