<?php

declare(strict_types=1);

it('renders api errors as json even without an accept header', function (): void {
    $this->get('/api/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
