<?php

return function () {
    return site()->index()->filterBy('template', 'factory');
};