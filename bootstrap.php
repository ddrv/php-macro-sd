<?php

use App\App;
use App\Service\Database\Database;
use App\Service\StorageManager\StorageManager;
use App\Web\Handler\Api;
use App\Web\Handler\Get;
use App\Web\Middleware\AttachStorageMiddleware;
use App\Web\Middleware\AuthMiddleware;
use App\Web\Middleware\AuthRequiredMiddleware;
use Ddrv\Container\Container;
use Ddrv\ServerRequestWizard\ServerRequestWizard;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$root = __DIR__;
$config = require_once __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

$emptyDatabase = implode(DIRECTORY_SEPARATOR, [$root, 'dist', 'visibility.sqlite']);
$appDatabase = implode(DIRECTORY_SEPARATOR, [$root, 'var', 'visibility.sqlite']);
if (!is_dir($root . DIRECTORY_SEPARATOR . 'var')) {
    mkdir($root . DIRECTORY_SEPARATOR . 'var');
}
if (!file_exists($appDatabase)) {
    copy($emptyDatabase, $appDatabase);
}

$container = new Container();
$container->value('root', $root);
$container->value('config', $config);
$container->value('debug', (bool)getenv('DEBUG'));

$container->service(Psr17Factory::class, function () {
    return new Psr17Factory();
});
$container->bind(RequestFactoryInterface::class, Psr17Factory::class);
$container->bind(ResponseFactoryInterface::class, Psr17Factory::class);
$container->bind(ServerRequestFactoryInterface::class, Psr17Factory::class);
$container->bind(StreamFactoryInterface::class, Psr17Factory::class);
$container->bind(UploadedFileFactoryInterface::class, Psr17Factory::class);
$container->bind(UriFactoryInterface::class, Psr17Factory::class);

$container->service(ServerRequestWizard::class, function (ContainerInterface $container) {
    return new ServerRequestWizard(
        $container->get(ServerRequestFactoryInterface::class),
        $container->get(StreamFactoryInterface::class),
        $container->get(UploadedFileFactoryInterface::class)
    );
});

$container->service(AuthMiddleware::class, function (ContainerInterface $container) {
    return new AuthMiddleware($container->get('config'));
});
$container->service(AuthRequiredMiddleware::class, function () {
    return new AuthRequiredMiddleware();
});

$container->service(AttachStorageMiddleware::class, function (ContainerInterface $container) {
    return new AttachStorageMiddleware(
        $container->get(StorageManager::class)
    );
});

$container->service(Database::class, function (ContainerInterface $container) {
    $dsn = 'sqlite:' . $container->get('root')
        . DIRECTORY_SEPARATOR . 'var'
        . DIRECTORY_SEPARATOR . 'visibility.sqlite'
    ;
    return new Database($dsn);
});

$container->service(StorageManager::class, function (ContainerInterface $container) {
    return new StorageManager(
        $container->get(Database::class),
        $container->get('config'),
        $container->get('root')
    );
});

$container->service(App::class, function (ContainerInterface $container) {
    return new App($container);
});

$container->service(Get::class, function (ContainerInterface $container) {
    return new Get(
        $container->get(ResponseFactoryInterface::class),
        $container->get(StreamFactoryInterface::class),
    );
});

$container->service(Api::class, function (ContainerInterface $container) {
    return new Api(
        $container->get(ResponseFactoryInterface::class),
        $container->get(StreamFactoryInterface::class)
    );
});

return $container;
