<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Bulk;
use Negaresh_Settings;

/**
 * I6: fixing existing posts (engine behind WP-CLI and the bulk tool page).
 */
class BulkTest extends TestCase
{
    /** @var array<int, \WP_Post> */
    private $posts = [];

    /** @var array<int, array<string, mixed>> */
    private $meta = [];

    /** @var list<array<string, mixed>> */
    private $updates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
        $this->posts = $this->meta = $this->updates = [];
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('post_type_supports')->justReturn(true);
        Functions\when('get_post_types')->justReturn(['post' => 'post', 'page' => 'page']);
        Functions\when('get_post')->alias(function ($id = null) {
            return $this->posts[$id] ?? null;
        });
        Functions\when('get_post_meta')->alias(function ($id, $key, $single = false) {
            return $this->meta[$id][$key] ?? '';
        });
        Functions\when('update_post_meta')->alias(function ($id, $key, $value) {
            $this->meta[$id][$key] = $value;
            return true;
        });
        Functions\when('wp_update_post')->alias(function ($data) {
            $this->updates[] = ['data' => $data, 'kses' => (bool) has_filter('content_save_pre', 'wp_filter_post_kses')];
            return $data['ID'];
        });
        Functions\when('wp_save_post_revision')->alias(function ($id) {
            $this->updates[] = ['revision_of' => $id];
            return 100 + $id;
        });
        Functions\when('kses_remove_filters')->alias(function () {
            remove_filter('content_save_pre', 'wp_filter_post_kses');
        });
        Functions\when('kses_init_filters')->alias(function () {
            add_filter('content_save_pre', 'wp_filter_post_kses');
        });
    }

    private function add(int $id, string $content, string $title = 'عنوان', string $type = 'post'): void
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_content = $content;
        $post->post_type = $type;
        $post->post_title = $title;
        $post->post_excerpt = '';
        $this->posts[$id] = $post;
    }

    private function bulk(): Negaresh_Bulk
    {
        $settings = new Negaresh_Settings();
        return new Negaresh_Bulk(new Negaresh($settings), $settings);
    }

    public function testDryRunReportsChangesAndWritesNothing(): void
    {
        $this->add(1, "<p>خط اول ...</p>\n<p>خط دوم</p>");
        $result = $this->bulk()->process(1, false);

        self::assertTrue($result['changed']);
        self::assertSame(['post_content'], array_keys($result['fields']));
        self::assertSame("<p>خط اول…</p>\n<p>خط دوم</p>", $result['fields']['post_content']['after']);
        self::assertSame([], $this->updates);
        self::assertSame([], $this->meta);
    }

    public function testApplyUpdatesThroughWordPressAndMarksThePost(): void
    {
        $this->add(2, '<p>متن ...</p>');
        $result = $this->bulk()->process(2, true);

        self::assertTrue($result['changed']);
        self::assertCount(2, $this->updates);
        self::assertSame(['revision_of' => 2], $this->updates[0], 'the text as it was is kept as a revision first');
        self::assertSame(['ID' => 2, 'post_content' => addslashes('<p>متن…</p>')], $this->updates[1]['data']);
        self::assertSame((new Negaresh_Settings())->rules_hash(), $this->meta[2][Negaresh_Settings::FIXED_META]);
    }

    public function testApplyNeverRunsKsesOverThePost(): void
    {
        // For users without unfiltered_html (multisite site admins) kses is on: re-saving would strip embeds.
        add_filter('content_save_pre', 'wp_filter_post_kses');
        $this->add(3, '<p>متن ...</p><iframe src="https://example.com/embed"></iframe>');

        $this->bulk()->process(3, true);

        self::assertFalse($this->updates[1]['kses'], 'kses must be off during the update');
        self::assertNotFalse(has_filter('content_save_pre', 'wp_filter_post_kses'), 'and on again afterwards');
    }

    public function testKsesStaysOffWhenItWasOff(): void
    {
        $this->add(4, '<p>متن ...</p>');

        $this->bulk()->process(4, true);

        self::assertFalse(has_filter('content_save_pre', 'wp_filter_post_kses'));
    }

    public function testUnchangedPostIsMarkedButNotUpdated(): void
    {
        $this->add(5, '<p>متن درست</p>');
        $result = $this->bulk()->process(5, true);

        self::assertFalse($result['changed']);
        self::assertSame([], $this->updates);
        self::assertSame((new Negaresh_Settings())->rules_hash(), $this->meta[5][Negaresh_Settings::FIXED_META]);
    }

    public function testOptedOutPostIsSkipped(): void
    {
        $this->add(6, '<p>متن ...</p>');
        $this->meta[6][Negaresh_Settings::SKIP_META] = '1';

        $result = $this->bulk()->process(6, true);

        self::assertSame('opted_out', $result['skipped']);
        self::assertSame([], $this->updates);
    }

    public function testTitlesAndExcerptsFollowTheSettings(): void
    {
        $this->add(7, '<p>متن</p>', 'عنوان ...');
        self::assertFalse($this->bulk()->process(7, false)['changed']);

        $this->options[Negaresh_Settings::OPTION] = ['fix_titles' => true];
        $result = $this->bulk()->process(7, false);
        self::assertSame('عنوان…', $result['fields']['post_title']['after']);
    }

    public function testMissingPostIsReported(): void
    {
        self::assertSame('missing', $this->bulk()->process(99, true)['skipped']);
    }

    public function testQueryUsesTheSaveScopeAndSkipsAlreadyFixedPosts(): void
    {
        $captured = null;
        Functions\when('get_posts')->alias(function ($args) use (&$captured) {
            $captured = $args;
            return [11, 12];
        });

        self::assertSame([11, 12], $this->bulk()->find(['limit' => 50]));
        self::assertSame(['post', 'page', 'wp_block'], $captured['post_type'], 'synced patterns too, as in save mode');
        self::assertSame('ids', $captured['fields']);
        self::assertSame(50, $captured['posts_per_page']);
        self::assertSame('ASC', $captured['order']);

        // No limit: every ID at once, so processing (which marks posts) cannot shift a page.
        $this->bulk()->find([]);
        self::assertSame(-1, $captured['posts_per_page']);
        $meta = $captured['meta_query'];
        self::assertSame('AND', $meta['relation']);
        self::assertStringContainsString('_negaresh_fixed', json_encode($meta) ?: '');
        self::assertStringContainsString('_negaresh_skip', json_encode($meta) ?: '');

        $this->bulk()->find(['all' => true, 'post_type' => ['page']]);
        self::assertSame(['page'], $captured['post_type']);
        self::assertStringNotContainsString('_negaresh_fixed', json_encode($captured['meta_query']) ?: '');
    }

    public function testQueryOnlyAllowsTypesInScope(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['post_types' => ['page']];
        $captured = null;
        Functions\when('get_posts')->alias(function ($args) use (&$captured) {
            $captured = $args;
            return [];
        });

        $this->bulk()->find(['post_type' => ['post', 'page']]);

        self::assertSame(['page'], $captured['post_type']);
    }

    public function testStatsCountFixedWaitingAndOptedOut(): void
    {
        Functions\when('get_posts')->alias(function ($args) {
            $json = json_encode($args['meta_query']) ?: '';
            if (false !== strpos($json, '"compare":"="')) {
                return [9]; // opted out
            }
            if (false !== strpos($json, '_negaresh_fixed')) {
                return [1, 2]; // not fixed with the current rules
            }
            return [1, 2, 3, 4, 5]; // every post in scope that is not opted out
        });

        self::assertSame(['total' => 5, 'fixed' => 3, 'waiting' => 2, 'opted_out' => 1], $this->bulk()->stats());
    }

    public function testLineDiff(): void
    {
        $diff = Negaresh_Bulk::diff("a\nb ...\nc\nd", "a\nb…\nc\nd\ne");

        self::assertSame([['-', 'b ...'], ['+', 'b…'], ['+', 'e']], $diff);
        self::assertSame([], Negaresh_Bulk::diff("x\ny", "x\ny"));
    }
}
