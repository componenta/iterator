# Componenta Iterator

Утилиты для итераторов: повторный обход одноразовых источников, обратный обход, обход строк и преобразование итератора в массив.

Используйте пакет, когда библиотеке нужно поведение итераторов без зависимости от collection framework.

## Установка

```bash
composer require componenta/iterator
```

## Связанные пакеты

| Пакет | Зачем нужен здесь |
|---|---|
| `componenta/stream-iterator` | Для больших PSR-7 потоков лучше использовать stream-iterator: он держит в памяти только текущий chunk. |
| `componenta/arrayable` | Некоторые итераторы раскрывают `toArray()` и могут использоваться как arrayable-объекты. |

## ReplayableIterator

`ReplayableIterator` оборачивает массивы, итераторы, iterator aggregates и generators. Он кеширует прочитанные значения, чтобы одноразовый источник можно было пройти повторно.

```php
use Componenta\Stdlib\ReplayableIterator;

$iterator = new ReplayableIterator((function () {
    yield 'a' => 1;
    yield 'b' => 2;
})());

$iterator->toArray(preserveKeys: true); // ['a' => 1, 'b' => 2]
```

Вызов `count()` или `toArray()` принудительно проходит весь исходный источник.

## Обратный обход

`ReverseIterator` итерирует iterable в обратном порядке. Он материализует значения внутри, поэтому рассчитан на конечные последовательности.

`ArrayListReverseIterator` — небольшой reverse iterator для list arrays.

## StringIterator

`StringIterator` итерирует строку с поддержкой кодировки и методами управления курсором:

- `moveTo()`
- `forward()`
- `backward()`
- `read()`
- `remaining()`
- `peek()`

## Преобразование в массив

`IteratorToArray` задаёт интерфейс `toArray()` для iterator-классов, которые могут материализовать своё содержимое.

## Память

Replayable и reverse iterators меняют память на возможность повторного или обратного обхода. Они подходят для конечных последовательностей. Для больших PSR-7 потоков используйте `componenta/stream-iterator`, который держит только текущий chunk.
