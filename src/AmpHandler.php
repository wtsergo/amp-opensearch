<?php

namespace Wtsergo\AmpOpensearch;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use League\Uri\Uri;
use Revolt\EventLoop;

class AmpHandler
{
    public function __construct(
        protected HttpClient $client,
        protected int $retryLimit = 3,
        protected float $retryDelay = 0.1,
    )
    {
        if ($this->retryLimit < 1) {
            throw new \UnexpectedValueException('Retry limit must be a positive integer');
        }
        if ($this->retryDelay <= 0) {
            throw new \UnexpectedValueException('Retry delay must be a positive float');
        }
    }

    public function __invoke(array $request)
    {
        $attempt = 0;
        do {
            if ($attempt>0) {
                $suspension = EventLoop::getSuspension();
                EventLoop::delay($this->retryDelay, static function () use ($suspension) {
                    $suspension->resume();
                });
                $suspension->suspend();
            }
            $response = $this->performRequest($request);
        } while (++$attempt <= $this->retryLimit && !empty($response['error']));
        return new CompletedFutureArray($response);
    }

    private function performRequest(array $request)
    {
        $response = [];
        $bodyStream = \fopen('php://memory', 'rb+');
        try {
            $baseUri = $this->baseUri($request);
            $response['effective_url'] = $baseUri->toString();
            $fullUri = $this->extendUri($baseUri, $request);
            $response['primary_port'] = $fullUri->getPort();
            $ampRequest = $this->requestFromArray($fullUri, $request);
            $ampResponse = $this->client->request($ampRequest);
            $body = $ampResponse->getBody()->buffer();
            \fwrite($bodyStream, $body);
            \fseek($bodyStream, 0);
            $response['body'] = $bodyStream;
            $response['headers'] = $ampResponse->getHeaders();
            $response['status'] = $ampResponse->getStatus();
            $response['reason'] = $ampResponse->getReason();
            $response['version'] = $ampResponse->getProtocolVersion();
            $response['transfer_stats'] = [
                'content_type' => $ampResponse->getHeader('Content-Type'),
                'http_code' => $ampResponse->getStatus(),
                'total_time' => 0
            ];
        } catch (HttpException|BufferException|StreamException $e) {
            $response['status'] = \Amp\Http\HttpStatus::INTERNAL_SERVER_ERROR;
            $response['reason'] = \Amp\Http\HttpStatus::getReason($response['status']);
            $response['body'] = $bodyStream;
            $response['error'] = $e;
            $response['transfer_stats'] = [
                'total_time' => 0
            ];
        }
        return $response;
    }

    private function baseUri(array $request): Uri
    {
        [$path, $query] = explode('?', $request['uri'], 2) + [null, null];
        // TODO: verify if fragment could be present in $request
        return Uri::fromComponents([
            'scheme' => $request['scheme'],
            'host' => $request['host'] ?? $request['headers']['Host'][0] ?? null,
            'path' => $path,
            'query' => $query,
            'fragment' => null,
        ]);
    }

    private function extendUri(Uri $uri, array $request): Uri
    {
        return $uri
            ->withPort($request['client']['curl'][\CURLOPT_PORT]??null)
        ;
    }

    private function requestFromArray(Uri $uri, array $request): Request
    {
        $basicAuth = $request['client']['curl'][\CURLOPT_USERPWD]??null;
        $ampRequest = new Request($uri, $request['http_method']??'GET');
        if ($basicAuth) {
            $ampRequest->setHeader('Authorization', 'Basic ' . base64_encode($basicAuth));
        }
        $ampRequest->setHeader('Content-Type', 'application/json');
        $ampRequest->setHeader('Accept', 'application/json');
        $headers = $request['headers']??[];
        if (isset($options['client']['headers']) && is_array($options['client']['headers'])) {
            $headers = array_merge($headers, $options['client']['headers']);
        }
        foreach ($headers as $headerName => $value) {
            $ampRequest->setHeader($headerName, $value);
        }
        $body = $request['body']??null;
        if ($body && $body != "null") {
            $ampRequest->setBody($body);
        }
        return $ampRequest;
    }
}
