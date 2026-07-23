<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Camada local do backup (ver docs/01-architecture.md) — dentro do bind
        // mount `./backend:/var/www/html` (docker-compose.yml), sobrevive a
        // rebuilds do container por estar no host.
        //
        // `visibility => public` é obrigatório aqui, não só estético: sem ele,
        // o padrão do Flysystem é 'private' (diretório 0700/arquivo 0600) — o
        // `backup:run`/`backup:clean` agendado (container `scheduler`) cria
        // `storage/app/backup/` com essa permissão restritiva. Achado real na
        // loja do cliente (2026-07-23, Windows + Docker Desktop/WSL2): o bind
        // mount de volta pro host não preserva esse bit de permissão de forma
        // confiável entre containers/execuções, e o www-data de uma leitura
        // seguinte via `/settings/backup` (deep listing do Flysystem) recebia
        // "Permission denied" ao tentar abrir o próprio diretório que ele
        // mesmo tinha criado. `EnsureBackupDirectoryIsAccessibleAction`
        // complementa isto corrigindo diretórios que já existiam 0700 antes
        // desta correção.
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backup'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
