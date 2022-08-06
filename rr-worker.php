<?php

use App\App;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

/** @var ContainerInterface $container */
$container = require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

/** @var App $app */
$app = $container->get(App::class);

$worker = new PSR7Worker(
    Worker::create(),
    $container->get(ServerRequestFactoryInterface::class),
    $container->get(StreamFactoryInterface::class),
    $container->get(UploadedFileFactoryInterface::class)
);

while ($request = $worker->waitRequest()) {
    try {
        $response = $app->web($request);
        $worker->respond($response);
    } catch (Throwable $exception) {
        $worker->getWorker()->error((string)$exception);
    }
}
