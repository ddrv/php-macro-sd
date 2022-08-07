<?php

declare(strict_types=1);

namespace App;

use App\Exception\Forbidden;
use App\Exception\NotFound;
use App\Web\Handler\Api;
use App\Web\Handler\Get;
use App\Web\Handler\MiddlewareHandler;
use App\Web\Middleware\AttachStorageMiddleware;
use App\Web\Middleware\AuthMiddleware;
use App\Web\Middleware\AuthRequiredMiddleware;
use App\Web\Middleware\RewindResponseBodyMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class App
{
    private ContainerInterface $container;
    private ResponseFactoryInterface $responseFactory;
    private bool $debug;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->responseFactory = $container->get(ResponseFactoryInterface::class);
        $this->debug = $container->get('debug');
    }

    public function web(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        try {
            switch ($request->getMethod()) {
                case 'HEAD':
                case 'GET':
                    $handlerClass = Get::class;
                    $middlewareClasses = [
                        AttachStorageMiddleware::class,
                        RewindResponseBodyMiddleware::class,
                    ];
                    break;
                case 'POST':
                    $handlerClass = Api::class;
                    $middlewareClasses = [
                        AttachStorageMiddleware::class,
                        AuthRequiredMiddleware::class,
                        AuthMiddleware::class,
                        RewindResponseBodyMiddleware::class,
                    ];
                    break;
                default:
                    throw new NotFound();
            }

            $handler = $this->container->get($handlerClass);

            if (!$handler instanceof RequestHandlerInterface) {
                return $responseFactory->createResponse(500);
            }
            foreach ($middlewareClasses as $middlewareClass) {
                $middleware = $this->container->get($middlewareClass);
                if (!$middleware instanceof MiddlewareInterface) {
                    continue;
                }
                $handler = new MiddlewareHandler($handler, $middleware);
            }
            return $handler->handle($request);
        } catch (NotFound) {
            return $this->notFound();
        } catch (Forbidden) {
            return $this->forbidden();
        } catch (Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    private function notFound(): ResponseInterface
    {
        return $this->getErrorResponse(404);
    }

    private function forbidden(): ResponseInterface
    {
        return $this->getErrorResponse(403);
    }

    private function serverError(Throwable $exception): ResponseInterface
    {
        return $this->getErrorResponse(500, $exception);
    }

    private function getErrorResponse(
        int $code,
        ?Throwable $exception = null
    ): ResponseInterface {
        $template = '<!DOCTYPE html><html lang="en"><head><title>Error: {ERROR}</title><body>'
            . '<h1>Error {CODE}</h1>'
            . '<p>{ERROR}</p>'
            . '{TRACE}'
            . '<hr>'
            . '<p>Macro SD</p>'
            . '</body></head></html>'
        ;
        $response = $this->responseFactory->createResponse($code);
        $html = str_replace('{CODE}', (string)$code, $template);
        $html = str_replace('{ERROR}', $response->getReasonPhrase(), $html);
        $trace = '';
        if (!is_null($exception) && $this->debug) {
            $trace = '<h2>details</h2>'
                . '<pre>'
                . $exception->getFile() . ':' . $exception->getLine() . "\r\n"
                . $exception->getMessage() . "\r\n"
                . "Trace: \r\n"
                . $exception->getTraceAsString()
                . '</pre>'
            ;
        }

        $html = str_replace('{TRACE}', $trace, $html);
        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', ['text/html'])
            ->withHeader('Cache-Control', ['no-store, no-cache'])
            ;
    }
}
