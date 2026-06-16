<?php
declare(strict_types=1);

namespace Cms\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * Benign base for classes the {@see ThemeFunctionBridge} stubs on demand.
 *
 * A ported theme may instantiate a class the runtime has never heard of — a
 * custom nav-menu walker, a widget, a framework helper whose defining file did
 * not load. Functions can be shimmed eagerly, but a missing class only surfaces
 * when PHP tries to resolve it, so the bridge registers a fallback autoloader
 * that defines the absent class as a subclass of this one.
 *
 * Like {@see LocalCmsSafeValue}, an instance is inert in every context: it can
 * be constructed with any arguments, any method or static call returns a safe
 * value, properties read back safe, and it counts/iterates/stringifies to empty.
 * So `new Unknown_Walker()` and `$walker->walk($items, $depth)` both no-op
 * instead of fataling.
 */
class LocalCmsSafeClass implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    public function __construct(mixed ...$arguments)
    {
    }

    public function __call(string $name, array $arguments): LocalCmsSafeValue
    {
        return LocalCmsSafeValue::instance();
    }

    public static function __callStatic(string $name, array $arguments): LocalCmsSafeValue
    {
        return LocalCmsSafeValue::instance();
    }

    public function __get(string $name): LocalCmsSafeValue
    {
        return LocalCmsSafeValue::instance();
    }

    public function __set(string $name, mixed $value): void
    {
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): LocalCmsSafeValue
    {
        return LocalCmsSafeValue::instance();
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    public function count(): int
    {
        return 0;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}
