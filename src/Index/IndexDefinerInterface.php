<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Index;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface IndexDefinerInterface
{
    /**
     * Returns the index name for the given type.
     *
     * @param string $type
     * @return string|null
     */
    public function getIndexName(string $type): ?string;

    /**
     * Returns the index definition for the given type.
     *
     * @param string $type
     * @return array|null
     */
    public function getIndexDefinition(string $type): ?array;

    /**
     * Returns all pipeline definitions for the given type.
     * As array key the pipeline id must be used, as value the pipeline definition must be used.
     * Is you wish to always execute a pipeline for a type, define "index.default_pipeline" in your index settings
     *
     * @param string $type
     * @return array|null
     */
    public function getPipelineDefinitions(string $type): ?array;

    /**
     * Prepares a given document to be stored in the index for the given type.
     * (removes not configured keys from document to prevent elasticsearch errors)
     *
     * @param string $type
     * @param array $document
     * @return array|null
     */
    public function prepare(string $type, array $document): ?array;
}
