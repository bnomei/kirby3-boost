<?php

if (! class_exists('Bnomei\Autoloader')) {
    require_once __DIR__ . '/classes/Autoloader.php';
}

if (! function_exists('autoloader')) {
    function autoloader(string $dir, array $options = []): \Bnomei\Autoloader
    {
        $options['dir'] = $dir;
        return \Bnomei\Autoloader::singleton($options);
    }
}
