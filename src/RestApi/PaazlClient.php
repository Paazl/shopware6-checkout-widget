<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\RestApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PaazlCheckoutWidget\Service\Logger\PaazlLogger;
use PaazlCheckoutWidget\Service\PaazlConfiguration;
use Psr\Http\Message\ResponseInterface;

class PaazlClient
{
    private const CONNECT_TIMEOUT = 10; // TODO: Configurable

    private readonly Client $restClient;

    public function __construct(
        private readonly PaazlLogger $paazlLogger,
        private readonly PaazlConfiguration $paazlConfiguration,
        private readonly UrlProvider $urlProvider
    ) {
        $this->restClient = new Client();
    }

    public function getApiToken(
        string $reference,
        ?string $salesChannelId
    ): string {
        $response = $this->request(
            'POST',
            $salesChannelId,
            UrlProvider::URL_CHECKOUT_TOKEN,
            [
                'json' => [
                    'reference' => $reference,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new PaazlApiException('error', 'API error');
        }

        $responseToken = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        return $responseToken['token'];
    }

    public function order(
        array $orderData,
        string $salesChannelId
    ): ResponseInterface {
        return $this->request('PUT', $salesChannelId, UrlProvider::URL_ORDER, [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
            ],
            'json' => $orderData,
        ]);
    }

    public function getPaazlCheckoutDetails(
        string $reference,
        string $salesChannelId
    ): array {
        $response = $this->request(
            'GET',
            $salesChannelId,
            UrlProvider::URL_CHECKOUT,
            [
                'query' => [
                    'reference' => $reference,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new PaazlApiException('error', 'API error');
        }

        return json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    public function exportPaazlOrder(
        array $orderData,
        string $salesChannelId
    ): array {
        $response = $this->request('POST', $salesChannelId, UrlProvider::URL_ORDER, [
            'json' => $orderData,
        ]);

        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }

        $response->getBody()->rewind();
        $content = $response->getBody()->getContents();

        if (empty($content)) {
            return [
                'type' => 'success',
                'message' => 'API Data Post SuccessFully',
            ];
        }

        return json_decode(
            $content,
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    /**
     * @throws PaazlApiException
     */
    private function request(
        string $method,
        ?string $salesChannelId,
        string $url,
        array $options = []
    ): ResponseInterface {
        $apiKey = $this->paazlConfiguration->getApiKey($salesChannelId);
        $secretKey = $this->paazlConfiguration->getSecretKey($salesChannelId);

        $url = $this->urlProvider->getUrl($url, $salesChannelId);

        $options = array_merge_recursive([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'headers' => [
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' . $apiKey . ':' . $secretKey,
            ],
        ], $options);

        try {
            return $this->restClient->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            if ($this->paazlConfiguration->debug($salesChannelId)) {
                $this->paazlLogger->addEntry(
                    'Paazl API Request Error',
                    $exception,
                    [
                        'method' => $method,
                        'url' => $url,
                        'options' => $options,
                    ]
                );
            }

            throw new PaazlApiException(
                'error',
                $exception->getMessage(),
                $exception
            );
        }
    }
}
