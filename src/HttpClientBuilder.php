<?php

namespace Wtsergo\AmpOpensearch;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;

class HttpClientBuilder
{
    public function build(): HttpClient
    {
        $tlsContext = (new ClientTlsContext(''))
            ->withoutPeerVerification();
        $connectContext = (new ConnectContext)
            ->withTlsContext($tlsContext);
        return (new \Amp\Http\Client\HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $connectContext)))
            ->build()
        ;
    }
}
