<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class GoogleDriveServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }


    public function boot(): void
    {
        Storage::extend('google', function ($app, $config)
        {
            $client = new \Google\Client();
            $client->setAuthConfig('config/rheybrook-marketing-1fb09c3c23a5.json');
            $client->setScopes(\Google\Service\Drive::DRIVE);
            $client->setConfig('debug', true);

            $service = new \Google\Service\Drive($client);
            $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folder']);
            $driver = new \League\Flysystem\Filesystem($adapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($driver, $adapter);
        });
    }
}
