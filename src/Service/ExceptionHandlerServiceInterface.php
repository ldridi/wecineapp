<?php

namespace App\Service;

interface ExceptionHandlerServiceInterface
{
    public function handleException(\Exception $exception, array $context = []): void;
}
