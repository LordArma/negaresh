<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Settings;

/**
 * I5: settings page usability: live preview, reset rules, Settings link, titles and excerpts.
 */
class SettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('sanitize_key')->alias(function ($key) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
        });
        Functions\when('get_post_types')->justReturn(['post' => (object) ['labels' => (object) ['singular_name' => 'Post']]]);
        Functions\when('wp_unslash')->alias('stripslashes');
        Functions\when('wp_slash')->alias('addslashes');
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('post_type_supports')->justReturn(true);
        Functions\when('get_post_meta')->justReturn('');
    }

    private function plugin(): Negaresh
    {
        return new Negaresh(new Negaresh_Settings());
    }

    // Live preview -----------------------------------------------------------------------------

    public function testPreviewUsesTheGivenRulesNotTheSavedOnes(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_english_numbers' => false];
        $plugin = $this->plugin();

        self::assertSame('<p>عدد ۱۲۳…</p>', $plugin->preview('<p>عدد 123 ...</p>', ['fix_english_numbers' => true, 'fix_three_dots' => true, 'remove_spaces_before_ellipsis' => true]));
        self::assertSame('<p>عدد 123…</p>', $plugin->filter_content('<p>عدد 123 ...</p>'), 'saved rules are untouched');
    }

    public function testPreviewIgnoresUnknownKeysAndTreatsMissingRulesAsOff(): void
    {
        // The form sends the checked boxes only: a rule that is missing is off.
        $out = $this->plugin()->preview('<p>متن ... ٤</p>', ['fix_arabic_numbers' => true, 'decode_html_entities' => true, 'bogus' => 1]);

        self::assertSame('<p>متن ... ۴</p>', $out);
    }

    public function testPreviewRouteIsForAdminsOnly(): void
    {
        $routes = [];
        Functions\when('register_rest_route')->alias(function ($ns, $route, $args) use (&$routes) {
            $routes[$ns . $route] = $args;
            return true;
        });
        $plugin = $this->plugin();
        $plugin->register_rest_routes();

        self::assertArrayHasKey('negaresh/v1/preview', $routes);
        $route = $routes['negaresh/v1/preview'];
        self::assertSame('POST', $route['methods']);

        Functions\when('current_user_can')->justReturn(false);
        self::assertFalse(($route['permission_callback'])());
        Functions\when('current_user_can')->alias(function ($cap) {
            return 'manage_options' === $cap;
        });
        self::assertTrue(($route['permission_callback'])());
    }

    public function testPreviewRouteLimitsTheTextLength(): void
    {
        $routes = [];
        Functions\when('register_rest_route')->alias(function ($ns, $route, $args) use (&$routes) {
            $routes[$ns . $route] = $args;
            return true;
        });
        $this->plugin()->register_rest_routes();
        $validate = $routes['negaresh/v1/preview']['args']['text']['validate_callback'];

        self::assertTrue($validate('متن'));
        self::assertFalse($validate(str_repeat('a', Negaresh::PREVIEW_MAX_LENGTH + 1)));
        self::assertFalse($validate(['not', 'a', 'string']));
    }

    public function testPreviewRouteReturnsTheFixedText(): void
    {
        $request = new \WP_REST_Request(['text' => '<p>متن ...</p>', 'rules' => ['fix_three_dots' => true, 'remove_spaces_before_ellipsis' => true]]);

        self::assertSame(['text' => '<p>متن…</p>'], $this->plugin()->rest_preview($request));
        self::assertSame(['text' => ''], $this->plugin()->rest_preview(new \WP_REST_Request(['text' => ''])));
    }

    // Reset ------------------------------------------------------------------------------------

    public function testResetRulesRestoresRuleDefaultsAndKeepsScope(): void
    {
        $clean = (new Negaresh_Settings())->sanitize([
            'reset_rules' => 'Reset',
            'fix_dashes' => '',            // off in the form
            'fix_english_numbers' => '1',  // on in the form
            'mode' => 'display',
            'post_types' => ['post'],
            'apply_in_rest' => '1',
            'fix_titles' => '1',
        ]);

        foreach (Negaresh_Settings::RULE_DEFAULTS as $key => $default) {
            self::assertSame($default, $clean[$key], $key);
        }
        self::assertSame('display', $clean['mode']);
        self::assertSame(['post'], $clean['post_types']);
        self::assertTrue($clean['apply_in_rest']);
        self::assertTrue($clean['fix_titles']);
        self::assertArrayNotHasKey('reset_rules', $clean);
    }

    // Plugins screen ---------------------------------------------------------------------------

    public function testSettingsLinkComesFirstOnThePluginsScreen(): void
    {
        Functions\when('admin_url')->alias(function ($path) {
            return 'https://example.com/wp-admin/' . $path;
        });

        $links = (new Negaresh_Settings())->action_links(['deactivate' => '<a>Deactivate</a>']);

        self::assertSame('<a href="https://example.com/wp-admin/options-general.php?page=negaresh-options">Settings</a>', $links[0]);
        self::assertSame('<a href="https://example.com/wp-admin/tools.php?page=negaresh-bulk">Fix existing posts</a>', $links[1], 'P3-3');
        self::assertSame('<a>Deactivate</a>', $links['deactivate']);
    }

    // Titles and excerpts ----------------------------------------------------------------------

    public function testTitlesAndExcerptsAreOffByDefault(): void
    {
        $plugin = $this->plugin();
        $defaults = Negaresh_Settings::defaults();

        self::assertFalse($defaults['fix_titles']);
        self::assertFalse($defaults['fix_excerpts']);
        self::assertSame('عنوان ?', $plugin->filter_title('عنوان ?', 5));
        self::assertSame('خلاصه ...', $plugin->filter_excerpt('خلاصه ...'));
    }

    public function testTitlesAreFixedOnDisplayWhenEnabled(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_titles' => true, 'fix_question_mark' => true, 'fix_spacing_for_punctuations' => true];

        self::assertSame('عنوان؟', $this->plugin()->filter_title('عنوان ?', 5));
    }

    public function testTitlesOfOtherPostTypesAreLeftAlone(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_titles' => true, 'post_types' => ['page'], 'fix_question_mark' => true];
        Functions\when('get_post_type')->alias(function ($id = null) {
            return 5 === $id ? 'post' : 'page';
        });

        self::assertSame('عنوان ?', $this->plugin()->filter_title('عنوان ?', 5));
    }

    public function testExcerptsAreFixedOnDisplayWhenEnabled(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_excerpts' => true];

        self::assertSame('<p>خلاصه…</p>', $this->plugin()->filter_excerpt('<p>خلاصه ...</p>'));
    }

    public function testTitleAndExcerptAreFixedOnSaveWhenEnabled(): void
    {
        $this->options[Negaresh_Settings::OPTION] = [
            'fix_titles' => true, 'fix_excerpts' => true, 'fix_question_mark' => true,
            'fix_english_quotes' => true, 'fix_spacing_for_punctuations' => true,
        ];
        Functions\when('get_post_types')->justReturn(['post' => 'post']);
        $data = [
            'post_content' => addslashes('<p>متن</p>'),
            'post_title' => addslashes('او گفت "سلام" ?'),
            'post_excerpt' => addslashes('خلاصه ...'),
            'post_type' => 'post',
        ];

        $out = $this->plugin()->filter_post_data($data, []);

        self::assertSame(addslashes('او گفت «سلام»؟'), $out['post_title']);
        self::assertSame(addslashes('خلاصه…'), $out['post_excerpt']);
    }

    public function testMarkerChangesWhenTitleOrExcerptScopeChanges(): void
    {
        $before = (new Negaresh_Settings())->rules_hash();
        $this->options[Negaresh_Settings::OPTION] = ['fix_titles' => true];

        self::assertNotSame($before, (new Negaresh_Settings())->rules_hash());
    }
}
