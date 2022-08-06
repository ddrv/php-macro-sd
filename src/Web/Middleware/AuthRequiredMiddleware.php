<?php

declare(strict_types=1);

namespace App\Web\Middleware;

use App\Exception\Forbidden;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthRequiredMiddleware implements MiddlewareInterface
{
    /**
     * @throws Forbidden
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (is_null($request->getAttribute('user'))) {
            throw new Forbidden();
        }

        return $handler->handle($request);
    }
}
