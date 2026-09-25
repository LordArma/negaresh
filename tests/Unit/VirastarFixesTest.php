<?php

namespace Negaresh\Tests\Unit;

use Negaresh\Vendor\Virastar\Virastar;

/**
 * Local fixes to the vendored Virastar other than preservation (B1).
 * See docs/dev/ANALYSIS.md §4.1.
 */
class VirastarFixesTest extends TestCase
{
    public function testB2DecodeHtmlEntitiesDoesNotScrambleTags(): void
    {
        $in = '<p>A&amp;B و &lt;script&gt; متن است.</p><p>بعدی</p>';
        $out = (new Virastar(['decode_html_entities' => true]))->cleanup($in);

        self::assertSame(self::tags($in), self::tags($out));
        self::assertSame(self::entities($in), self::entities($out));
        self::assertStringNotContainsString('<script>', $out);
    }

    public function testB4ClassLivesInOwnNamespaceNextToUpstreamClass(): void
    {
        if (!class_exists('Alirezasedghi\Virastar\Virastar', false)) {
            // Stand in for the GDIC theme's copy; declaring it must not collide with ours.
            eval('namespace Alirezasedghi\Virastar; class Virastar {}');
        }

        self::assertTrue(class_exists('Alirezasedghi\Virastar\Virastar', false));
        self::assertTrue(class_exists(Virastar::class, false));
        self::assertNotSame('Alirezasedghi\Virastar\Virastar', Virastar::class);
    }

    public function testB20QuestionMarkBecomesPersianQuestionMark(): void
    {
        self::assertSame('خوبید؟', (new Virastar())->cleanup('خوبید ?'));
    }

    public function testB20CleanupZwnjCollapsesRepeatedZwnj(): void
    {
        $out = (new Virastar())->cleanup("می\u{200c}\u{200c}روم");

        self::assertNoPreserverLeftovers($out);
        self::assertSame("می\u{200c}روم", $out);
    }

    public function testB20SoftHyphenBecomesZwnj(): void
    {
        self::assertSame("می\u{200c}روم", (new Virastar())->cleanup("می\u{00ad}روم"));
    }

    public function testB20RlmBecomesZwnj(): void
    {
        $out = (new Virastar(['cleanup_zwnj' => false]))->cleanup("می\u{200f}روم");

        self::assertSame("می\u{200c}روم", $out);
    }

    public function testB20SuffixMiscFixesHehYeSuffix(): void
    {
        self::assertSame("خانه\u{200c}ای بزرگ", (new Virastar())->cleanup("خانه\u{200c}ئی بزرگ"));
    }

    public function testB20HamzehSuffixWithoutFixHamzeh(): void
    {
        $out = (new Virastar(['fix_hamzeh' => false, 'fix_suffix_spacing' => true]))->cleanup('خانه ی من');

        self::assertNoPreserverLeftovers($out);
        self::assertSame("خانه\u{200c}ی من", $out);
    }

    public function testB21FrontMatterIsPreserved(): void
    {
        $out = (new Virastar())->cleanup("---\ntitle: یک , دو\n---\nمتن اصلی .");

        self::assertStringContainsString("---\ntitle: یک , دو\n---\n", $out);
        self::assertStringEndsWith('متن اصلی.', $out);
    }

    /**
     * B22: the PHP port dropped the JS single space padding, so rules that need a space after a
     * word missed the last word of the text.
     */
    /**
     * @return array<string, array{array<string, bool>, string, string}>
     */
    public function b22Provider(): array
    {
        return [
            'suffix ها' => [['fix_suffix_spacing' => true], 'کتاب ها', "کتاب\u{200c}ها"],
            'suffix تر' => [['fix_suffix_spacing' => true], 'بزرگ تر', "بزرگ\u{200c}تر"],
            'hamzeh' => [[], 'خانه ی', 'خانهٔ'],
            'arabic hamzeh' => [['fix_hamzeh_arabic' => true], 'مدرسة', 'مدرسهٔ'],
        ];
    }

    /**
     * @dataProvider b22Provider
     * @param array<string, bool> $options
     */
    public function testB22RulesSeeTheLastWord(array $options, string $in, string $want): void
    {
        self::assertSame($want, (new Virastar($options))->cleanup($in));
    }

    public function testB22PaddingIsRemoved(): void
    {
        self::assertSame('متن', (new Virastar(['cleanup_begin_and_end' => false]))->cleanup('متن'));
        self::assertSame('<p>متن</p>', (new Virastar(['cleanup_begin_and_end' => false]))->cleanup('<p>متن</p>'));
    }

    /**
     * B26: normalizeEllipsis added a space after "…" even before a line break; in save mode (I4)
     * that trailing space was stored in every classic editor paragraph ending with "...".
     */
    public function testB26NoTrailingSpaceAfterEllipsisAtLineEnd(): void
    {
        $v = new Virastar();

        self::assertSame("اول…\nدوم… سوم…", $v->cleanup("اول ...\nدوم ... سوم ..."));
        self::assertSame("اول…\n\nدوم", $v->cleanup("اول…   \n\nدوم"));
        self::assertSame('یک… دو', $v->cleanup('یک…دو'));
    }
}
