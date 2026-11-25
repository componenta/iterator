<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('preserves the original source error when releasing the failed source also throws', function (): void {
    $failure = new RuntimeException('Primary source error.');
    $replay = new ReplayableIterator(new class($failure) implements Iterator {
        public function __construct(private readonly Throwable $failure) {}
        public function rewind(): void {}
        public function current(): mixed { return null; }
        public function key(): mixed { return null; }
        public function next(): void {}
        public function valid(): bool { throw $this->failure; }
        public function __destruct() { throw new RuntimeException('Secondary cleanup error.'); }
    });

    foreach ([
        static fn () => $replay->current(),
        static fn () => $replay->cursor()->current(),
        static fn () => $replay->count(),
    ] as $operation) {
        $caught = null;
        try {
            $operation();
        } catch (Throwable $error) {
            $caught = $error;
        }

        expect($caught)->toBe($failure);
    }
});
