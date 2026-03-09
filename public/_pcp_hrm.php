<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PCP\Dev\HmrServer;
use PCP\Env;
use PCP\PCP;

$pcp = PCP::builder()
    ->roots([__DIR__ . '/../src/Components'])
    ->cacheDir(__DIR__ . '/../var/pcp')
    ->env(Env::Dev)
    ->hmr(true)
    ->build();

$server = new HmrServer($pcp);
$server->handle();