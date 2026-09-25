<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Settings;

/**
 * I10b: words and phrases the admin wants left exactly as they are.
 */
class ProtectedWordsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $all = array_fill_keys(array_keys(Negaresh_Settings::RULE_DEFAULTS), true);
        $all['remove_diacritics'] = false;
        $this->stubOptions([Negaresh_Settings::OPTION => $all + ['protected_words' => ['كتاب ها', 'Kasra ...', 'ي']]]);
        $this->stubFrontEnd();
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('sanitize_key')->alias(function ($key) {
            return strtolower((string) $key);
        });
        Functions\when('get_post_types')->justReturn([]);
    }

    private function plugin(): Negaresh
    {
        return new Negaresh(new Negaresh_Settings());
    }

    public function testListedPhraseIsLeftAloneAndTheRestIsFixed(): void
    {
        $out = $this->plugin()->fix('<p>عنوان كتاب ها است و كتاب ها را خواندید ?</p>');

        self::assertSame('<p>عنوان كتاب ها است و كتاب ها را خواندید؟</p>', $out);
    }

    public function testOnlyWholeWordsMatch(): void
    {
        // "ي" alone is listed, but inside "علي" (a longer word) it is still fixed.
        $out = $this->plugin()->fix('<p>علي و ي</p>');

        self::assertSame('<p>علی و ي</p>', $out);
    }

    public function testLatinPhrasesAndSpacesAroundThemAreKept(): void
    {
        $out = $this->plugin()->fix('<p>نام Kasra ... است ...</p>');

        self::assertSame('<p>نام Kasra ... است…</p>', $out);
    }

    public function testTextContainingThePlaceholderSpellingUsesTheSafeSplit(): void
    {
        $out = $this->plugin()->fix('<p>NGRSHKEEP0X و كتاب ها ?</p>');

        self::assertStringContainsString('NGRSHKEEP0X', $out, 'the literal text is kept');
        self::assertStringContainsString('كتاب ها', $out, 'the listed phrase is kept');
        self::assertStringContainsString('؟', $out, 'the rest is still fixed');
    }

    public function testPreviewUsesTheWordsFromThePage(): void
    {
        $rules = array_fill_keys(array_keys(Negaresh_Settings::RULE_DEFAULTS), true);
        $rules['remove_diacritics'] = false;
        $plugin = $this->plugin();

        self::assertSame('نام ويژه…', $plugin->preview('نام ويژه ...', $rules, ['ويژه']));
        self::assertSame('نام ویژه…', $plugin->preview('نام ويژه ...', $rules, []));
    }

    public function testSanitizeCleansTheList(): void
    {
        $clean = (new Negaresh_Settings())->sanitize([
            'protected_words' => "  كتاب ها \r\n\nكتاب ها\n" . str_repeat('ا', 150) . "\nTeX",
        ]);

        self::assertSame(['كتاب ها', str_repeat('ا', 100), 'TeX'], $clean['protected_words']);
    }

    public function testListIsCappedAt500Entries(): void
    {
        $lines = implode("\n", array_map(function ($i) {
            return 'word' . $i;
        }, range(1, 600)));

        $words = (new Negaresh_Settings())->sanitize(['protected_words' => $lines])['protected_words'];
        self::assertIsArray($words);
        self::assertCount(500, $words);
    }

    public function testChangingTheListChangesTheRulesHash(): void
    {
        $before = (new Negaresh_Settings())->rules_hash();
        $this->options[Negaresh_Settings::OPTION]['protected_words'] = ['دیگر'];

        self::assertNotSame($before, (new Negaresh_Settings())->rules_hash());
    }

    public function testCorruptStoredListIsNormalized(): void
    {
        $this->options[Negaresh_Settings::OPTION] = ['protected_words' => 'not a list'];

        self::assertSame([], (new Negaresh_Settings())->words());
    }
}
