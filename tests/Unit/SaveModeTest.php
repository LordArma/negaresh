<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Settings;

/**
 * I4: fix before saving. The stored text is corrected when a post is saved, and posts saved that
 * way are not fixed again on display.
 */
class SaveModeTest extends TestCase
{
    /** @var array<int, array<string, mixed>> post meta by post ID */
    private $meta = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
        $this->meta = [];
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('post_type_supports')->alias(function ($type, $feature) {
            return 'editor' === $feature && in_array($type, ['post', 'page', 'wp_block', 'product'], true);
        });
        Functions\when('get_post_types')->justReturn(['post' => 'post', 'page' => 'page', 'attachment' => 'attachment', 'product' => 'product']);
        Functions\when('get_post_meta')->alias(function ($id, $key, $single = false) {
            return $this->meta[$id][$key] ?? '';
        });
        Functions\when('update_post_meta')->alias(function ($id, $key, $value) {
            $this->meta[$id][$key] = $value;
            return true;
        });
        Functions\when('get_post')->justReturn(null);
    }

    private function plugin(): Negaresh
    {
        return new Negaresh(new Negaresh_Settings());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function setOptions(array $options): void
    {
        $this->options[Negaresh_Settings::OPTION] = $options;
    }

    /**
     * What wp_insert_post() passes to the wp_insert_post_data filter: slashed values.
     *
     * @return array<string, string>
     */
    private static function postData(string $content, string $type = 'post'): array
    {
        return ['post_content' => addslashes($content), 'post_title' => addslashes('عنوان ?'), 'post_type' => $type];
    }

    private static function post(int $id, string $content, string $type = 'post'): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_content = $content;
        $post->post_type = $type;
        return $post;
    }

    public function testConstructorRegistersSaveHooks(): void
    {
        $plugin = $this->plugin();

        self::assertNotFalse(has_filter('wp_insert_post_data', [$plugin, 'filter_post_data']));
        self::assertNotFalse(has_action('save_post', [$plugin, 'mark_fixed']));
    }

    public function testNewInstallsDefaultToSaveMode(): void
    {
        self::assertSame('save', (new Negaresh_Settings())->mode());
    }

    public function testSaveModeFixesStoredContentAndKeepsSlashes(): void
    {
        $data = $this->plugin()->filter_post_data(self::postData('<p>او گفت "سلام" ... و رفت</p>'), []);

        self::assertSame(addslashes('<p>او گفت "سلام"… و رفت</p>'), $data['post_content']);
        self::assertSame(addslashes('عنوان ?'), $data['post_title'], 'titles are not in scope for I4');
    }

    public function testClassicEditorParagraphBreaksSurvive(): void
    {
        $raw = "پاراگراف اول ...\n\nپاراگراف دوم ...\nخط بعد [gallery ids=\"1,2\"]";
        $data = $this->plugin()->filter_post_data(self::postData($raw), []);

        self::assertSame("پاراگراف اول…\n\nپاراگراف دوم…\nخط بعد [gallery ids=\"1,2\"]", stripslashes($data['post_content']));
    }

    public function testBlockDelimitersAreUntouched(): void
    {
        $raw = "<!-- wp:paragraph {\"className\":\"x\"} -->\n<p>متن ...</p>\n<!-- /wp:paragraph -->";
        $data = $this->plugin()->filter_post_data(self::postData($raw), []);

        self::assertSame("<!-- wp:paragraph {\"className\":\"x\"} -->\n<p>متن…</p>\n<!-- /wp:paragraph -->", stripslashes($data['post_content']));
    }

    /**
     * @return array<string, array{string}>
     */
    public function skippedTypeProvider(): array
    {
        return [
            'revision' => ['revision'],
            'attachment (no editor)' => ['attachment'],
            'navigation menu item' => ['nav_menu_item'],
            'site editor template (JSON and markup)' => ['wp_template'],
            'global styles (JSON)' => ['wp_global_styles'],
        ];
    }

    /** @dataProvider skippedTypeProvider */
    public function testOtherPostTypesAreNotChanged(string $type): void
    {
        $data = self::postData('<p>متن ...</p>', $type);

        self::assertSame($data, $this->plugin()->filter_post_data($data, []));
    }

    public function testSyncedPatternsAreFixed(): void
    {
        $data = $this->plugin()->filter_post_data(self::postData('<p>متن ...</p>', 'wp_block'), []);

        self::assertSame('<p>متن…</p>', stripslashes($data['post_content']));
    }

    public function testChosenPostTypesLimitSaving(): void
    {
        $this->setOptions(['post_types' => ['page']]);
        $data = self::postData('<p>متن ...</p>');

        self::assertSame($data, $this->plugin()->filter_post_data($data, []));
        self::assertSame('<p>متن…</p>', stripslashes($this->plugin()->filter_post_data(self::postData('<p>متن ...</p>', 'page'), [])['post_content']));
    }

    public function testDisplayModeDoesNotChangeStoredContent(): void
    {
        $this->setOptions(['mode' => 'display']);
        $data = self::postData('<p>متن ...</p>');

        self::assertSame($data, $this->plugin()->filter_post_data($data, []));
    }

    public function testSavedPostIsMarkedAndNotFixedAgainOnDisplay(): void
    {
        $plugin = $this->plugin();
        $data = $plugin->filter_post_data(self::postData('<p>متن ...</p>'), []);
        $stored = stripslashes($data['post_content']);

        $plugin->mark_fixed(7, self::post(7, $stored));
        self::assertSame((new Negaresh_Settings())->rules_hash(), $this->meta[7][Negaresh::FIXED_META]);

        Functions\when('get_post')->justReturn(self::post(7, $stored));
        // Display filters may add markup; a marked post is passed through as it is.
        self::assertSame('<p>متن ...تغییر نکند</p>', $plugin->filter_content('<p>متن ...تغییر نکند</p>'));
    }

    public function testOnlyThePostThatWasFixedIsMarked(): void
    {
        $plugin = $this->plugin();
        $plugin->filter_post_data(self::postData('<p>متن ...</p>'), []);

        // A revision (or anything else) saved in the same request must not take the mark.
        $plugin->mark_fixed(8, self::post(8, '<p>چیز دیگری</p>'));
        self::assertArrayNotHasKey(8, $this->meta);
    }

    public function testPostFixedWithOtherRulesIsFixedAgainOnDisplay(): void
    {
        $this->meta[9][Negaresh::FIXED_META] = 'hash-of-older-rules';
        Functions\when('get_post')->justReturn(self::post(9, '<p>متن ...</p>'));

        self::assertSame('<p>متن…</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testPostsSavedBeforeSaveModeAreStillFixedOnDisplay(): void
    {
        Functions\when('get_post')->justReturn(self::post(10, '<p>متن ...</p>'));

        self::assertSame('<p>متن…</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testRulesHashChangesWithTheRules(): void
    {
        $before = (new Negaresh_Settings())->rules_hash();
        $this->setOptions(['fix_dashes' => false]);

        self::assertNotSame($before, (new Negaresh_Settings())->rules_hash());
        $this->setOptions(['fix_dashes' => false, 'post_types' => ['page'], 'mode' => 'display']);
        self::assertSame((new Negaresh_Settings())->rules_hash(), (new Negaresh_Settings())->rules_hash());
    }

    public function testFailuresKeepTheTypedText(): void
    {
        $data = self::postData("<p>متن \xB1\x31 ...</p>");

        self::assertSame($data, $this->plugin()->filter_post_data($data, []));
    }
}
