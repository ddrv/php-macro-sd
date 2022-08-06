<?php

declare(strict_types=1);

namespace App\Web\Middleware;

use App\Exception\NotFound;
use App\Service\StorageManager\StorageManager;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AttachStorageMiddleware implements MiddlewareInterface
{
    private StorageManager $storageManager;

    public function __construct(StorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $request->getUri()->getHost();
        $storage = $this->storageManager->getStorage($host);
        return $handler->handle($request->withAttribute('storage', $storage));
    }
}
