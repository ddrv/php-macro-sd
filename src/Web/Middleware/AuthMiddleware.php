<?php

declare(strict_types=1);

namespace App\Web\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader('Authorization')) {
            return $handler->handle($request);
        }

        $auth = $request->getHeaderLine('Authorization');
        if (!str_starts_with(strtolower($auth), 'basic ')) {
            return $handler->handle($request);
        }

        $encoded = trim(substr($auth, 6));
        $arr = explode(':', base64_decode($encoded), 2);
        if (count($arr) !== 2) {
            return $handler->handle($request);
        }

        [$user, $pass] = $arr;

        $host = $request->getUri()->getHost();
        if (!array_key_exists($host, $this->config)) {
            return $handler->handle($request);
        }

        if (!array_key_exists($user, $this->config[$host]['users'])) {
            return $handler->handle($request);
        }

        if (password_verify($pass, $this->config[$host]['users'][$user])) {
            return $handler->handle($request->withAttribute('user', $user));
        }

        return $handler->handle($request);
    }
}
