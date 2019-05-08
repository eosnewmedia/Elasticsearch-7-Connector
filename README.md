Elasticsearch-7-Connector
=========================
An abstract php connector for elasticsearch 7.

```bash
composer require eos/elasticsearch-7-connector
```

## How to use?

This library provides abstract classes with base functionalities.
Your domain classes (e.g. repositories, event handlers or command processors) should extend the `AbstractConnector`
or, if you want to use parallel indices, the `AbstractParallelIndexConnector`.

### Method provided by abstract classes
All methods are defined as protected and designed for internal usage in your extending class.

`AbstractConnector`:

| Method                                        | Description                                                                                                                                                                                                    |
|-----------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| getConnection()                               | Returns the current elasticsearch client instance.                                                                                                                                                             |
| getIndexName($type)                           | Returns the current index name for the given type.                                                                                                                                                             |
| createIndex($type, $overwrite)                | Creates the given index and all pipelines with the definitions for the given type.                                                                                                                             |
| dropIndex($type)                              | Drops the given index an all pipelines for the type.                                                                                                                                                           |
| storeDocument($type, $id, $data, $parameters) | Stores a document, identified by type and id, to the current elasticsearch index. $data has to contain the document sources while $parameters could contain optional parameters for the elasticsearch request. |
| removeDocument($type, $id)                    | Removes a document, identified by type and id, from the current elasticsearch index.                                                                                                                           |
| executeBulk($force)                           | Executes all bulk actions if forced or bulk size is reached                                                                                                                                                    |

`AbstractParallelIndexConnector`:

| Method                                         | Description                                                                                                                                                                                        |
|------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| migrateToParallelIndex($type, $removeOldIndex) | Creates the parallel index, migrates all documents from current index to parallel index, switches the current index to parallel index and then deletes the old index (if remove old index is true) |
| reindexDocumentsToParallelIndex($hits, $type)  | Bulk index all hits to the parallel index for the given type.                                                                                                                                      |


### Dependencies
The `AbstractConnector` requires an instance of `ConnectionFactoryInterface` and an instance of `IndexDefinerInterface`
(`AbstractParallelIndexConnector` instead requires `ParellelIndexDefinerInterface`).

Optional you can provide a `bulkSize`. If the `bulkSize`, which is 0 by default, is greater then 1, bulk requests with 
the given bulk size will be executed instead of single requests for each storage operation.

#### ConnectionFactoryInterface
The connection factory is responsible for creating instances of `Elasticsearch\Client`.

The provided implementation of `ConnectionFactoryInterface` is `SimpleConnectionFactory`, which will take a single (!)
`dsn` (`scheme://host:port`) as constructor argument.

#### IndexDefinerInterface
Index definers provide methods, which contains/creates the elasticsearch definition of a specific index.

An index definer can provide more then one index definition but only one for each type.

Each type will be stored in its own index.

If you want to use one index definer for each type, your index definers could extend `AbstractIndexDefiner` and can be added
to an instance of `IndexDefinerRegistry`.

If you want to prefix all your index names with the same base name, you could wrap the `IndexDefinerRegistry` into an instance of `PrefixedIndexDefiner` 
(its also possible to wrap each single index definer into its own `PrefixedIndexDefiner` or define full index names directly in your index definer).

## Docker / Docker Compose
For local development a docker container with all required components (php, composer) can be created and used:

Build and start the container
```bash
docker-compose up --build -d
```

Execute a command in the container (here "composer install"):
```bash
docker-compose exec app sh -c "composer install"
```
