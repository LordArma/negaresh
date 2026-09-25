<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh\Vendor\Virastar\Virastar;
use Negaresh_Settings;

/**
 * The the_content filter, with WordPress mocked.
 */
class NegareshFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        $this->stubFrontEnd();
    }

    private function plugin(?Negaresh_Settings $settings = null): Negaresh
    {
        return new Negaresh($settings ?? new Negaresh_Settings());
    }

    public function testConstructorRegistersHooks(): void
    {
        $plugin = $this->plugin();

        self::assertNotFalse(has_filter('the_content', [$plugin, 'filter_content']));
        self::assertNotFalse(has_action('init', [$plugin, 'load_textdomain']));
        self::assertNotFalse(has_action('update_option_negaresh_options', [$plugin, 'reset']));
    }

    public function testFixesPersianTextAndKeepsMarkup(): void
    {
        $in = '<p>به <a href="https://example.com/?a=1&amp;b=2">این صفحه</a> سر بزنید...</p>';

        self::assertSame(
            '<p>به <a href="https://example.com/?a=1&amp;b=2">این صفحه</a> سر بزنید…</p>',
            $this->plugin()->filter_content($in)
        );
    }

    public function testB2EntitiesAreNotDecodedAndTagsStayInPlace(): void
    {
        $in = '<p>A&amp;B و &lt;script&gt; متن است.</p><p>بعدی</p>';
        $out = $this->plugin()->filter_content($in);

        self::assertSame(self::tags($in), self::tags($out));
        self::assertStringContainsString('A&amp;B', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testB3ShortcodeTagsAreKept(): void
    {
        $in = '<p>گالری [gallery ids="1,2" columns="3"] و [caption id="x"]شرح عکس ها[/caption] و [[escaped]]</p>';
        $out = $this->plugin()->filter_content($in);

        self::assertStringContainsString('[gallery ids="1,2" columns="3"]', $out);
        self::assertStringContainsString('[caption id="x"]', $out);
        self::assertStringContainsString('[/caption]', $out);
        self::assertStringContainsString('[[escaped]]', $out);
    }

    public function testB3ShortcodeInsideAttributeIsKept(): void
    {
        $in = '<p><a href="[site_url]/a" title="[x y=1]">پیوند</a> متن...</p>';
        $out = $this->plugin()->filter_content($in);

        self::assertSame(self::tags($in), self::tags($out));
        self::assertStringContainsString('متن…', $out);
    }

    public function testB3CodeAndScriptContentsAreNotTouched(): void
    {
        $pre = "<pre class=\"x\"><code>if (a ... b) { echo \"x\"; } // 123 ...</code></pre>";
        $script = '<script>var s = "a ... b"; // ...</script>';
        $style = '<style>.a::after{content:"..."}</style>';
        $inline = '<code>$a = "..." ;</code>';
        $in = "<p>کد ... زیر:</p>\n$pre\n<p>و $inline در متن ...</p>$script$style";
        $out = $this->plugin()->filter_content($in);

        foreach ([$pre, $script, $style, $inline] as $kept) {
            self::assertStringContainsString($kept, $out);
        }
        self::assertStringContainsString('<p>کد… زیر:</p>', $out);
    }

    public function testB3PersianTextInBracketsIsStillFixed(): void
    {
        $out = $this->plugin()->filter_content('<p>[یادداشت ...] متن</p>');

        self::assertStringContainsString('[یادداشت…]', $out);
    }

    public function testB5DefaultsApplyWithoutSavedOptions(): void
    {
        // Nothing in wp_options: 4.0 treated every rule as off here.
        $out = $this->plugin()->filter_content('<p>عدد ٤٥٦ و ...</p>');

        self::assertSame('<p>عدد ۴۵۶ و…</p>', $out);
    }

    public function testSavedOptionsAreUsed(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['fix_three_dots' => false, 'fix_english_numbers' => true];
        $out = $this->plugin()->filter_content('<p>عدد 123 و ...</p>');

        self::assertStringContainsString('۱۲۳', $out);
        self::assertStringNotContainsString('…', $out);
    }

    public function testB6NoLineBreakTagsAreAdded(): void
    {
        $in = "<p>خط اول</p>\n<ul>\n<li>مورد</li>\n</ul>\n<pre>a\nb</pre>";
        $out = $this->plugin()->filter_content($in);

        self::assertStringNotContainsString('<br', $out);
        self::assertStringContainsString("<pre>a\nb</pre>", $out);
    }

    public function testB7ErrorsReturnOriginalContentAndPrintNothing(): void
    {
        $settings = new ThrowingSettings();
        $in = '<p>متن ...</p>';

        // beStrictAboutOutputDuringTests fails the test if anything is echoed.
        self::assertSame($in, $this->plugin($settings)->filter_content($in));
    }

    public function testInvalidUtf8IsLeftAlone(): void
    {
        $in = "<p>متن \xB1\x31 ...</p>";

        self::assertSame($in, $this->plugin()->filter_content($in));
    }

    public function testContentWithoutPersianIsLeftAlone(): void
    {
        $in = '<p>Hello "world" ... 123</p>';

        self::assertSame($in, $this->plugin()->filter_content($in));
    }

    public function testNonStringAndEmptyContentPassThrough(): void
    {
        self::assertNull($this->plugin()->filter_content(null));
        self::assertSame('  ', $this->plugin()->filter_content('  '));
    }

    public function testB10AdminScreensAreSkipped(): void
    {
        Functions\when('is_admin')->justReturn(true);

        self::assertSame('<p>متن ...</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testB10AdminAjaxIsFixed(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);

        self::assertSame('<p>متن…</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testB10FeedsCanBeExcluded(): void
    {
        Functions\when('is_feed')->justReturn(true);
        $this->options[Negaresh_Settings::OPTION] = ['apply_in_feeds' => false];

        self::assertSame('<p>متن ...</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testB10PostTypesCanBeLimited(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['post_types' => ['page']];

        self::assertSame('<p>متن ...</p>', $this->plugin()->filter_content('<p>متن ...</p>'));

        $this->stubFrontEnd('page');
        self::assertSame('<p>متن…</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testB10RestCanBeExcluded(): void
    {
        define('REST_REQUEST', true);
        $this->options[Negaresh_Settings::OPTION] = ['apply_in_rest' => false];

        self::assertSame('<p>متن ...</p>', $this->plugin()->filter_content('<p>متن ...</p>'));
    }

    public function testB18EveryVirastarOptionIsPassedExplicitly(): void
    {
        $passed = $this->plugin()->virastar_options();
        $all = (new Virastar())->getOptions();

        self::assertEqualsCanonicalizing(array_keys($all), array_keys($passed));
        self::assertFalse($passed['decode_html_entities']);
        self::assertFalse($passed['markdown_normalize_lists']);
        self::assertFalse($passed['markdown_normalize_braces']);
        self::assertTrue($passed['preserve_HTML']);
    }

    public function testOptionChangeRebuildsVirastar(): void
    {
        $plugin = $this->plugin();
        self::assertSame('<p>متن…</p>', $plugin->filter_content('<p>متن ...</p>'));

        $this->options[Negaresh_Settings::OPTION] = ['fix_three_dots' => false];
        $plugin->reset();

        self::assertSame('<p>متن ...</p>', $plugin->filter_content('<p>متن ...</p>'));
    }
}

/** Settings that work for should_filter() and then throw inside the fix. */
class ThrowingSettings extends Negaresh_Settings
{
    private $calls = 0;

    public function get(): array
    {
        if (++$this->calls > 1) {
            throw new \RuntimeException('boom <b>');
        }
        return parent::get();
    }
}
