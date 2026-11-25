<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('never reports an interrupted source read as a completed sequence', function (bool $warm): void {
    $source = (static function () use ($warm): Generator {
        if ($warm) {
            yield 'first' => 1;
        }

        Fiber::suspend('source paused');
        yield 'next' => 2;
        yield 'last' => 3;
    })();
    $replay = new ReplayableIterator($source);
    if ($warm) {
        expect($replay->current())->toBe(1);
    }

    $reader = new Fiber(static fn () => $replay->toArray());
    expect($reader->start())->toBe('source paused');

    unset($reader);
    gc_collect_cycles();

    expect($replay->traversed)->toBeFalse();

    $failure = null;
    foreach ([
        static fn () => count($replay),
        static fn () => $replay->toArray(),
        static fn () => $replay->cursor()->current(),
        static fn () => $replay->current(),
    ] as $read) {
        $caught = null;
        try {
            $read();
        } catch (LogicException $exception) {
            $caught = $exception;
        }
        expect($caught)->toBeInstanceOf(LogicException::class);
        $failure ??= $caught;
        expect($caught)->toBe($failure);
    }

    expect($replay->traversed)->toBeFalse();
})->with(['before first yield' => false, 'after cached entry' => true]);

it('does not invalidate a suspended source merely because another reader was rejected', function (): void {
    $replay = new ReplayableIterator((static function (): Generator {
        yield 1;
        Fiber::suspend('source paused');
        yield 2;
    })());
    $reader = new Fiber(static fn () => $replay->toArray());
    expect($reader->start())->toBe('source paused');

    try {
        expect(fn () => count($replay))->toThrow(LogicException::class, 'Source read is already in progress')
            ->and($replay->cursor()->current())->toBe(1);
    } finally {
        $reader->resume();
    }

    expect($reader->getReturn())->toBe([1, 2])
        ->and($replay->toArray())->toBe([1, 2])
        ->and($replay->traversed)->toBeTrue();
});
