<?php

namespace Negaresh\Tests\Unit;

/**
 * B15: translation files stay complete and in sync with the source.
 * Regenerate with the commands in CLAUDE.md §4 when a string changes.
 */
class I18nTest extends TestCase
{
    private const DIR = NEGARESH_PLUGIN_DIR . '/languages';

    /** @return array<string,string> msgid => msgstr, single line entries (all ours are) */
    private static function parsePo(string $file): array
    {
        preg_match_all('/^msgid "(.*)"\nmsgstr "(.*)"$/m', self::read($file), $m, PREG_SET_ORDER);
        $entries = [];
        foreach ($m as $entry) {
            if ('' !== $entry[1]) {
                $entries[stripcslashes($entry[1])] = stripcslashes($entry[2]);
            }
        }
        return $entries;
    }

    /**
     * Literal strings passed to the translation functions in the plugin source.
     *
     * @return list<string>
     */
    private static function sourceStrings(): array
    {
        $strings = [];
        foreach (self::pluginFiles() as $file) {
            preg_match_all("/\\b(?:__|esc_html__|esc_html_e|esc_attr__|_e)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'negaresh'/", self::read($file), $m);
            foreach ($m[1] as $s) {
                $strings[] = stripcslashes($s);
            }
        }
        return array_values(array_unique($strings));
    }

    public function testPotContainsEverySourceString(): void
    {
        $pot = self::parsePo(self::DIR . '/negaresh.pot');

        foreach (self::sourceStrings() as $string) {
            self::assertArrayHasKey($string, $pot, "not in negaresh.pot (run make-pot): $string");
        }
    }

    public function testPersianTranslationIsComplete(): void
    {
        $pot = self::parsePo(self::DIR . '/negaresh.pot');
        $po = self::parsePo(self::DIR . '/negaresh-fa_IR.po');

        self::assertSame(array_keys($pot), array_keys($po), 'fa_IR.po is out of sync with negaresh.pot');
        foreach ($po as $id => $str) {
            self::assertNotSame('', $str, "untranslated: $id");
        }
    }

    public function testMoMatchesPo(): void
    {
        $mo = self::read(self::DIR . '/negaresh-fa_IR.mo');
        $header = self::unpack('Vmagic/Vrevision/Vcount/Voriginals/Vtranslations', substr($mo, 0, 20));
        $translations = [];
        for ($i = 0; $i < $header['count']; $i++) {
            $orig = self::unpack('Vlength/Voffset', substr($mo, $header['originals'] + 8 * $i, 8));
            $tran = self::unpack('Vlength/Voffset', substr($mo, $header['translations'] + 8 * $i, 8));
            $translations[substr($mo, $orig['offset'], $orig['length'])] = substr($mo, $tran['offset'], $tran['length']);
        }
        unset($translations['']);

        self::assertSame(0x950412de, $header['magic']);
        $po = self::parsePo(self::DIR . '/negaresh-fa_IR.po');
        ksort($po);
        ksort($translations);
        self::assertSame($po, $translations, 'recompile the .mo (wp i18n make-mo)');
    }

    /**
     * @return array<string, int>
     */
    private static function unpack(string $format, string $data): array
    {
        $values = unpack($format, $data);
        self::assertIsArray($values, 'truncated .mo file');
        return $values;
    }
}
