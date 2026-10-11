<?php

namespace App\Tests;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Test\HarFileResponseFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Replays the HTTP exchanges of a HAR file, or records them again when the RECORD_HAR env var is set.
 */
final class HarRecorder extends HarFileResponseFactory
{
    private array $entries = [];

    public function __construct(
        private readonly string $harFile,
    ) {
        parent::__construct($harFile);
    }

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        if (!($_SERVER['RECORD_HAR'] ?? false)) {
            return parent::__invoke($method, $url, $options);
        }

        $requestHeaders = [];
        foreach ($options['normalized_headers'] as $name => $lines) {
            $requestHeaders[$name] = array_map(static fn (string $line) => substr($line, \strlen($name) + 2), $lines);
        }

        $response = HttpClient::create()->request($method, $url, ['headers' => $options['headers'], 'body' => $options['body']]);
        $content = $response->getContent(false);
        $headers = $response->getHeaders(false);

        $this->entries[] = [
            'startedDateTime' => gmdate('Y-m-d\TH:i:s\Z'),
            'time' => 0,
            'request' => [
                'method' => $method,
                'url' => $url,
                'httpVersion' => 'HTTP/1.1',
                'cookies' => [],
                'headers' => self::toHarHeaders($requestHeaders),
                'queryString' => [],
                'postData' => ['text' => $options['body'], 'mimeType' => $requestHeaders['content-type'][0] ?? ''],
                'headersSize' => -1,
                'bodySize' => \strlen($options['body']),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'statusText' => '',
                'httpVersion' => 'HTTP/1.1',
                'cookies' => [],
                'headers' => self::toHarHeaders($headers),
                'content' => ['text' => $content, 'size' => \strlen($content), 'mimeType' => $headers['content-type'][0] ?? ''],
                'redirectURL' => '',
                'headersSize' => -1,
                'bodySize' => \strlen($content),
            ],
            'cache' => [],
            'timings' => ['send' => 0, 'wait' => 0, 'receive' => 0],
        ];

        $har = ['log' => ['version' => '1.2', 'creator' => ['name' => self::class, 'version' => '1.0'], 'entries' => $this->entries]];
        file_put_contents($this->harFile, json_encode($har, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        return new MockResponse($content, ['http_code' => $response->getStatusCode(), 'response_headers' => $headers]);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function toHarHeaders(array $headers): array
    {
        $harHeaders = [];
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                // the files are committed: credentials and sessions stay out of them
                $harHeaders[] = ['name' => $name, 'value' => \in_array($name, ['authorization', 'cookie', 'set-cookie'], true) ? '[REDACTED]' : $value];
            }
        }

        return $harHeaders;
    }
}
