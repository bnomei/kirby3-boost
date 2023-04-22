<?php

// this example uses lapse plugin to build a static cache (makes panel faster) and filter the base kv collection by template.
// performance is mediocre at best since each object will be resolved.

return fn () => lapseStatic(__FILE__, function () {
    return collection('boostidkvs')->filterBy(
        function ($kvObject) {
            if ($kvObject->template === 'post') {
                return boost($kvObject->value);
            };
            return null;
        }
    );
});
