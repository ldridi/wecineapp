<?php
namespace App\Provider;

use Psr\Log\LoggerInterface;

class TokenProvider implements TokenProviderInterface
{
    private ?string $bearerToken;
    private LoggerInterface $logger;

    public function __construct($bearerToken, LoggerInterface $logger)
    {
        $this->bearerToken = $bearerToken;
        $this->logger = $logger;
    }

    public function getBearerToken(): string
    {
        if ($this->bearerToken === null) {
            $this->bearerToken = $this->loadToken();
        }

        return $this->bearerToken;
    }

    public function invalidateToken(): void
    {
        $this->bearerToken = null;
    }

    private function loadToken(): string
    {
        if ($this->bearerToken === null || empty($this->bearerToken)) {
            $this->logger->error('Bearer token not found in environment variables.');
            throw new \RuntimeException('Bearer token is not set.');
        }

        return $this->bearerToken;
    }
}
