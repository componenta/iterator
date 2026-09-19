<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('rejects overlapping source reads without changing the replay or manual position', function (string $operation): void {
    $source = new class($operation) implements Iterator {
        private int $position = 0;
        private bool $suspended = false;
        public function __construct(private string $operation) {}
        public function rewind(): void { $this->position = 0; }
        public function next(): void { ++$this->position; $this->pause('next'); }
        public function valid(): bool { $this->pause('valid'); return $this->position < 2; }
        public function key(): int { $this->pause('key'); return $this->position; }
        public function current(): int { $this->pause('current'); return [10, 20][$this->position]; }
        private function pause(string $operation): void {
            if (!$this->suspended && $this->operation === $operation && Fiber::getCurrent() !== null) {
                $this->suspended = true;
                Fiber::suspend('source read');
            }
        }
    };
    $replay = new ReplayableIterator($source);
    $cursor = $replay->cursor();
    $reader = new Fiber(static function () use ($cursor, $operation): int {
        $first = $cursor->current();
        if ($operation === 'next') {
            $cursor->next();
            return $cursor->current();
        }
        return $first;
    });

    expect($reader->start())->toBe('source read');
    try {
        if ($operation === 'next') {
            expect($replay->cursor()->current())->toBe(10);
        }
        $other = new Fiber(static fn () => $replay->toArray());
        expect(fn () => $other->start())->toThrow(LogicException::class, 'Source read is already in progress')
            ->and(fn () => count($replay))->toThrow(LogicException::class, 'Source read is already in progress')
            ->and(fn () => $replay->next())->toThrow(LogicException::class, 'Source read is already in progress');
    } finally {
        $reader->resume();
    }

    expect($reader->getReturn())->toBe($operation === 'next' ? 20 : 10)
        ->and($replay->current())->toBe(10)
        ->and($replay->toArray())->toBe([10, 20])
        ->and(iterator_to_array($replay->cursor(), false))->toBe([10, 20]);
})->with(['valid', 'key', 'current', 'next']);

it('keeps the original source failure after rejecting an overlapping reader', function (): void {
    $failure = new RuntimeException('original source failure');
    $replay = new ReplayableIterator((static function () use ($failure): Generator {
        Fiber::suspend('source read');
        yield 10;
        throw $failure;
    })());
    $reader = new Fiber(static fn () => $replay->toArray());
    expect($reader->start())->toBe('source read');
    expect(fn () => $replay->toArray())->toThrow(LogicException::class, 'Source read is already in progress');

    foreach ([
        static fn () => $reader->resume(),
        static fn () => $replay->toArray(),
        static fn () => count($replay),
        static fn () => $replay->cursor()->current(),
    ] as $read) {
        $caught = null;
        try {
            $read();
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
    }
});
