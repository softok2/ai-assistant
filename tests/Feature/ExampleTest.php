<?php

declare(strict_types=1);

it('rejects the entry point without a signed link', function (): void {
    $response = $this->get('/');

    $response->assertStatus(400);
});
