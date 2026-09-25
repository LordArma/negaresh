<?php

namespace Negaresh\Tests\Unit;

use Negaresh;
use Negaresh_Settings;

/**
 * I2: Virastar only ever sees text between tags. Markup, attributes and the whitespace at every
 * text/tag boundary come back byte for byte, with every rule switched on.
 */
class HtmlProcessingTest extends TestCase
{
    /** @var Negaresh */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $all = array_fill_keys(array_keys(Negaresh_Settings::RULE_DEFAULTS), true);
        $all['remove_diacritics'] = false; // conflicts with fix_diacritics
        $this->stubOptions([Negaresh_Settings::OPTION => $all]);
        $this->stubFrontEnd();
        $this->plugin = new Negaresh(new Negaresh_Settings());
    }

    /**
     * Tags as a correct tokenizer sees them: quoted attribute values may contain ">".
     *
     * @return list<string>
     */
    private static function realTags(string $html): array
    {
        preg_match_all('#<(?:!--[\s\S]*?-->|/?[A-Za-z][^\s/>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>)#', $html, $m);
        return $m[0];
    }

    /**
     * @return array<string, array{string}>
     */
    public function markupProvider(): array
    {
        return [
            'B24 > inside a double quoted attribute' => ['<p><a title="او گفت > "بله"" href="/x">متن</a> ادامه</p>'],
            'B24 > inside alt, quotes in the text after it' => ['<p><img alt="a > b" src="x.png"> او گفت "سلام" به ما</p>'],
            'B24 > inside a single quoted attribute' => ["<p><span data-x='1 > 0'>متن ...</span> و \"نقل\"</p>"],
            'digits and punctuation inside attributes' => ['<p><span data-n="123" class="c-2" title="a, b ; c ?">عدد 456</span></p>'],
            'comment containing tags' => ['<p>متن ...</p><!-- <b>یادداشت ...</b> "x" --><p>دوم ...</p>'],
            'uppercase tags' => ['<P>متن ...<BR>خط</P>'],
            'fixtures: classic post' => [self::fixture('classic-post.html')],
            'fixtures: block post' => [self::fixture('gutenberg-post.html')],
        ];
    }

    /** @dataProvider markupProvider */
    public function testMarkupIsNeverChanged(string $in): void
    {
        $out = $this->plugin->fix($in);

        self::assertSame(self::realTags($in), self::realTags($out));
        self::assertSame(self::comments($in), self::comments($out));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function boundaryProvider(): array
    {
        return [
            'B25 space after ellipsis before an inline tag' => ['<p>متن ... <em>تاکید</em> ادامه</p>', '<p>متن… <em>تاکید</em> ادامه</p>'],
            'B25 space after ellipsis before a link' => ['<p>بخوانید ... <a href="/x">پیوند</a></p>', '<p>بخوانید… <a href="/x">پیوند</a></p>'],
            'no space is added where there was none' => ['<p>متن<b>پررنگ</b>متن</p>', '<p>متن<b>پررنگ</b>متن</p>'],
            'half space before a tag is kept' => ["<p>می\u{200c}<b>روم</b> خانه</p>", "<p>می\u{200c}<b>روم</b> خانه</p>"],
            'punctuation spacing inside a text node' => ['<p>سلام ، <b>دنیا</b> و بعد !</p>', '<p>سلام، <b>دنیا</b> و بعد!</p>'],
            'whitespace between blocks is untouched' => ["<p>یک</p>\n\n\n\n<p>دو</p>\n", "<p>یک</p>\n\n\n\n<p>دو</p>\n"],
            'text around a shortcode keeps its spaces' => ['<p>گالری ... [gallery ids="1,2"] ادامه ...</p>', '<p>گالری… [gallery ids="1,2"] ادامه…</p>'],
        ];
    }

    /** @dataProvider boundaryProvider */
    public function testWhitespaceAtBoundariesIsKept(string $in, string $want): void
    {
        self::assertSame($want, $this->plugin->fix($in));
    }

    /**
     * @return array<string, array{string}>
     */
    public function protectedProvider(): array
    {
        return [
            'nested svg' => ['<svg viewBox="0 0 1 1"><svg><text>متن ... "x"</text></svg><text>باز ...</text></svg>'],
            'code with > in an attribute' => ['<code data-x="a>b">x ... y "z"</code>'],
            'unclosed pre keeps the rest untouched' => ['<pre>کد ... "x"<p>متن ...</p>'],
            'uppercase script' => ['<SCRIPT>var a = "...";</SCRIPT>'],
            'textarea' => ['<textarea name="t">متن ... ?</textarea>'],
        ];
    }

    /** @dataProvider protectedProvider */
    public function testProtectedElementsAreUntouched(string $block): void
    {
        $out = $this->plugin->fix('<p>قبل ...</p>' . $block);

        self::assertSame('<p>قبل…</p>' . $block, $out);
    }

    public function testTextNodesAreStillFixed(): void
    {
        $out = $this->plugin->fix('<ul><li>کتاب ها</li><li>عدد 123 ?</li></ul>');

        self::assertSame("<ul><li>کتاب\u{200c}ها</li><li>عدد ۱۲۳؟</li></ul>", $out);
    }
}
