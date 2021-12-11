<?php

return function ($site) {
    $paths = kirby()->cache('bnomei.boost')->get('siteindexfolders');

    if (!$paths) {
        $drafts = option('bnomei.boost.drafts');
        $root = kirby()->roots()->content();

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        $paths = [];
        foreach ($iter as $path => $dir) {
            if ($dir->isDir()) {
                if (!$drafts && strstr($path, '_drafts') === false) {
                    continue;
                }
                $paths[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }
        kirby()->cache('bnomei.boost')->set('siteindexfolders', $paths, 1);
    }

    return $paths;
};
