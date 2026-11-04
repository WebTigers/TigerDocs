<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Support;

use ReflectionClass;
use ReflectionMethod;

/**
 * Reflection helpers for exercising the Docs engine's protected pure logic without a full ZF boot.
 *
 * ZF1 module classes carry their behavior in protected methods; PHP 8.1+ lets reflection invoke a
 * protected/private method without setAccessible() (deprecated on 8.5, and it trips failOnWarning),
 * so we never call it. `bare()` builds an instance without running the constructor (the Docs model's
 * constructor scans content roots off disk — not wanted for a pure-helper unit test).
 */
trait Reflect
{
    /** A no-constructor instance (skips the filesystem scan in Docs_Model_Docs::__construct). */
    protected function bare(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /** Invoke a protected/private method. Pass $args by reference-capable array for by-ref params. */
    protected function call(object $obj, string $method, array $args = [])
    {
        return (new ReflectionMethod($obj, $method))->invokeArgs($obj, $args);
    }
}
