<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\CancellableHttpTransportInterface;
use Batoi\Aif\Contracts\CancellationTokenInterface;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Value\HttpResponse;

final readonly class CurlHttpTransport implements CancellableHttpTransportInterface
{
    private const MAX_RESPONSE_BYTES = 10_485_760;

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        return $this->postJsonCancellable($url, $headers, $payload, new NeverCancelledToken(), $timeoutSeconds);
    }

    public function postJsonCancellable(
        string $url,
        array $headers,
        array $payload,
        CancellationTokenInterface $cancellation,
        int $timeoutSeconds = 30,
    ): HttpResponse {
        $cancellation->throwIfCancellationRequested();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw ProviderRequestException::failed('http', 0, 'Unable to encode request payload.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw ProviderRequestException::failed('http', 0, 'Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $responseBody = '';
        $responseTooLarge = false;

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => $this->headers($headers),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => min(10, $timeoutSeconds),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function () use ($cancellation): int {
                return $cancellation->isCancellationRequested() ? 1 : 0;
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($line, $separator + 1));
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $handle,
                string $chunk,
            ) use (
                &$responseBody,
                &$responseTooLarge,
            ): int {
                if (strlen($responseBody) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $responseTooLarge = true;

                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ]);

        $executed = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        $cancellation->throwIfCancellationRequested();

        if ($responseTooLarge) {
            throw ProviderRequestException::failed('http', $statusCode, 'Provider response exceeded the configured size limit.');
        }

        if ($executed === false) {
            throw ProviderRequestException::failed('http', 0, $error !== '' ? $error : 'Unknown cURL error.');
        }

        return new HttpResponse(
            statusCode: $statusCode,
            body: $responseBody,
            headers: $responseHeaders,
        );
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function headers(array $headers): array
    {
        $lines = ['Content-Type: application/json'];

        foreach ($headers as $name => $value) {
            $lines[] = sprintf('%s: %s', $name, $value);
        }

        return $lines;
    }
}
