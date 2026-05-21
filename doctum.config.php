<?php

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;

$dir = __DIR__ . '/src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->notName('CustomLoggerClass.php')
    ->in($dir);

return new Doctum($iterator, [
    'title'             => 'PHP SDK by CNIC',
    'build_dir'         => __DIR__ . '/docs',
    'cache_dir'         => __DIR__ . '/build/api-cache',
    'source_dir'        => __DIR__ . '/',
    'remote_repository' => new GitHubRemoteRepository('centralnicgroup-opensource/rtldev-middleware-php-sdk', __DIR__),
]);
