<?php

namespace Negaresh\Tests\Unit;

use Alirezasedghi\Virastar\Virastar;

/**
 * B1: Virastar must give back everything it sets aside before cleaning text.
 *
 * Only B1 is covered here. decode_html_entities is switched off because it is
 * broken separately (B2); brackets and code blocks are B3.
 */
class VirastarPreservationTest extends TestCase
{
    private function virastar(array $options = []): Virastar
    {
        return new Virastar(array_merge(['decode_html_entities' => false], $options));
    }

    public function fixtureProvider(): array
    {
        return [
            'classic editor post' => ['classic-post.html'],
            'block editor post' => ['gutenberg-post.html'],
        ];
    }

    /** @dataProvider fixtureProvider */
    public function testHtmlTagsSurviveInOrder(string $fixture): void
    {
        $in = self::fixture($fixture);
        $out = $this->virastar()->cleanup($in);

        self::assertNoPreserverLeftovers($out);
        self::assertSame(self::tags($in), self::tags($out));
    }

    /** @dataProvider fixtureProvider */
    public function testHtmlCommentsSurviveInOrder(string $fixture): void
    {
        $in = self::fixture($fixture);
        $out = $this->virastar()->cleanup($in);

        self::assertSame(self::comments($in), self::comments($out));
    }

    public function testEntitiesAndNbspSurvive(): void
    {
        $in = self::fixture('classic-post.html');
        $out = $this->virastar()->cleanup($in);

        self::assertNoPreserverLeftovers($out);
        self::assertSame(self::entities($in), self::entities($out));
    }

    public function testBareUrlsSurvive(): void
    {
        $out = $this->virastar()->cleanup('سایت ما example.com و آدرس https://example.org/path?a=1 است.');

        self::assertNoPreserverLeftovers($out);
        self::assertStringContainsString('example.com', $out);
        self::assertStringContainsString('https://example.org/path?a=1', $out);
    }

    public function testMarkdownLinksSurvive(): void
    {
        $out = $this->virastar()->cleanup('این [پیوند](https://example.com/a_b) است.');

        self::assertNoPreserverLeftovers($out);
        self::assertStringContainsString('(https://example.com/a_b)', $out);
    }

    public function testBracketsAndBracesSurviveWhenPreserved(): void
    {
        $in = 'متن [gallery ids="1,2"] و {مقدار , ثابت} پایان';
        $out = $this->virastar(['preserve_brackets' => true, 'preserve_braces' => true])->cleanup($in);

        self::assertNoPreserverLeftovers($out);
        self::assertStringContainsString('[gallery ids="1,2"]', $out);
        self::assertStringContainsString('{مقدار , ثابت}', $out);
    }

    public function testTextIsStillCleaned(): void
    {
        $out = $this->virastar()->cleanup('<p>سلام دوستان ، کتاب ها را بخوانید !</p>');

        self::assertSame('<p>سلام دوستان، کتاب‌ها را بخوانید!</p>', $out);
    }

    public function testSpacingAroundTagsIsKept(): void
    {
        $in = '<p>به <a href="https://example.com">این صفحه</a> سر بزنید.</p><p>دوم.</p>';

        self::assertSame($in, $this->virastar()->cleanup($in));
    }

    public function testNoSpaceAddedBeforePreservedContentAfterPunctuation(): void
    {
        // fixPunctuationSpacing used to backtrack past its "not before a preserver" guard
        self::assertSame('<p>تمام.</p>', $this->virastar()->cleanup('<p>تمام.</p>'));
        self::assertSame('گفت،<b>بله</b>', $this->virastar()->cleanup('گفت،<b>بله</b>'));
    }

    public function testManyPreservedItemsComeBackInOrder(): void
    {
        $in = '';
        for ($i = 1; $i <= 50; $i++) {
            $in .= '<span data-i="' . $i . '">متن ' . $i . '</span>';
        }
        $out = $this->virastar()->cleanup($in);

        self::assertSame(self::tags($in), self::tags($out));
    }
}
