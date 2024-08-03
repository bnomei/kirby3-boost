<?php

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;

require __DIR__.'/../vendor/autoload.php';
echo (new Kirby)->render();
