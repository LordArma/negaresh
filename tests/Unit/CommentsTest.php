<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Settings;

/**
 * P3-6: comments, when "Fix comments" is on.
 */
class CommentsTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private $meta = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions([Negaresh_Settings::OPTION => ['fix_comments' => true]]);
        $this->stubFrontEnd();
        $this->meta = [];
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('get_comment_meta')->alias(function ($id, $key, $single = false) {
            return $this->meta[$id][$key] ?? '';
        });
        Functions\when('update_comment_meta')->alias(function ($id, $key, $value) {
            $this->meta[$id][$key] = $value;
            return true;
        });
        Functions\when('delete_comment_meta')->alias(function ($id, $key) {
            unset($this->meta[$id][$key]);
            return true;
        });
    }

    private function plugin(): Negaresh
    {
        return new Negaresh(new Negaresh_Settings());
    }

    private static function comment(int $id, string $content): \WP_Comment
    {
        $comment = new \WP_Comment();
        $comment->comment_ID = (string) $id;
        $comment->comment_content = $content;
        return $comment;
    }

    public function testHooks(): void
    {
        $plugin = $this->plugin();

        self::assertSame(9, has_filter('comment_text', [$plugin, 'filter_comment_text']), 'before wptexturize');
        self::assertSame(20, has_filter('pre_comment_content', [$plugin, 'filter_comment_content']), 'after kses');
    }

    public function testOffByDefault(): void
    {
        $this->options[Negaresh_Settings::OPTION] = [];
        $plugin = $this->plugin();

        self::assertSame('نظر ...', $plugin->filter_comment_text('نظر ...', self::comment(1, 'نظر ...')));
        self::assertSame(addslashes('نظر "من" ...'), $plugin->filter_comment_content(addslashes('نظر "من" ...')));
    }

    public function testSaveModeFixesTheStoredCommentAndKeepsSlashes(): void
    {
        self::assertSame(addslashes('<p>نظر "من"…</p>'), $this->plugin()->filter_comment_content(addslashes('<p>نظر "من" ...</p>')));
    }

    public function testDisplayModeLeavesTheStoredCommentAlone(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_comments' => true, 'mode' => 'display'];
        $plugin = $this->plugin();

        self::assertSame('نظر ...', $plugin->filter_comment_content('نظر ...'));
        self::assertSame('نظر…', $plugin->filter_comment_text('نظر ...', self::comment(2, 'نظر ...')));
    }

    public function testCommentFixedOnSaveIsMarkedAndNotFixedAgain(): void
    {
        $plugin = $this->plugin();
        $stored = stripslashes($plugin->filter_comment_content(addslashes('نظر ...')));

        $plugin->mark_comment_fixed(3, self::comment(3, $stored));
        self::assertSame((new Negaresh_Settings())->rules_hash(), $this->meta[3][Negaresh::FIXED_META]);
        // Display filters (make_clickable) may already have changed the text: passed through.
        self::assertSame('نظر ... دیگر', $plugin->filter_comment_text('نظر ... دیگر', self::comment(3, $stored)));
    }

    public function testOlderCommentsAreFixedOnDisplay(): void
    {
        self::assertSame('نظر…', $this->plugin()->filter_comment_text('نظر ...', self::comment(4, 'نظر ...')));
    }

    public function testEditingACommentOutsideTheFilterDropsTheMark(): void
    {
        $plugin = $this->plugin();
        $this->meta[5][Negaresh::FIXED_META] = (new Negaresh_Settings())->rules_hash();

        // An edit whose text did not go through filter_comment_content (for example wp-cli).
        $plugin->mark_comment_fixed(5, self::comment(5, 'متن تازه ...'));

        self::assertArrayNotHasKey(Negaresh::FIXED_META, $this->meta[5]);
    }

    public function testCommentsFollowTheAdminAndPostTypeScope(): void
    {
        Functions\when('is_admin')->justReturn(true);

        self::assertSame('نظر ...', $this->plugin()->filter_comment_text('نظر ...', self::comment(6, 'نظر ...')));
    }

    public function testUninstallRemovesCommentMarkers(): void
    {
        $removed = [];
        Functions\when('delete_post_meta_by_key')->justReturn(true);
        Functions\when('delete_metadata')->alias(function ($type, $id, $key, $value, $all) use (&$removed) {
            $removed[] = [$type, $key, $all];
            return true;
        });

        Negaresh_Settings::delete_all();

        self::assertContains(['comment', Negaresh_Settings::FIXED_META, true], $removed);
    }
}
