<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Settings;

/**
 * P3-9: display mode results are cached in the object cache (not the database).
 */
class CacheTest extends TestCase
{
    /** @var array<string, mixed> */
    private $cache = [];

    /** @var list<string> */
    private $sets = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions([Negaresh_Settings::OPTION => ['mode' => 'display']]);
        $this->stubFrontEnd();
        $this->cache = $this->sets = [];
        Functions\when('wp_cache_get')->alias(function ($key, $group = '') {
            return $this->cache[$group . ':' . $key] ?? false;
        });
        Functions\when('wp_cache_set')->alias(function ($key, $value, $group = '', $expire = 0) {
            $this->cache[$group . ':' . $key] = $value;
            $this->sets[] = $group . ':' . $key;
            return true;
        });
    }

    private function plugin(): Negaresh
    {
        return new Negaresh(new Negaresh_Settings());
    }

    public function testResultIsStoredAndReused(): void
    {
        $plugin = $this->plugin();

        self::assertSame('<p>متن…</p>', $plugin->filter_content('<p>متن ...</p>'));
        self::assertCount(1, $this->sets);
        self::assertStringStartsWith('negaresh:', $this->sets[0]);

        // A cached value is returned as is (proves Virastar is not run again).
        $this->cache[$this->sets[0]] = 'from cache';
        self::assertSame('from cache', $plugin->filter_content('<p>متن ...</p>'));
    }

    public function testKeyChangesWithTheRules(): void
    {
        $this->plugin()->filter_content('<p>متن ...</p>');
        $this->options[Negaresh_Settings::OPTION] = ['mode' => 'display', 'fix_three_dots' => false];
        $this->plugin()->filter_content('<p>متن ...</p>');

        self::assertCount(2, array_unique($this->sets));
    }

    public function testTitlesExcerptsAndCommentsAreCachedToo(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['mode' => 'display', 'fix_titles' => true, 'fix_excerpts' => true, 'fix_comments' => true];
        Functions\when('get_comment_meta')->justReturn('');
        $plugin = $this->plugin();

        $plugin->filter_title('عنوان ...', 0);
        $plugin->filter_excerpt('خلاصه ...');
        $comment = new \WP_Comment();
        $plugin->filter_comment_text('نظر ...', $comment);

        self::assertCount(3, $this->sets);
    }

    public function testSaveModeDoesNotUseTheCache(): void
    {
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('post_type_supports')->justReturn(true);
        Functions\when('get_post_types')->justReturn(['post' => 'post']);
        Functions\when('get_post_meta')->justReturn('');
        $this->options[Negaresh_Settings::OPTION] = ['mode' => 'save'];

        $this->plugin()->filter_post_data(['post_content' => '<p>متن ...</p>', 'post_type' => 'post'], ['ID' => 1]);

        self::assertSame([], $this->sets);
    }

    public function testTextWithoutPersianIsNotCached(): void
    {
        $this->plugin()->filter_content('<p>English only ...</p>');

        self::assertSame([], $this->sets);
    }
}
