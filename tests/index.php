<?php

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;

function patchKirbyHelpers()
{
    $h = __DIR__.'/kirby/config/helpers.php';
    if (file_exists($h)) {
        // open file and change a function name dump to xdump and save file again
        $content = file_get_contents($h);
        $content = str_replace('function dump(', 'function xdump(', $content);
        $content = str_replace('function e(', 'function xe(', $content);
        file_put_contents($h, $content);
    }
}
patchKirbyHelpers();

require __DIR__.'/../vendor/autoload.php';
echo (new Kirby())->render();
