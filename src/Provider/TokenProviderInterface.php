<?php
namespace App\Provider;

interface TokenProviderInterface
{
    public function getBearerToken(): string;
    public function invalidateToken(): void;
}
