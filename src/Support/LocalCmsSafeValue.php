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
 * A benign null-object returned by synthesized theme-function fallbacks.
 *
 * When a ported WordPress theme calls a helper the Local CMS runtime has no real
 * implementation for, the {@see ThemeFunctionBridge} defines a shim that returns
 * this object. The aim is "avoid all errors": whatever a template then does with
 * the value — echo it, iterate it, index it, count it, chain a method, read a
 * property, call it — must not fatal.
 *
 * So this object is deliberately inert in every context a template might use it:
 *
 *   echo $v;                  => '' (Stringable)
 *   foreach ($v as $x) {}     => no iterations (IteratorAggregate over [])
 *   count($v);                => 0 (Countable)
 *   $v['anything'];           => the same safe value (ArrayAccess)
 *   $v->anything;             => the same safe value (__get)
 *   $v->anything();           => the same safe value (__call)
 *   $v();                     => the same safe value (__invoke)
 *
 * Method, property, array, and invocation access all return the same shared
 * instance, so arbitrarily deep chains (`$v->foo()->bar['baz']->qux()`) stay
 * safe. The only context it cannot control is boolean truthiness — an object is
 * always truthy — so the bridge returns `false` (not this) for fallbacks whose
 * name reads boolean (is_/has_/show_/...).
 */
final class LocalCmsSafeValue implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    private static ?self $instance = null;

    /** A single shared instance is enough; the object carries no state. */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function __toString(): string
    {
        return '';
    }

    public function __call(string $name, array $arguments): self
    {
        return self::instance();
    }

    public static function __callStatic(string $name, array $arguments): self
    {
        return self::instance();
    }

    public function __get(string $name): self
    {
        return self::instance();
    }

    public function __set(string $name, mixed $value): void
    {
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __unset(string $name): void
    {
    }

    public function __invoke(mixed ...$arguments): self
    {
        return self::instance();
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): self
    {
        return self::instance();
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
