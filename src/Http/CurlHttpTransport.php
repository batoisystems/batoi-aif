<?php

declare(strict_types=1);

namespace Batoi\Aif\Http;

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Exception\ProviderRequestException;
use Batoi\Aif\Value\HttpResponse;

final readonly class CurlHttpTransport implements HttpTransportInterface
{
    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw ProviderRequestException::failed('http', 0, 'Unable to encode request payload.');
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers($headers),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($responseBody === false) {
            throw ProviderRequestException::failed('http', 0, $error !== '' ? $error : 'Unknown cURL error.');
        }

        return new HttpResponse(
            statusCode: $statusCode,
            body: (string) $responseBody,
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
