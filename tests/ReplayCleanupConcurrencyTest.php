<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('does not report successful completion before suspended source cleanup finishes', function (bool $fail): void {
    $failure = new RuntimeException('Source cleanup failed.');
    $replay = new ReplayableIterator(new class($fail, $failure) implements Iterator {
        public function __construct(private bool $fail, private Throwable $failure) {}
        public function rewind(): void {}
        public function current(): mixed { return null; }
        public function key(): mixed { return null; }
        public function next(): void {}
        public function valid(): bool { return false; }
        public function __destruct()
        {
            Fiber::suspend('cleaning up');
            if ($this->fail) {
                throw $this->failure;
            }
        }
    });
    $reader = new Fiber(static fn () => $replay->toArray());
    expect($reader->start())->toBe('cleaning up');

    try {
        expect($replay->traversed)->toBeFalse()
            ->and(fn () => $replay->count())->toThrow(LogicException::class, 'Source read is already in progress');
    } finally {
        if ($fail) {
            expect(fn () => $reader->resume())->toThrow($failure);
        } else {
            $reader->resume();
        }
    }

    if ($fail) {
        expect($replay->traversed)->toBeFalse()
            ->and(fn () => $replay->count())->toThrow($failure);
    } else {
        expect($reader->getReturn())->toBe([])
            ->and($replay->traversed)->toBeTrue()
            ->and(count($replay))->toBe(0);
    }
})->with([false, true]);
