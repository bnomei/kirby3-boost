<?php

return function () {
    return page('humankind')->children()->filterBy('template', 'human');
};
