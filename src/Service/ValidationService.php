<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ValidationService implements ValidationServiceInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function isValidId(int $id, string $type): bool
    {
        if ($id <= 0) {
            $this->logger->warning(sprintf('Invalid %s ID received.', $type), ["{$type}_id" => $id]);
            return false;
        }
        return true;
    }

    public function isValidSearchQuery(string $query): bool
    {
        if (empty(trim($query))) {
            $this->logger->warning('Empty search query received.');
            return false;
        }
        return true;
    }
}
