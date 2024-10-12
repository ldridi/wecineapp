<?php

namespace App\Service;

interface ValidationServiceInterface
{
    public function isValidId(int $id, string $type): bool;
    public function isValidSearchQuery(string $query): bool;
}
