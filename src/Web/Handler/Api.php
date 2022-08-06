<?php

declare(strict_types=1);

namespace App\Web\Handler;

use App\Exception\NotFound;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Api implements RequestHandlerInterface
{
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @throws NotFound
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var FilesystemOperator $storage */
        $storage = $request->getAttribute('storage');

        $query = $request->getQueryParams();
        $method = $query['method'] ?? null;
        $locationKey = 'location';
        if ($method === 'move' || $method === 'copy') {
            $locationKey = 'source';
        }
        $location = $query[$locationKey] ?? null;
        if (!is_string($method) || !is_string($location)) {
            throw new InvalidArgumentException();
        }
        try {
            switch ($method) {
                case 'fileExists':
                    $result = $storage->fileExists($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['fileExists' => $result]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'directoryExists':
                    $result = $storage->directoryExists($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['directoryExists' => $result]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'write':
                    $visibility = $query['visibility'] ?? Visibility::PRIVATE;
                    if ($visibility !== Visibility::PRIVATE && $visibility !== Visibility::PUBLIC) {
                        $visibility = Visibility::PRIVATE;
                    }

                    $stream = $request->getBody()->detach();
                    $storage->writeStream($location, $stream, [Config::OPTION_VISIBILITY => $visibility]);
                    fclose($stream);
                    return $this->responseFactory->createResponse(201);
                case 'read':
                    $stream = $storage->readStream($location);
                    $response = $this->responseFactory->createResponse(200);
                    return $response
                        ->withHeader('Content-Type', ['application/octet-stream'])
                        ->withBody($this->streamFactory->createStreamFromResource($stream));
                case 'delete':
                    $storage->delete($location);
                    return $this->responseFactory->createResponse(204);
                case 'deleteDirectory':
                    $storage->deleteDirectory($location);
                    return $this->responseFactory->createResponse(204);
                case 'createDirectory':
                    $directoryVisibility = $query['directory_visibility'] ?? Visibility::PRIVATE;
                    if ($directoryVisibility !== Visibility::PRIVATE && $directoryVisibility !== Visibility::PUBLIC) {
                        $directoryVisibility = Visibility::PRIVATE;
                    }
                    $storage->createDirectory($location, [Config::OPTION_DIRECTORY_VISIBILITY => $directoryVisibility]);
                    return $this->responseFactory->createResponse(201);
                case 'setVisibility':
                    $visibility = $query['visibility'] ?? Visibility::PRIVATE;
                    if ($visibility !== Visibility::PRIVATE && $visibility !== Visibility::PUBLIC) {
                        $visibility = Visibility::PRIVATE;
                    }
                    $storage->setVisibility($location, $visibility);
                    return $this->responseFactory->createResponse(200);
                case 'visibility':
                    $visibility = $storage->visibility($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['visibility' => $visibility]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'mimeType':
                    $mimeType = $storage->mimeType($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['mimeType' => $mimeType]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'lastModified':
                    $lastModified = $storage->lastModified($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['lastModified' => $lastModified]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'fileSize':
                    $fileSize = $storage->fileSize($location);
                    $response = $this->responseFactory->createResponse(200);
                    $response->getBody()->write(json_encode(['fileSize' => $fileSize]));
                    return $response
                        ->withHeader('Content-Type', ['application/json']);
                case 'listContents':
                    $deep = ($query['deep'] ?? 'false') === 'true';
                    /** @var StorageAttributes[] $list */
                    $list = $storage->listContents($location, $deep);
                    $response = $this->responseFactory->createResponse(200);
                    $stream = fopen('php://temp', 'w+');
                    foreach ($list as $item) {
                        $extra = $item->extraMetadata();
                        $row = [
                            'type' => $item->type(),
                            'path' => $item->path(),
                            'visibility' => $item->visibility(),
                            'lastModified' => $item->lastModified(),
                            'fileSize' => null,
                            'mimeType' => null,
                            'extraMetadata' => empty($extra) ? null : json_encode($extra),
                        ];
                        if ($item instanceof FileAttributes) {
                            $row['fileSize'] = $item->fileSize();
                            $row['mimeType'] = $item->mimeType();
                        }
                        fputcsv($stream, array_values($row));
                    }
                    return $response
                        ->withHeader('Content-Type', ['text/csv'])
                        ->withBody($this->streamFactory->createStreamFromResource($stream));
                case 'move':
                case 'copy':
                    $destination = $query['destination'] ?? null;
                    if (!is_string($destination)) {
                        throw new InvalidArgumentException();
                    }
                    $visibility = $query['visibility'] ?? Visibility::PRIVATE;
                    if ($visibility !== Visibility::PRIVATE && $visibility !== Visibility::PUBLIC) {
                        $visibility = Visibility::PRIVATE;
                    }
                    $directoryVisibility = $query['directory_visibility'] ?? Visibility::PRIVATE;
                    if ($directoryVisibility !== Visibility::PRIVATE && $directoryVisibility !== Visibility::PUBLIC) {
                        $directoryVisibility = Visibility::PRIVATE;
                    }
                    $fn = [$storage, $method];
                    $fn($location, $destination, [
                        Config::OPTION_VISIBILITY => $visibility,
                        Config::OPTION_DIRECTORY_VISIBILITY => $directoryVisibility
                    ]);
                    return $this->responseFactory->createResponse(200);
            }
        } catch (FilesystemException $exception) {
            $response = $this->responseFactory->createResponse(500);
            $body = $response->getBody();
            $body->write(json_encode([
                'error' => $this->getFilesystemExceptionType($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]));
            return $response;
        }
        throw new NotFound();
    }

    private function getFilesystemExceptionType(FilesystemException $exception): string
    {
        $class = get_class($exception);
        $array = explode('\\', $class);
        return array_pop($array);
    }
}
