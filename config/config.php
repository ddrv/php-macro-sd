<?php

$localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
$localConfig = file_exists($localConfigFile) ? require $localConfigFile : [];

return array_replace_recursive([
    'localhost' => [
        'users' => [
            'user' => '$2y$10$v/lO6ne/jW2/2sZwHPkiXuCGQelDSJY3vlOA4FX.t9nfzYTuzNkOO',
        ],
        'storage' => 'file://var/files',
    ],
], (array)$localConfig);
