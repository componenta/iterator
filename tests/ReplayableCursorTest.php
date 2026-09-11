<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('provides independent cursors over one lazy source', function (): void {
    $source = (static function (): Generator {
        yield 'first' => 1;
        yield 'second' => 2;
        yield 'third' => 3;
    })();

    $replayable = new ReplayableIterator($source);
    $first = $replayable->cursor();
    $second = $replayable->cursor();

    expect($first->key())->toBe('first')
        ->and($first->current())->toBe(1)
        ->and($second->key())->toBe('first')
        ->and($second->current())->toBe(1);

    $first->next();
    expect($first->key())->toBe('second')
        ->and($first->current())->toBe(2)
        ->and($second->key())->toBe('first')
        ->and($second->current())->toBe(1);

    $second->next();
    expect($second->key())->toBe('second')
        ->and($second->current())->toBe(2);

    $first->next();
    $second->next();

    expect($first->key())->toBe('third')
        ->and($first->current())->toBe(3)
        ->and($second->key())->toBe('third')
        ->and($second->current())->toBe(3);
});

it('replays duplicate and null keys independently', function (): void {
    $source = new class implements Iterator {
        private array $entries = [
            [null, 'null'],
            ['same', 'first'],
            ['same', 'second'],
        ];
        private int $position = 0;

        public function current(): mixed { return $this->entries[$this->position][1] ?? null; }
        public function key(): mixed { return $this->entries[$this->position][0] ?? null; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->entries); }
        public function rewind(): void { $this->position = 0; }
    };

    $replayable = new ReplayableIterator($source);

    expect(iterator_to_array($replayable->cursor(), false))->toBe(['null', 'first', 'second'])
        ->and(iterator_to_array($replayable->cursor(), false))->toBe(['null', 'first', 'second']);
});

it('rejects cyclic IteratorAggregate chains instead of unwrapping forever', function (): void {
    $source = new class implements IteratorAggregate {
        private int $calls = 0;

        public function getIterator(): Traversable
        {
            $this->calls++;

            if ($this->calls > 1) {
                throw new LogicException('cycle guard reached');
            }

            return $this;
        }
    };

    expect(fn() => new ReplayableIterator($source))
        ->toThrow(RuntimeException::class, 'IteratorAggregate cycle detected');
});

it('rejects multi-object IteratorAggregate cycles', function (): void {
    $factory = static fn() => new class implements IteratorAggregate {
        public ?IteratorAggregate $next = null;

        public function getIterator(): Traversable
        {
            return $this->next ?? new EmptyIterator();
        }
    };

    $first = $factory();
    $second = $factory();
    $first->next = $second;
    $second->next = $first;

    expect(fn() => new ReplayableIterator($first))
        ->toThrow(RuntimeException::class, 'IteratorAggregate cycle detected');
});
