<?php

declare(strict_types=1);

namespace App\Service\StorageManager;

use App\Adapter\VisibilityAdapterDecorator;
use App\Exception\StorageNotConfigured;
use App\Service\Database\Database;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

final class StorageManager
{
    private Database $db;
    private array $config;
    private string $root;
    private array $storages = [];

    public function __construct(Database $db, array $config, string $root)
    {
        $this->db = $db;
        $this->config = $config;
        $this->root = $root;
    }

    public function getStorage(string $project): FilesystemOperator
    {
        $dsn = $this->config[$project]['storage'] ?? null;
        if (!is_string($dsn)) {
            throw new StorageNotConfigured();
        }
        if (!array_key_exists($dsn, $this->storages)) {
            $this->storages[$dsn] = $this->createStorage($dsn, $project);
        }
        return $this->storages[$dsn];
    }

    private function createStorage(string $dsn, string $project): FilesystemOperator
    {
        $adapter = $this->createAdapter($dsn, $project);
        return new Filesystem($adapter);
    }

    private function createAdapter(string $dsn, string $project): FilesystemAdapter
    {
        $config = parse_url($dsn);
        $args = [];
        if (array_key_exists('query', $config)) {
            parse_str($config['query'], $args);
        }

        $scheme = $config['scheme'] ?? null;
        $arr = explode('+', trim($scheme ?? ''));
        $type = $arr[0];
        $scheme = array_key_exists(1, $arr) ? $arr[1] : $type;
        $path = $config['path'] ?? '/';
        switch ($type) {
            case 'file':
                if (str_starts_with($path, '//')) {
                    $path = DIRECTORY_SEPARATOR . ltrim($path, '/');
                } else {
                    $path = $this->root . DIRECTORY_SEPARATOR . ltrim($path, '/');
                }
                return new LocalFilesystemAdapter(rtrim($path, '/'));
            case 'memory':
                return new InMemoryFilesystemAdapter();
            case 'webdav':
                $host = $scheme . '://' . $config['host'];
                if (array_key_exists('port', $config)) {
                    $host .= ':' . $config['port'];
                }
                $client = new Client([
                    'baseUri' => $host . '/',
                    'userName' => $config['user'] ?? null,
                    'password' => $config['pass'] ?? null,
                ]);
                $webdav = new WebDAVAdapter($client, $path, WebDAVAdapter::ON_VISIBILITY_IGNORE);
                return new VisibilityAdapterDecorator($webdav, $this->db, $project);
        }
        throw new StorageNotConfigured();
    }
}
