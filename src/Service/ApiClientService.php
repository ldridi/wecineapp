<?php

namespace App\Service;

use App\Provider\TokenProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClientService
{
    private HttpClientInterface $client;
    private LoggerInterface $logger;
    private TokenProvider $tokenProvider;
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_DELAY_MS = 1000;
    public const MAX_BACKOFF_DELAY_MS = 8000;

    public function __construct(TokenProvider $tokenProvider, HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $maxRetries
     * @param int $initialDelay
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     */
    public function makeRequest(
        string $method,
        string $endpoint,
        array $params = [],
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        int $initialDelay = self::DEFAULT_DELAY_MS
    ): array {
        $attempt = 0;
        $delay = $initialDelay;
        $tokenRefreshed = false;

        while ($attempt < $maxRetries) {
            $attempt++;
            $this->logRequestAttempt($method, $endpoint, $params, $attempt);

            try {
                $response = $this->client->request($method, $endpoint, [
                    'query' => $params,
                    'headers' => $this->getDefaultHeaders(),
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === Response::HTTP_OK) {
                    $data = $response->toArray();
                    $this->logSuccessfulResponse($statusCode, $data);
                    return $data;
                }

                if ($statusCode === Response::HTTP_UNAUTHORIZED && !$tokenRefreshed) {
                    $this->logger->warning('Received 401 Unauthorized. Attempting to refresh Bearer token.');
                    $this->tokenProvider->invalidateToken();
                    $tokenRefreshed = true;
                    continue;
                }

                $this->logNonSuccessStatus($statusCode, $response);
                throw new HttpException($statusCode, "API returned status code {$statusCode}.");

            } catch (TransportExceptionInterface | RedirectionExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
                $this->logException($e, $method, $endpoint, $params, $attempt);

                if ($attempt >= $maxRetries) {
                    $this->logger->critical('Max retry attempts reached. Giving up on API request.', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'params' => $params,
                    ]);
                    throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'External API service is unavailable.');
                }

                $this->sleepMilliseconds($delay);
                $delay = $this->increaseDelay($delay);
            } catch (\Exception $e) {
                $this->logException($e, $method, $endpoint, $params, $attempt);
                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'An unexpected error occurred during API communication.');
            }
        }

        throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to make API request after retries.');
    }

    /**
     * @return string[]
     */
    private function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenProvider->getBearerToken(),
        ];
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $attempt
     * @return void
     */
    public function logRequestAttempt(string $method, string $endpoint, array $params, int $attempt): void
    {
        $this->logger->info('Attempting API request.', [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'attempt' => $attempt,
        ]);
    }

    /**
     * @param int $statusCode
     * @param array $data
     * @return void
     */
    public function logSuccessfulResponse(int $statusCode, array $data): void
    {
        $this->logger->info('API request successful.', [
            'status_code' => $statusCode,
            'response_data_keys' => array_keys($data),
        ]);
    }

    /**
     * @param int $retryAfter
     * @return void
     */
    public function logRateLimitExceeded(int $retryAfter): void
    {
        $this->logger->warning('Rate limit exceeded. Retrying after delay.', [
            'retry_after_ms' => $retryAfter,
        ]);
    }

    /**
     * @param int $statusCode
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     * @return void
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function logNonSuccessStatus(int $statusCode, \Symfony\Contracts\HttpClient\ResponseInterface $response): void
    {
        $this->logger->warning('API returned a non-200 status code.', [
            'status_code' => $statusCode,
            'response_content' => $response->getContent(false),
        ]);
    }

    /**
     * @param \Exception $e
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $attempt
     * @return void
     */
    public function logException(\Exception $e, string $method, string $endpoint, array $params, int $attempt): void
    {
        $this->logger->error('Error during API request.', [
            'exception_message' => $e->getMessage(),
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'attempt' => $attempt,
        ]);
    }

    /**
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     * @return int|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getRetryAfter(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?int
    {
        $headers = $response->getHeaders(false);
        if (isset($headers['retry-after'][0])) {
            $retryAfterSeconds = (int) $headers['retry-after'][0];
            return $retryAfterSeconds * 1000;
        }

        return null;
    }

    /**
     * @param int $milliseconds
     * @return void
     */
    public function sleepMilliseconds(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * @param int $currentDelay
     * @return int
     */
    public function increaseDelay(int $currentDelay): int
    {
        $nextDelay = $currentDelay * 2;
        return $nextDelay > self::MAX_BACKOFF_DELAY_MS ? self::MAX_BACKOFF_DELAY_MS : $nextDelay;
    }
}
