<?php
declare(strict_types=1);

namespace Eos\ElasticsearchConnector\Connection;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class SimpleConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @var string
     */
    private $dsn;

    /**
     * @param string $dsn
     */
    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
    }

    /**
     * @return Client
     */
    public function createConnection(): Client
    {
        return ClientBuilder::create()->setHosts([$this->dsn])->build();
    }
}
