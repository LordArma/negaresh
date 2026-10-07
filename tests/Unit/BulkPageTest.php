<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Bulk;
use Negaresh_Bulk_Page;
use Negaresh_Settings;

/**
 * I6: the bulk tool page (Tools → Negaresh) and its REST routes.
 */
class BulkPageTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private $routes = [];

    /** @var array<int, \WP_Post> */
    private $posts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        $this->routes = $this->posts = [];
        Functions\when('register_rest_route')->alias(function ($ns, $route, $args) {
            $this->routes[$ns . $route] = $args;
            return true;
        });
        Functions\when('post_type_supports')->justReturn(true);
        Functions\when('get_post_types')->justReturn(['post' => 'post', 'page' => 'page']);
        Functions\when('get_post')->alias(function ($id = null) {
            return $this->posts[$id] ?? null;
        });
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_edit_post_link')->alias(function ($id) {
            return "https://example.com/wp-admin/post.php?post={$id}&action=edit";
        });
    }

    private function page(): Negaresh_Bulk_Page
    {
        $settings = new Negaresh_Settings();
        $plugin = new Negaresh($settings);
        $page = new Negaresh_Bulk_Page(new Negaresh_Bulk($plugin, $settings));
        $page->register_rest_routes();
        return $page;
    }

    private function add(int $id, string $content): void
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_content = $content;
        $post->post_title = "پست {$id}";
        $this->posts[$id] = $post;
    }

    public function testRoutesAreForAdminsOnly(): void
    {
        $this->page();
        Functions\when('current_user_can')->alias(function ($cap) {
            return 'edit_posts' === $cap; // an author: may edit, may not run bulk jobs
        });

        foreach (['negaresh/v1/bulk/find', 'negaresh/v1/bulk/process'] as $route) {
            self::assertArrayHasKey($route, $this->routes);
            self::assertFalse(($this->routes[$route]['permission_callback'])(), $route);
        }
        Functions\when('current_user_can')->justReturn(true);
        self::assertTrue(($this->routes['negaresh/v1/bulk/process']['permission_callback'])());
    }

    public function testFindReturnsIdsForTheChosenTypes(): void
    {
        $page = $this->page();
        $args = null;
        Functions\when('get_posts')->alias(function ($query) use (&$args) {
            $args = $query;
            return [3, 5];
        });

        $out = $page->rest_find(new \WP_REST_Request(['post_type' => ['page'], 'all' => true]));

        self::assertSame(['ids' => [3, 5]], $out);
        self::assertSame(['page'], $args['post_type']);
    }

    public function testProcessBatchSizeIsLimited(): void
    {
        $this->page();
        $validate = $this->routes['negaresh/v1/bulk/process']['args']['ids']['validate_callback'];

        self::assertTrue($validate([1, 2, 3]));
        self::assertFalse($validate(range(1, Negaresh_Bulk_Page::BATCH + 1)));
        self::assertFalse($validate([]));
        self::assertFalse($validate('1,2'));
    }

    public function testProcessReportsDiffsAndChecksEachPost(): void
    {
        $page = $this->page();
        $this->add(1, "<p>یک ...</p>\n<p>دو</p>");
        $this->add(2, '<p>درست</p>');
        $this->add(3, '<p>سه ...</p>');
        Functions\when('current_user_can')->alias(function ($cap, $id = null) {
            return 'edit_post' === $cap && 3 !== $id; // post 3 belongs to someone else
        });

        $out = $page->rest_process(new \WP_REST_Request(['ids' => [1, 2, 3], 'apply' => false]));
        $by_id = array_column($out['results'], null, 'id');

        self::assertTrue($by_id[1]['changed']);
        self::assertSame('https://example.com/wp-admin/post.php?post=1&action=edit', $by_id[1]['edit_link']);
        self::assertSame([['field' => 'post_content', 'lines' => [['-', '<p>یک ...</p>'], ['+', '<p>یک…</p>']]]], $by_id[1]['diff']);
        self::assertArrayNotHasKey('fields', $by_id[1], 'full texts are not sent to the browser');
        self::assertFalse($by_id[2]['changed']);
        self::assertSame('not_allowed', $by_id[3]['skipped']);
    }

    public function testMenuPageIsUnderTools(): void
    {
        $added = null;
        Functions\when('add_management_page')->alias(function (...$args) use (&$added) {
            $added = $args;
            return 'tools_page_negaresh-bulk';
        });

        $this->page()->add_page();

        self::assertIsArray($added);
        self::assertSame('manage_options', $added[2]);
        self::assertSame(Negaresh_Bulk_Page::PAGE, $added[3]);
    }

    /**
     * B33: fixing marks every post, also those already correct: before, only the posts that would
     * change were fixed, so the others stayed "waiting" for ever and the count never went down.
     */
    public function testB33FixMarksPostsThatNeedNoChangeToo(): void
    {
        $page = $this->page();
        $this->add(1, '<p>یک ...</p>');
        $this->add(2, '<p>درست</p>');
        $this->add(3, '<p>سه ...</p>');
        $meta = [];
        Functions\when('update_post_meta')->alias(function ($id, $key, $value) use (&$meta) {
            $meta[$id][$key] = $value;
            return true;
        });
        Functions\when('wp_save_post_revision')->justReturn(1);
        Functions\when('has_filter')->justReturn(false);
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('wp_update_post')->alias(function ($data) {
            return $data['ID'];
        });
        Functions\when('current_user_can')->alias(function ($cap, $id = null) {
            return 'edit_post' === $cap && 3 !== $id;
        });

        $counts = $page->fix([1, 2, 3]);

        self::assertSame(['negaresh_fixed' => 1, 'negaresh_checked' => 1, 'negaresh_failed' => 1], $counts);
        self::assertSame([1, 2], array_keys($meta), 'the post already correct is marked as checked too');
    }

    public function testB33FixingSendsNoDiff(): void
    {
        $page = $this->page();
        $this->add(1, '<p>یک ...</p>');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('wp_save_post_revision')->justReturn(1);
        Functions\when('has_filter')->justReturn(false);
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('wp_update_post')->justReturn(1);

        $out = $page->rest_process(new \WP_REST_Request(['ids' => [1], 'apply' => true]));

        self::assertTrue($out['results'][0]['changed']);
        self::assertSame([], $out['results'][0]['diff']);
    }

    public function testB33PerPageScreenOptionIsKeptInRange(): void
    {
        $page = $this->page();

        self::assertSame(200, $page->save_per_page(false, Negaresh_Bulk_Page::PER_PAGE_OPTION, '200'));
        self::assertSame(999, $page->save_per_page(false, Negaresh_Bulk_Page::PER_PAGE_OPTION, '5000'));
        self::assertSame(1, $page->save_per_page(false, Negaresh_Bulk_Page::PER_PAGE_OPTION, '-3'));
        self::assertFalse($page->save_per_page(false, 'edit_post_per_page', '20'), 'other screen options are not ours');
        self::assertGreaterThanOrEqual(50, Negaresh_Bulk_Page::PER_PAGE, 'many more posts than the old scan showed at once');
    }
}
