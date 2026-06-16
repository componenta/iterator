<?php

declare(strict_types=1);

namespace Componenta\Stdlib\Tests;

use ArrayIterator;
use Componenta\Stdlib\ReplayableIterator;
use Generator;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Traversable;

final class ReplayableIteratorTest extends TestCase
{
    // =========================================================================
    // Basic functionality with arrays
    // =========================================================================

    #[Test]
    public function iteratesOverArrayCorrectly(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $iterator = new ReplayableIterator($source);

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        self::assertSame($source, $result);
    }

    #[Test]
    public function arrayIsImmediatelyTraversed(): void
    {
        $iterator = new ReplayableIterator([1, 2, 3]);

        self::assertTrue($iterator->traversed);
        self::assertSame(3, $iterator->cacheSize);
    }

    #[Test]
    public function handlesEmptyArray(): void
    {
        $iterator = new ReplayableIterator([]);

        self::assertFalse($iterator->valid());
        self::assertSame(0, $iterator->count());
        self::assertSame([], $iterator->toArray());
    }

    // =========================================================================
    // Basic functionality with iterators
    // =========================================================================

    #[Test]
    public function iteratesOverIteratorCorrectly(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $iterator = new ReplayableIterator(new ArrayIterator($source));

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        self::assertSame($source, $result);
    }

    #[Test]
    public function iteratorIsLazilyTraversed(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3]));

        self::assertFalse($iterator->traversed);
        self::assertSame(0, $iterator->cacheSize);

        $iterator->current(); // Cache first element
        self::assertSame(1, $iterator->cacheSize);
    }

    #[Test]
    public function handlesIteratorAggregate(): void
    {
        $aggregate = new class implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                return new ArrayIterator(['x' => 10, 'y' => 20]);
            }
        };

        $iterator = new ReplayableIterator($aggregate);
        $result = $iterator->toArray(preserveKeys: true);

        self::assertSame(['x' => 10, 'y' => 20], $result);
    }

    #[Test]
    public function handlesNestedIteratorAggregate(): void
    {
        $nested = new class implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                return new class implements IteratorAggregate {
                    public function getIterator(): Traversable
                    {
                        return new ArrayIterator(['nested' => 'value']);
                    }
                };
            }
        };

        $iterator = new ReplayableIterator($nested);
        $result = $iterator->toArray(preserveKeys: true);

        self::assertSame(['nested' => 'value'], $result);
    }

    // =========================================================================
    // Generator handling
    // =========================================================================

    #[Test]
    public function iteratesOverGeneratorCorrectly(): void
    {
        $generator = (function (): Generator {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'c' => 3;
        })();

        $iterator = new ReplayableIterator($generator);

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    #[Test]
    public function generatorCanBeReiteratedAfterCaching(): void
    {
        $generator = (function (): Generator {
            yield 1;
            yield 2;
            yield 3;
        })();

        $iterator = new ReplayableIterator($generator);

        // First iteration
        $first = [];
        foreach ($iterator as $value) {
            $first[] = $value;
        }

        // Second iteration (from cache)
        $iterator->rewind();
        $second = [];
        foreach ($iterator as $value) {
            $second[] = $value;
        }

        self::assertSame($first, $second);
        self::assertSame([1, 2, 3], $first);
    }

    // =========================================================================
    // Critical bug fix: next() without current()
    // =========================================================================

    #[Test]
    public function nextWithoutCurrentDoesNotLoseElements(): void
    {
        $generator = (function (): Generator {
            yield 'first';
            yield 'second';
            yield 'third';
        })();

        $iterator = new ReplayableIterator($generator);

        // Call next() without calling current() first
        self::assertTrue($iterator->valid());
        $iterator->next();

        // Now get remaining elements
        $result = [];
        while ($iterator->valid()) {
            $result[] = $iterator->current();
            $iterator->next();
        }

        // Rewind and get all elements
        $iterator->rewind();
        $all = $iterator->toArray();

        self::assertSame(['first', 'second', 'third'], $all);
    }

    #[Test]
    public function multipleNextCallsWithoutCurrentPreserveAllElements(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3, 4, 5]));

        // Skip to position 3 without reading
        $iterator->next();
        $iterator->next();
        $iterator->next();

        self::assertSame(4, $iterator->current());

        // Rewind and verify all elements are cached
        $iterator->rewind();
        self::assertSame([1, 2, 3, 4, 5], $iterator->toArray());
    }

    // =========================================================================
    // Critical bug fix: null keys
    // =========================================================================

    #[Test]
    public function handlesNullKeysCorrectly(): void
    {
        $iteratorWithNullKey = new class implements \Iterator {
            private array $items = [
                [null, 'null-value'],
                ['a', 'a-value'],
            ];
            private int $position = 0;

            public function current(): mixed
            {
                return $this->items[$this->position][1] ?? null;
            }

            public function key(): mixed
            {
                return $this->items[$this->position][0] ?? null;
            }

            public function next(): void
            {
                $this->position++;
            }

            public function valid(): bool
            {
                return $this->position < count($this->items);
            }

            public function rewind(): void
            {
                $this->position = 0;
            }
        };

        $iterator = new ReplayableIterator($iteratorWithNullKey);

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[] = [$key, $value];
        }

        self::assertSame([
            [null, 'null-value'],
            ['a', 'a-value'],
        ], $result);
    }

    // =========================================================================
    // Critical bug fix: duplicate keys
    // =========================================================================

    #[Test]
    public function handlesDuplicateKeysCorrectly(): void
    {
        $iteratorWithDuplicateKeys = new class implements \Iterator {
            private array $items = [
                ['a', 1],
                ['a', 2], // Duplicate key
                ['b', 3],
            ];
            private int $position = 0;

            public function current(): mixed
            {
                return $this->items[$this->position][1] ?? null;
            }

            public function key(): mixed
            {
                return $this->items[$this->position][0] ?? null;
            }

            public function next(): void
            {
                $this->position++;
            }

            public function valid(): bool
            {
                return $this->position < count($this->items);
            }

            public function rewind(): void
            {
                $this->position = 0;
            }
        };

        $iterator = new ReplayableIterator($iteratorWithDuplicateKeys);

        // All three items should be present
        self::assertSame(3, $iterator->count());

        // Collect all items
        $iterator->rewind();
        $result = [];
        foreach ($iterator as $key => $value) {
            $result[] = [$key, $value];
        }

        self::assertSame([
            ['a', 1],
            ['a', 2],
            ['b', 3],
        ], $result);
    }

    #[Test]
    public function toArrayWithPreserveKeysOverwritesDuplicates(): void
    {
        $iteratorWithDuplicateKeys = new class implements \Iterator {
            private array $items = [
                ['a', 1],
                ['a', 2],
            ];
            private int $position = 0;

            public function current(): mixed
            {
                return $this->items[$this->position][1] ?? null;
            }

            public function key(): mixed
            {
                return $this->items[$this->position][0] ?? null;
            }

            public function next(): void
            {
                $this->position++;
            }

            public function valid(): bool
            {
                return $this->position < count($this->items);
            }

            public function rewind(): void
            {
                $this->position = 0;
            }
        };

        $iterator = new ReplayableIterator($iteratorWithDuplicateKeys);

        // toArray without preserveKeys keeps all values
        self::assertSame([1, 2], $iterator->toArray(preserveKeys: false));

        // toArray with preserveKeys - last value wins
        self::assertSame(['a' => 2], $iterator->toArray(preserveKeys: true));
    }

    // =========================================================================
    // rewind() behavior
    // =========================================================================

    #[Test]
    public function rewindAfterPartialTraversalWorks(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3, 4, 5]));

        // Partial traversal
        $iterator->current(); // 1
        $iterator->next();
        $iterator->current(); // 2
        $iterator->next();

        // Rewind
        $iterator->rewind();

        // Should start from beginning
        self::assertSame(0, $iterator->key());
        self::assertSame(1, $iterator->current());

        // Full traversal should work
        self::assertSame([1, 2, 3, 4, 5], $iterator->toArray());
    }

    #[Test]
    public function multipleRewindsWork(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator(['a', 'b', 'c']));

        for ($i = 0; $i < 3; $i++) {
            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
            }
            self::assertSame(['a', 'b', 'c'], $result);
            $iterator->rewind();
        }
    }

    // =========================================================================
    // toArray() behavior
    // =========================================================================

    #[Test]
    public function toArrayForcesFullTraversal(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3]));

        self::assertFalse($iterator->traversed);
        $iterator->toArray();
        self::assertTrue($iterator->traversed);
    }

    #[Test]
    public function toArrayPreservesPositionAfterTraversal(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3, 4, 5]));

        // Move to position 2
        $iterator->current();
        $iterator->next();
        $iterator->current();
        $iterator->next();

        // Force full traversal
        $iterator->toArray();

        // Position should be preserved
        self::assertSame(2, $iterator->currentPosition);
        self::assertSame(3, $iterator->current());
    }

    #[Test]
    #[DataProvider('toArrayDataProvider')]
    public function toArrayReturnsCorrectFormat(array $source, bool $preserveKeys, array $expected): void
    {
        $iterator = new ReplayableIterator($source);
        self::assertSame($expected, $iterator->toArray($preserveKeys));
    }

    public static function toArrayDataProvider(): iterable
    {
        yield 'sequential without preserve' => [
            [1, 2, 3],
            false,
            [1, 2, 3],
        ];

        yield 'sequential with preserve' => [
            [1, 2, 3],
            true,
            [0 => 1, 1 => 2, 2 => 3],
        ];

        yield 'associative without preserve' => [
            ['a' => 1, 'b' => 2],
            false,
            [1, 2],
        ];

        yield 'associative with preserve' => [
            ['a' => 1, 'b' => 2],
            true,
            ['a' => 1, 'b' => 2],
        ];
    }

    // =========================================================================
    // count() behavior
    // =========================================================================

    #[Test]
    public function countForcesFullTraversal(): void
    {
        $callCount = 0;
        $generator = (function () use (&$callCount): Generator {
            for ($i = 0; $i < 5; $i++) {
                $callCount++;
                yield $i;
            }
        })();

        $iterator = new ReplayableIterator($generator);

        self::assertSame(0, $callCount);
        self::assertSame(5, $iterator->count());
        self::assertSame(5, $callCount);
        self::assertTrue($iterator->traversed);
    }

    #[Test]
    public function countIsIdempotent(): void
    {
        $iterator = new ReplayableIterator([1, 2, 3]);

        self::assertSame(3, $iterator->count());
        self::assertSame(3, $iterator->count());
        self::assertSame(3, $iterator->count());
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function handlesEmptyIterator(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([]));

        self::assertFalse($iterator->valid());
        self::assertNull($iterator->current());
        self::assertNull($iterator->key());
        self::assertSame(0, $iterator->count());
        self::assertSame([], $iterator->toArray());
    }

    #[Test]
    public function handlesSingleElementIterator(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator(['only' => 'one']));

        self::assertTrue($iterator->valid());
        self::assertSame('only', $iterator->key());
        self::assertSame('one', $iterator->current());

        $iterator->next();

        self::assertFalse($iterator->valid());
        self::assertSame(1, $iterator->count());
    }

    #[Test]
    public function currentAndKeyReturnNullWhenInvalid(): void
    {
        $iterator = new ReplayableIterator([1]);

        $iterator->next(); // Move past the only element

        self::assertFalse($iterator->valid());
        self::assertNull($iterator->current());
        self::assertNull($iterator->key());
    }

    #[Test]
    public function worksWithMixedValueTypes(): void
    {
        $source = [
            'null' => null,
            'bool' => false,
            'int' => 0,
            'float' => 0.0,
            'string' => '',
            'array' => [],
            'object' => new \stdClass(),
        ];

        $iterator = new ReplayableIterator($source);
        $result = $iterator->toArray(preserveKeys: true);

        self::assertEquals($source, $result);
    }

    #[Test]
    public function releasesIteratorAfterFullTraversal(): void
    {
        $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3]));

        $iterator->toArray();

        // Using reflection to verify internal state
        $reflection = new \ReflectionClass($iterator);
        $property = $reflection->getProperty('iterable');
        $property->setAccessible(true);

        self::assertNull($property->getValue($iterator));
    }

    // =========================================================================
    // Countable interface compliance
    // =========================================================================

    #[Test]
    public function implementsCountableCorrectly(): void
    {
        $iterator = new ReplayableIterator([1, 2, 3, 4, 5]);

        self::assertInstanceOf(\Countable::class, $iterator);
        self::assertCount(5, $iterator);
    }

    // =========================================================================
    // Iterator interface compliance
    // =========================================================================

    #[Test]
    public function implementsIteratorCorrectly(): void
    {
        $iterator = new ReplayableIterator(['a' => 1, 'b' => 2]);

        self::assertInstanceOf(\Iterator::class, $iterator);

        $iterator->rewind();
        self::assertTrue($iterator->valid());
        self::assertSame('a', $iterator->key());
        self::assertSame(1, $iterator->current());

        $iterator->next();
        self::assertTrue($iterator->valid());
        self::assertSame('b', $iterator->key());
        self::assertSame(2, $iterator->current());

        $iterator->next();
        self::assertFalse($iterator->valid());
    }
}
