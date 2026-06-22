<?php

/**
 * IDE stubs for Pest global functions.
 *
 * Not autoloaded at runtime — Pest defines these in vendor/pestphp/pest/src/Functions.php.
 * This file exists so Intelephense/PHPStan can resolve symbols when vendor lives in Docker.
 *
 * @see https://pestphp.com/docs
 */

namespace {
    function pest(): object
    {
    }

    function test(?string $description = null, ?\Closure $closure = null): object
    {
    }

    function describe(string $description, \Closure $closure): void
    {
    }

    function it(?string $description = null, ?\Closure $closure = null): object
    {
    }

    function expect(mixed $value = null): object
    {
    }

    function beforeEach(?\Closure $closure = null): object
    {
    }

    function afterEach(?\Closure $closure = null): object
    {
    }
}
