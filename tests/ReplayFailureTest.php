<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('keeps the original source failure for every subsequent traversal', function (int $yieldCount, string $firstRead): void {
    $failure = new RuntimeException('source failed');
    $source = (static function () use ($yieldCount, $failure): Generator {
        for ($index = 0; $index < $yieldCount; ++$index) {
            yield $index;
        }
        throw $failure;
    })();
    $iterator = new ReplayableIterator($source);
    $reads = [
        'array' => static fn () => $iterator->toArray(),
        'count' => static fn () => count($iterator),
        'iteration' => static fn () => iterator_to_array($iterator),
    ];

    foreach ([$reads[$firstRead], ...array_values($reads)] as $read) {
        $caught = null;
        try {
            $read();
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
    }
})->with([0, 1, 2])->with(['array', 'count', 'iteration']);



it('shares the original failure between manual iteration and independent cursors', function (): void {
    $failure = new RuntimeException('shared source failed');
    $iterator = new ReplayableIterator((static function () use ($failure): Generator {
        yield 'first';
        yield 'second';
        throw $failure;
    })());
    $cursor = $iterator->cursor();
    $replay = $iterator->replay();
    expect($cursor->current())->toBe('first')
        ->and($replay->current())->toBe('first');

    $reads = [
        static fn () => $iterator->toArray(),
        static fn () => $cursor->next(),
        static fn () => $replay->next(),
        static fn () => $iterator->rewind(),
        static fn () => $iterator->current(),
        static fn () => iterator_to_array($iterator->cursor()),
    ];
    foreach ($reads as $read) {
        $caught = null;
        try {
            $read();
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
    }
});
