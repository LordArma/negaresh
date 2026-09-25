<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Editor;
use Negaresh_Settings;

/**
 * I6: per post opt out, skipping marked markup, and the editor's "Fix this post" endpoint.
 */
class EditorTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private $meta = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
        $this->meta = [];
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('post_type_supports')->justReturn(true);
        Functions\when('get_post_types')->justReturn(['post' => 'post']);
        Functions\when('get_post_meta')->alias(function ($id, $key, $single = false) {
            return $this->meta[$id][$key] ?? '';
        });
        Functions\when('update_post_meta')->alias(function ($id, $key, $value) {
            $this->meta[$id][$key] = $value;
            return true;
        });
    }

    /**
     * @return array{Negaresh, Negaresh_Editor}
     */
    private function make(): array
    {
        $plugin = new Negaresh(new Negaresh_Settings());
        return [$plugin, new Negaresh_Editor($plugin, new Negaresh_Settings())];
    }

    private static function post(int $id): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        return $post;
    }

    /**
     * @return array<string, string>
     */
    private static function data(string $content): array
    {
        return ['post_content' => addslashes($content), 'post_title' => 't', 'post_type' => 'post'];
    }

    // Markup skip ------------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public function skippedMarkupProvider(): array
    {
        return [
            'class negaresh-skip' => ['<div class="note negaresh-skip"><p>متن ... "x"</p></div>'],
            'data-negaresh off' => ["<blockquote data-negaresh='off'>نقل ... ?</blockquote>"],
            'nested same element' => ['<div class="negaresh-skip"><div>یک ...</div>دو ...</div>'],
        ];
    }

    /** @dataProvider skippedMarkupProvider */
    public function testMarkedMarkupIsNotFixed(string $block): void
    {
        [$plugin] = $this->make();

        self::assertSame('<p>قبل…</p>' . $block . '<p>بعد…</p>', $plugin->fix('<p>قبل ...</p>' . $block . '<p>بعد ...</p>'));
    }

    public function testSimilarClassNamesAreNotSkipped(): void
    {
        [$plugin] = $this->make();

        self::assertSame('<p class="negaresh-skipper">متن…</p>', $plugin->fix('<p class="negaresh-skipper">متن ...</p>'));
        self::assertSame('<p data-negaresh="on">متن…</p>', $plugin->fix('<p data-negaresh="on">متن ...</p>'));
    }

    // Per post opt out -------------------------------------------------------------------------

    public function testOptedOutPostIsNotFixedOnDisplay(): void
    {
        [$plugin] = $this->make();
        $this->meta[3][Negaresh_Settings::SKIP_META] = '1';
        Functions\when('get_post')->justReturn(self::post(3));

        self::assertSame('<p>متن ...</p>', $plugin->filter_content('<p>متن ...</p>'));
    }

    public function testOptedOutPostIsNotFixedOnSave(): void
    {
        [$plugin] = $this->make();
        $this->meta[3][Negaresh_Settings::SKIP_META] = '1';
        $data = self::data('<p>متن ...</p>');

        self::assertSame($data, $plugin->filter_post_data($data, ['ID' => 3]));
    }

    public function testTickingTheBoxInTheBlockEditorAppliesToThatSameSave(): void
    {
        [$plugin, $editor] = $this->make();
        $request = new \WP_REST_Request(['meta' => [Negaresh_Settings::SKIP_META => true]]);
        $prepared = (object) ['ID' => 4, 'post_content' => '<p>متن ...</p>'];

        self::assertSame($prepared, $editor->capture_rest_skip($prepared, $request));
        $data = self::data('<p>متن ...</p>');
        self::assertSame($data, $plugin->filter_post_data($data, ['ID' => 4]), 'meta is saved after the content');
    }

    public function testUntickingTheBoxInTheBlockEditorAppliesToThatSameSave(): void
    {
        [$plugin, $editor] = $this->make();
        $this->meta[5][Negaresh_Settings::SKIP_META] = '1';
        $editor->capture_rest_skip((object) ['ID' => 5], new \WP_REST_Request(['meta' => [Negaresh_Settings::SKIP_META => false]]));

        self::assertSame(addslashes('<p>متن…</p>'), $plugin->filter_post_data(self::data('<p>متن ...</p>'), ['ID' => 5])['post_content']);
    }

    public function testClassicEditorBoxIsReadWithItsNonce(): void
    {
        [$plugin, $editor] = $this->make();
        Functions\when('wp_verify_nonce')->alias(function ($nonce, $action) {
            return 'good' === $nonce && 'negaresh_skip' === $action ? 1 : false;
        });
        $_POST = ['negaresh_skip_nonce' => 'good', 'negaresh_skip' => '1'];
        $data = self::data('<p>متن ...</p>');

        try {
            self::assertSame($data, $plugin->filter_post_data($data, ['ID' => 6]));

            $_POST['negaresh_skip_nonce'] = 'forged';
            self::assertSame(addslashes('<p>متن…</p>'), $plugin->filter_post_data($data, ['ID' => 6])['post_content']);
        } finally {
            $_POST = [];
        }
    }

    public function testClassicEditorBoxIsSavedAsMeta(): void
    {
        [, $editor] = $this->make();
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('delete_post_meta')->alias(function ($id, $key) {
            unset($this->meta[$id][$key]);
            return true;
        });

        $_POST = ['negaresh_skip_nonce' => 'n', 'negaresh_skip' => '1'];
        try {
            $editor->save_meta_box(7);
            self::assertSame('1', $this->meta[7][Negaresh_Settings::SKIP_META]);

            $_POST = ['negaresh_skip_nonce' => 'n'];
            $editor->save_meta_box(7);
            self::assertArrayNotHasKey(Negaresh_Settings::SKIP_META, $this->meta[7]);
        } finally {
            $_POST = [];
        }
    }

    public function testMetaIsRegisteredForTheBlockEditor(): void
    {
        [, $editor] = $this->make();
        $registered = [];
        Functions\when('register_post_meta')->alias(function ($type, $key, $args) use (&$registered) {
            $registered[$key] = $args;
            return true;
        });
        $editor->register_meta();

        $args = $registered[Negaresh_Settings::SKIP_META];
        self::assertTrue($args['show_in_rest']);
        self::assertSame('boolean', $args['type']);
        Functions\when('current_user_can')->alias(function ($cap, $id = null) {
            return 'edit_post' === $cap && 9 === $id;
        });
        self::assertTrue(($args['auth_callback'])(true, Negaresh_Settings::SKIP_META, 9));
        self::assertFalse(($args['auth_callback'])(true, Negaresh_Settings::SKIP_META, 10));
    }

    // "Fix this post" --------------------------------------------------------------------------

    public function testFixRouteUsesTheSavedRulesAndNeedsEditRights(): void
    {
        [, $editor] = $this->make();
        $routes = [];
        Functions\when('register_rest_route')->alias(function ($ns, $route, $args) use (&$routes) {
            $routes[$ns . $route] = $args;
            return true;
        });
        $editor->register_rest_routes();
        $route = $routes['negaresh/v1/fix'];

        Functions\when('current_user_can')->justReturn(false);
        self::assertFalse(($route['permission_callback'])());
        Functions\when('current_user_can')->alias(function ($cap) {
            return 'edit_posts' === $cap;
        });
        self::assertTrue(($route['permission_callback'])());

        $this->options[Negaresh_Settings::OPTION] = ['fix_three_dots' => true, 'fix_titles' => false];
        $out = $editor->rest_fix(new \WP_REST_Request(['content' => '<p>متن ...</p>', 'title' => 'عنوان ...']));
        self::assertSame(['content' => '<p>متن…</p>', 'title' => 'عنوان ...'], $out);

        $this->options[Negaresh_Settings::OPTION] = ['fix_titles' => true];
        self::assertSame('عنوان…', $editor->rest_fix(new \WP_REST_Request(['content' => '', 'title' => 'عنوان ...']))['title']);
    }

    public function testUninstallAlsoRemovesTheOptOuts(): void
    {
        $removed = [];
        Functions\when('delete_post_meta_by_key')->alias(function ($key) use (&$removed) {
            $removed[] = $key;
            return true;
        });

        Negaresh_Settings::delete_all();

        self::assertContains(Negaresh_Settings::SKIP_META, $removed);
        self::assertContains(Negaresh_Settings::FIXED_META, $removed);
    }
}
