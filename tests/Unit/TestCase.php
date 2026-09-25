<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // No object cache by default (P3-9); CacheTest replaces these with a working one.
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
    }

    /** @var array<string,mixed> in-memory wp_options table, see stubOptions() */
    protected $options = [];

    /**
     * Stubs get_option / update_option / delete_option against $this->options.
     *
     * @param array<string, mixed> $initial
     */
    protected function stubOptions(array $initial = []): void
    {
        $this->options = $initial;
        Functions\when('get_option')->alias(function ($name, $default = false) {
            return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
        });
        Functions\when('update_option')->alias(function ($name, $value) {
            $this->options[$name] = $value;
            return true;
        });
        Functions\when('delete_option')->alias(function ($name) {
            $existed = array_key_exists($name, $this->options);
            unset($this->options[$name]);
            return $existed;
        });
    }

    /** Front end request for a post of the given type. */
    protected function stubFrontEnd(string $post_type = 'post'): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('get_post_type')->justReturn($post_type);
        Functions\when('get_post')->justReturn(null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    protected static function fixture(string $name): string
    {
        return self::read(NEGARESH_TESTS_DIR . '/fixtures/' . $name);
    }

    /** Reads a file the test needs; a missing file fails the test instead of passing false on. */
    protected static function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents, "cannot read $path");
        return $contents;
    }

    /**
     * The plugin's PHP files in the root and includes/.
     *
     * @return list<string>
     */
    protected static function pluginFiles(): array
    {
        $files = glob(NEGARESH_PLUGIN_DIR . '/{,includes/}*.php', GLOB_BRACE);
        self::assertIsArray($files);
        return $files;
    }

    /**
     * Every HTML tag, in order.
     *
     * @return list<string>
     */
    protected static function tags(string $html): array
    {
        preg_match_all('/<\/?[a-z][^>]*?>/i', $html, $m);
        return $m[0];
    }

    /**
     * Every HTML comment (Gutenberg block delimiters live here), in order.
     *
     * @return list<string>
     */
    protected static function comments(string $html): array
    {
        preg_match_all('/<!--[\s\S]*?-->/', $html, $m);
        return $m[0];
    }

    /**
     * Every HTML entity outside tags, in order.
     *
     * @return list<string>
     */
    protected static function entities(string $html): array
    {
        $text = (string) preg_replace('/<[^>]*>/', ' ', $html);
        preg_match_all('/&#?[a-z0-9]+;/i', $text, $m);
        return $m[0];
    }

    protected static function assertNoPreserverLeftovers(string $out): void
    {
        self::assertDoesNotMatchRegularExpression('/__[A-Z_]+PRESERVER__/', $out, 'placeholder left in output');
        self::assertStringNotContainsString('\\x{', $out, 'literal regex escape left in output');
    }
}
