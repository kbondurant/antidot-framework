<?php

declare(strict_types=1);

namespace Antidot\Application\Http\Response;

use Psr\Http\Message\ResponseInterface;
use Throwable;

final class ServerRequestErrorResponseGenerator implements ErrorResponseGenerator
{
    private $responseFactory;
    private $debug;
    private $stackTraceTemplate = <<< 'EOT'
Exception %s was thrown in file %s in line %d:
Message: %s
Stack Trace:
%s
EOT;

    public function __construct(callable $responseFactory, bool $isDevelopmentMode = false)
    {
        $this->responseFactory = static function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };
        $this->debug = $isDevelopmentMode;
    }

    public function __invoke(Throwable $e): ResponseInterface
    {
        $response = ($this->responseFactory)();
        $response = $response->withStatus($this->getStatusCode($e, $response));

        return $this->prepareDefaultResponse($e, $this->debug, $response);
    }

    private function prepareDefaultResponse(Throwable $e, bool $debug, ResponseInterface $response): ResponseInterface
    {
        $message = 'An unexpected error occurred';
        if ($debug) {
            $message .= "; stack trace:\n\n".$this->prepareStackTrace($e);
        }
        $response->getBody()->write($message);

        return $response;
    }

    private function prepareStackTrace(Throwable $e): string
    {
        $message = '';
        do {
            $message .= \sprintf(
                $this->stackTraceTemplate,
                \get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }

    private function getStatusCode(Throwable $e, ResponseInterface $response): int
    {
        return $e->getCode() ?? $response->getStatusCode() ?? 500;
    }
}
