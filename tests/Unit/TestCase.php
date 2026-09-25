<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    protected static function fixture(string $name): string
    {
        return file_get_contents(NEGARESH_TESTS_DIR . '/fixtures/' . $name);
    }

    /** Every HTML tag, in order. */
    protected static function tags(string $html): array
    {
        preg_match_all('/<\/?[a-z][^>]*?>/i', $html, $m);
        return $m[0];
    }

    /** Every HTML comment (Gutenberg block delimiters live here), in order. */
    protected static function comments(string $html): array
    {
        preg_match_all('/<!--[\s\S]*?-->/', $html, $m);
        return $m[0];
    }

    /** Every HTML entity outside tags, in order. */
    protected static function entities(string $html): array
    {
        $text = preg_replace('/<[^>]*>/', ' ', $html);
        preg_match_all('/&#?[a-z0-9]+;/i', $text, $m);
        return $m[0];
    }

    protected static function assertNoPreserverLeftovers(string $out): void
    {
        self::assertDoesNotMatchRegularExpression('/__[A-Z_]+PRESERVER__/', $out, 'placeholder left in output');
        self::assertStringNotContainsString('\\x{', $out, 'literal regex escape left in output');
    }
}
