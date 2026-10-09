<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi;

use PHPUnit\Framework\TestCase as BaseTestCase;

use function json_decode;

abstract class AbstractTestCase extends BaseTestCase
{
    /**
     * Decodes a JSON object or list into an array, failing the test otherwise.
     */
    protected static function decode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Walks `$data` down `$path`, asserting every step is an array, and returns
     * the array at the end: decoded JSON is untyped until a test narrows it.
     */
    protected static function at(array $data, string|int ...$path): array
    {
        foreach ($path as $key) {
            static::assertArrayHasKey($key, $data);
            $next = $data[$key];
            static::assertIsArray($next, 'Not an object or list at "' . $key . '".');
            $data = $next;
        }

        return $data;
    }
}
