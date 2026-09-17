<?php

declare(strict_types=1);

use Laravel\Telescope\Telescope;

it('hides the authorization header from telescope in every environment', function () {
    expect(in_array('authorization', Telescope::$hiddenRequestHeaders, true))->toBeTrue();
});
