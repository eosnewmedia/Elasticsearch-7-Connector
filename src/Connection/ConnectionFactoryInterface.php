<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Connection;

use Elasticsearch\Client;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface ConnectionFactoryInterface
{
    /**
     * @return Client
     */
    public function createConnection(): Client;
}
