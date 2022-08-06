<?php

declare(strict_types=1);

namespace App\Web\Handler;

use App\Exception\Forbidden;
use App\Exception\NotFound;
use DateTimeImmutable;
use DateTimeZone;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Get implements RequestHandlerInterface
{
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;
    private DateTimeZone $gmt;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->gmt = new DateTimeZone('GMT');
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var FilesystemOperator $storage */
        $storage = $request->getAttribute('storage');
        $location = $request->getUri()->getPath();
        try {
            if (!$storage->fileExists($location)) {
                throw new NotFound();
            }

            $visibility = $storage->visibility($location);

            if ($visibility === Visibility::PRIVATE) {
                throw new Forbidden();
            }

            $stream = $storage->readStream($location);
            $size = $storage->fileSize($location);
            $time = $storage->lastModified($location);
            $lastModified = DateTimeImmutable::createFromFormat('U', (string)$time)
                ->setTimezone($this->gmt)
                ->format(DATE_RFC7231)
            ;

            try {
                $mimeType = $storage->mimeType($location);
            } catch (FilesystemException) {
                $mimeType = 'application/octet-stream';
            }

            $response = $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', [$mimeType])
                ->withHeader('LastModified', [$lastModified])
                ->withHeader('Cache-Control', ['public, max-age=31536000'])
                ->withHeader('Content-Length', [(string)$size])
            ;
            if ($request->getMethod() !== 'HEAD') {
                $body = $this->streamFactory->createStreamFromResource($stream);
                $response = $response->withBody($body);
            }
            return $response;
        } catch (FilesystemException) {
            return $this->responseFactory->createResponse(500);
        }
    }
}
