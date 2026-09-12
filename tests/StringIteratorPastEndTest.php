<?php

declare(strict_types=1);

use Componenta\Stdlib\StringIterator;

it('keeps a past-the-end cursor past the end when moving forward', function (): void {
    $iterator = new StringIterator('ab');
    $iterator->next();
    $iterator->next();

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull();

    $iterator->forward(0);

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull();

    $iterator->forward();

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull();
});
