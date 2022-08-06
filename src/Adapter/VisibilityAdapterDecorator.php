<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Service\Database\Database;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\Visibility;
use PDOException;

final class VisibilityAdapterDecorator implements FilesystemAdapter
{
    private FilesystemAdapter $adapter;
    private Database $db;
    private string $project;

    public function __construct(
        FilesystemAdapter $adapter,
        Database $db,
        string $project
    ) {
        $this->adapter = $adapter;
        $this->db = $db;
        $this->project = $project;
    }

    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
        $this->setVisibility($path, $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->adapter->writeStream($path, $contents, $config);
        $this->setVisibility($path, $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE));
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
        $visibility = $config->get(
            Config::OPTION_DIRECTORY_VISIBILITY,
            $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE)
        );
        $this->setVisibility($path, $visibility);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $path = '/' . ltrim(rtrim($path, '/'), '/');
        $this->db->begin();
        try {
            if ($visibility === Visibility::PUBLIC) {
                $query = 'DELETE FROM private WHERE path = ? and project = ?;';
            } else {
                $query = 'INSERT INTO private (path, project) VALUES (?, ?) ON CONFLICT DO NOTHING;';
            }
            $this->db->execute($query, [$path, $this->project]);
            $this->db->commit();
        } catch (PDOException $exception) {
            $this->db->rollback();
            throw UnableToSetVisibility::atLocation($path, '', $exception);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        $path = '/' . ltrim(rtrim($path, '/'), '/');
        $row = $this->db->fetch('SELECT * FROM private WHERE path = ? AND project = ?;', [$path, $this->project]);
        $visibility = is_null($row) ? Visibility::PUBLIC : Visibility::PRIVATE;
        return new FileAttributes($path, visibility: $visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->adapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
        $visibility = $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
        if ($this->adapter->directoryExists($destination)) {
            $visibility = $config->get(
                Config::OPTION_DIRECTORY_VISIBILITY,
                $visibility
            );
        }
        $this->setVisibility($source, Visibility::PUBLIC);
        $this->setVisibility($destination, $visibility);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
        $visibility = $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
        if ($this->adapter->directoryExists($destination)) {
            $visibility = $config->get(
                Config::OPTION_DIRECTORY_VISIBILITY,
                $visibility
            );
        }
        $this->setVisibility($destination, $visibility);
    }
}
