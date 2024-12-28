<?php

require_once __DIR__ . '/bootstrap.php';

use Wtsergo\AmpOpensearch\HttpClientBuilder;
use Wtsergo\AmpOpensearch\AmpHandler;

$ampHandler = new AmpHandler((new HttpClientBuilder())->build());

$client = (new \OpenSearch\ClientBuilder())
    ->setHandler($ampHandler)
    ->setHosts(['https://localhost:7200'])
    ->setBasicAuthentication('admin', 'admin')
    ->setSSLVerification(false)
    ->build();

$result = $client->info();

var_dump($result);

var_dump("\n\n\ncreate index\n\n\n");

$indexName = 'test-index-name';

// Create an index with non-default settings.
$result = $client->indices()->create([
    'index' => $indexName,
    'body' => [
        'settings' => [
            'index' => [
                'number_of_shards' => 4
            ]
        ]
    ]
]);

var_dump($result);

var_dump("\n\n\nadd document\n\n\n");

$result = $client->create([
    'index' => $indexName,
    'id' => 1,
    'body' => [
        'title' => 'Moneyball',
        'director' => 'Bennett Miller',
        'year' => 2011
    ]
]);

var_dump($result);

var_dump("\n\n\nsearch\n\n\n");

$result = $client->search([
    'index' => $indexName,
    'body' => [
        'size' => 5,
        'query' => [
            'multi_match' => [
                'query' => 'miller',
                'fields' => ['title^2', 'director']
            ]
        ]
    ]
]);

var_dump($result);
