<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Index;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface ParallelIndexDefinerInterface extends IndexDefinerInterface
{
    /**
     * @param string $type
     * @return string|null
     */
    public function getParallelIndexName(string $type): ?string;

    /**
     * @param string $type
     */
    public function switchToParallelIndex(string $type): void;
}
