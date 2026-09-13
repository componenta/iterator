<?php

declare(strict_types=1);

$package = dirname(__DIR__);
$local = $package . '/vendor/autoload.php';
$loader = require is_file($local) ? $local : dirname($package) . '/var-export/vendor/autoload.php';
if (!is_file($local)) {
    $loader->addPsr4('Componenta\\Arrayable\\', dirname($package, 2) . '/vendor/componenta/arrayable/src');
}
$loader->addPsr4('Componenta\\Stdlib\\', $package . '/src', true);
$loader->addPsr4('Componenta\\Stdlib\\Tests\\', __DIR__, true);
