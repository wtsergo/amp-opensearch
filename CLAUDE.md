# wtsergo/amp-opensearch

Async OpenSearch adapter bridging `opensearch-php` SDK with AMPHP HTTP client.

See [AGENTS.md](AGENTS.md) for detailed documentation.

## Quick Reference

- **Handler**: `AmpHandler` — callable for `ClientBuilder::setHandler()`
- **Client factory**: `HttpClientBuilder::build()` — AMPHP HttpClient with connection pooling
- **Retry**: Configurable `retryLimit` (default 3) and `retryDelay` (default 0.1s)
- **SSL**: Peer verification disabled by default
- **Headers**: Hard-coded `application/json` Content-Type and Accept
