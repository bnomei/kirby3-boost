<?php

return function ($site) {
    // NOTE: this does not have a cached since cached content is assumed
    // NOTE: this collection does not include drafts
    return $site->index()->filter(function ($page) {
        return $page->hasBoost() === true;
    });
};
