<?php

declare(strict_types=1);

use Componenta\Stdlib\StringIterator;

it('finishes a multibyte traversal using forward and isEnd', function (): void {
    $iterator = new StringIterator('а🙂б');
    $characters = [];

    for ($limit = 0; $limit < 5 && !$iterator->isEnd(); ++$limit) {
        $characters[] = $iterator->current();
        $iterator->forward();
    }

    expect($characters)->toBe(['а', '🙂', 'б'])
        ->and($iterator->isEnd())->toBeTrue()
        ->and($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull()
        ->and($iterator->read())->toBe('');

    $iterator->backward();
    expect($iterator->current())->toBe('б')
        ->and($iterator->remaining())->toBe('б');
});

it('keeps a finished cursor in place when moving forward', function (string $value, int $extraSteps, int $forward): void {
    $iterator = new StringIterator($value);
    iterator_to_array($iterator);
    for ($step = 0; $step < $extraSteps; ++$step) {
        $iterator->next();
    }
    $position = $iterator->key();

    $iterator->forward($forward);

    expect($iterator->key())->toBe($position)
        ->and($iterator->isEnd())->toBeTrue()
        ->and($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull();
})->with(['', 'аб'])->with([0, 2])->with([0, 1, PHP_INT_MAX]);

it('keeps a zero step in place and clamps a large forward step to the end', function (): void {
    $iterator = new StringIterator('а🙂б');
    $iterator->moveTo(1)->forward(0);
    expect($iterator->key())->toBe(1)
        ->and($iterator->current())->toBe('🙂');

    $iterator->forward(PHP_INT_MAX);
    expect($iterator->key())->toBe(3)
        ->and($iterator->isEnd())->toBeTrue()
        ->and($iterator->remaining())->toBe('');

    $iterator->rewind();
    expect(iterator_to_array($iterator))->toBe(['а', '🙂', 'б']);
});
