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
     * Returns the index definition for the given type.
     *
     * @param string $type
     * @return array|null
     */
    final public function getIndexDefinition(string $type): ?array
    {
        if ($this->getType() !== $type) {
            return null;
        }

        return $this->getIndexDefinitionForType();
    }

    /**
     * Returns the index definition for the type.
     *
     * @return array
     */
    abstract protected function getIndexDefinitionForType(): array;

    /**
     * Returns all pipeline definitions for the given type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     * Is you wish to always execute a pipeline for a type, define "index.default_pipeline" in your index settings
     *
     * @param string $type
     * @return array|null
     */
    final public function getPipelineDefinitions(string $type): ?array
    {
        if ($this->getType() !== $type) {
            return null;
        }

        return $this->getPipelineDefinitionsForType();
    }

    /**
     * Returns all pipeline definitions for the type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     * Is you wish to always execute a pipeline for a type, define "index.default_pipeline" in your index settings
     *
     * @return array
     */
    abstract protected function getPipelineDefinitionsForType(): array;

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

        $definition = $this->getIndexDefinition($type);

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
    private function prepareObject($definition, array $object): array
    {
        foreach ($object as $key => $value) {
            if (!array_key_exists($key, $definition)) {
                unset($object[$key]);
                continue;
            }

            if ($definition[$key]['type'] === 'object') {
                if (!\is_array($value)) {
                    unset($object[$key]);
                    continue;
                }

                if (!\array_key_exists('properties', $definition[$key]) || \count($value) === 0) {
                    continue;
                }

                // if array is object
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $object[$key] = $this->prepareObject($definition[$key]['properties'], $value);
                } else { // if array is list of objects
                    $object[$key] = [];
                    foreach ($value as $item) {
                        $object[$key][] = $this->prepareObject($definition[$key]['properties'], $item);
                    }
                }
            }
        }

        return $object;
    }
}
