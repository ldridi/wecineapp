<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use Symfony\Component\HttpFoundation\RequestStack;

class ExceptionHandlerService implements ExceptionHandlerServiceInterface
{
    private LoggerInterface $logger;
    private RequestStack $requestStack;

    public function __construct(LoggerInterface $logger, RequestStack $requestStack)
    {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    public function handleException(\Exception $exception, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $flashBag = $session->getFlashBag();
            if ($exception instanceof HttpExceptionInterface) {
                $this->logger->error(
                    'API HttpException occurred.',
                    array_merge($context, ['exception' => $exception->getMessage()])
                );
                $flashBag->add('error', $exception->getMessage());
            } else {
                $this->logger->critical(
                    'Unexpected error occurred during API call.',
                    array_merge($context, ['exception' => $exception->getMessage()])
                );
                $flashBag->add('error', 'An unexpected error occurred.');
            }
        } else {
            $this->logger->critical(
                'No session available to add flash messages.',
                array_merge($context, ['exception' => $exception->getMessage()])
            );
        }
    }
}


