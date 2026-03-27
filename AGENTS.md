# wtsergo/amp-opensearch

Async OpenSearch client adapter — bridges the official `opensearch-project/opensearch-php` SDK with AMPHP's non-blocking HTTP client.

## Key Abstractions

### AmpHandler

Callable handler passed to OpenSearch `ClientBuilder::setHandler()`. Translates OpenSearch request arrays into AMPHP HTTP requests and back.

**Constructor:**
```php
new AmpHandler(HttpClient $client, int $retryLimit = 3, float $retryDelay = 0.1)
```

**Request flow:**
1. `__invoke(array $request)` — called by OpenSearch SDK
2. Retry loop: up to `$retryLimit` attempts with `$retryDelay` between failures
3. `performRequest()` — builds AMPHP `Request` from SDK request array, executes via `HttpClient`
4. Returns `CompletedFutureArray` (Guzzle Ring format expected by SDK)

**Response format** returned to SDK:
- `status`, `reason`, `headers`, `body` (php://memory stream), `effective_url`, `transfer_stats`, `error`

### HttpClientBuilder

Factory for creating a configured AMPHP `HttpClient`:
- TLS with peer verification disabled (development default)
- `UnlimitedConnectionPool` for connection pooling
- `DefaultConnectionFactory` with `ConnectContext`

```php
$client = (new HttpClientBuilder())->build();
```

## Usage

```php
use Wtsergo\AmpOpensearch\AmpHandler;
use Wtsergo\AmpOpensearch\HttpClientBuilder;

$handler = new AmpHandler((new HttpClientBuilder())->build());

$client = (new \OpenSearch\ClientBuilder())
    ->setHandler($handler)
    ->setHosts(['https://localhost:9200'])
    ->setBasicAuthentication('admin', 'admin')
    ->setSSLVerification(false)
    ->build();

// Standard OpenSearch SDK usage — all calls are async under the hood
$client->index(['index' => 'my-index', 'id' => 1, 'body' => [...]]);
$result = $client->search(['index' => 'my-index', 'body' => ['query' => [...]]]);
```

## Gotchas

- **SSL verification disabled by default**: `HttpClientBuilder` uses `withoutPeerVerification()`. No option to enable it — review for production.
- **Hard-coded JSON headers**: Always sets Content-Type and Accept to `application/json`. Not customizable per-request.
- **Memory stream for all responses**: Uses `php://memory` — large responses consume memory with no streaming option.
- **transfer_stats incomplete**: `total_time` is hardcoded to 0. No actual timing info.
- **Requires Amp event loop context**: Retry delays use `EventLoop::delay()` + suspension. Will fail if called outside Revolt event loop.
- **CompletedFutureArray dependency**: Comes transitively via `opensearch-php`, not declared directly.
