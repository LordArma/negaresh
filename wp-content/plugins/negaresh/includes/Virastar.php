<?php

// Negaresh patch (B4): own namespace, so another copy of Virastar (for example the GDIC theme)
// can be loaded at the same time without a "Cannot declare class" fatal error.
namespace Negaresh\Vendor\Virastar;

use Exception;

class Virastar
{
    private $charsPersian = 'ءاآأإئؤبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهیةيك';

    // @REF: https://en.wikipedia.org/wiki/Persian_alphabet#Diacritics
    // `\u064e\u0650\u064f\u064b\u064d\u064c\u0651\u06c0`
    private $charsDiacritic = 'ًٌٍَُِّْ';

    private $patternURI = '#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#sim';
    private $patternAfter = '\\s.,;،؛!؟?"\'()[\\]{}“”«»';

    private $defaults = [
        "cleanup_begin_and_end" => true,
        "cleanup_extra_marks" => true,
        "cleanup_kashidas" => true,
        "cleanup_line_breaks" => true,
        "cleanup_rlm" => true,
        "cleanup_spacing" => true,
        "cleanup_zwnj" => true,
        "decode_html_entities" => true,
        "fix_arabic_numbers" => true,
        "fix_dashes" => true,
        "fix_diacritics" => true,
        "fix_english_numbers" => true,
        "fix_english_quotes_pairs" => true,
        "fix_english_quotes" => true,
        "fix_hamzeh" => true,
        "fix_hamzeh_arabic" => false,
        "fix_misc_non_persian_chars" => true,
        "fix_misc_spacing" => true,
        "fix_numeral_symbols" => true,
        "fix_prefix_spacing" => true,
        "fix_persian_glyphs" => true,
        "fix_punctuations" => true,
        "fix_question_mark" => true,
        "fix_spacing_for_braces_and_quotes" => true,
        "fix_spacing_for_punctuations" => true,
        "fix_suffix_misc" => true,
        "fix_suffix_spacing" => true,
        "fix_three_dots" => true,
        "kashidas_as_parenthetic" => true,
        "markdown_normalize_braces" => true,
        "markdown_normalize_lists" => true,
        "normalize_dates" => true,
        "normalize_ellipsis" => true,
        "normalize_eol" => true,
        "preserve_braces" => false,
        "preserve_brackets" => false,
        "preserve_comments" => true,
        "preserve_entities" => true,
        "preserve_front_matter" => true,
        "preserve_HTML" => true,
        "preserve_nbsp" => true,
        "preserve_URIs" => true,
        "remove_diacritics" => false,
        "remove_spaces_before_ellipsis" => true, // Negaresh patch (I11): Virastar.js 0.22 option
        "skip_markdown_ordered_lists_numbers_conversion" => false
    ];

    private $digits = '۱۲۳۴۵۶۷۸۹۰';

    private $entities = [
        'sbquo;' => '\x{201a}',
        'lsquo;' => '\x{2018}',
        'lsquor;' => '\x{201a}',
        'ldquo;' => '\x{201c}',
        'ldquor;' => '\x{201e}',
        'rdquo;' => '\x{201d}',
        'rdquor;' => '\x{201d}',
        'rsquo;' => '\x{2019}',
        'rsquor;' => '\x{2019}',
        'apos;' => '\'',
        'QUOT;' => '"',
        'QUOT' => '"',
        'quot;' => '"',
        'quot' => '"',
        'zwj;' => '\x{200d}',
        'ZWNJ;' => '\x{200c}',
        'zwnj;' => '\x{200c}',
        'shy;' => '\x{00ad}' // wrongly used as zwnj
    ];

    private $glyphs = [
        '\x{200c}ه' => 'ﻫ',
        'ی\x{200c}' => 'ﻰﻲ',
        'ﺃ' => 'ﺄﺃ',
        'ﺁ' => 'ﺁﺂ',
        'ﺇ' => 'ﺇﺈ',
        'ا' => 'ﺎا',
        'ب' => 'ﺏﺐﺑﺒ',
        'پ' => 'ﭖﭗﭘﭙ',
        'ت' => 'ﺕﺖﺗﺘ',
        'ث' => 'ﺙﺚﺛﺜ',
        'ج' => 'ﺝﺞﺟﺠ',
        'چ' => 'ﭺﭻﭼﭽ',
        'ح' => 'ﺡﺢﺣﺤ',
        'خ' => 'ﺥﺦﺧﺨ',
        'د' => 'ﺩﺪ',
        'ذ' => 'ﺫﺬ',
        'ر' => 'ﺭﺮ',
        'ز' => 'ﺯﺰ',
        'ژ' => 'ﮊﮋ',
        'س' => 'ﺱﺲﺳﺴ',
        'ش' => 'ﺵﺶﺷﺸ',
        'ص' => 'ﺹﺺﺻﺼ',
        'ض' => 'ﺽﺾﺿﻀ',
        'ط' => 'ﻁﻂﻃﻄ',
        'ظ' => 'ﻅﻆﻇﻈ',
        'ع' => 'ﻉﻊﻋﻌ',
        'غ' => 'ﻍﻎﻏﻐ',
        'ف' => 'ﻑﻒﻓﻔ',
        'ق' => 'ﻕﻖﻗﻘ',
        'ک' => 'ﮎﮏﮐﮑﻙﻚﻛﻜ',
        'گ' => 'ﮒﮓﮔﮕ',
        'ل' => 'ﻝﻞﻟﻠ',
        'م' => 'ﻡﻢﻣﻤ',
        'ن' => 'ﻥﻦﻧﻨ',
        'ه' => 'ﻩﻪﻫﻬ',
        'هٔ' => 'ﮤﮥ',
        'و' => 'ﻭﻮ',
        'ﺅ' => 'ﺅﺆ',
        'ی' => 'ﯼﯽﯾﯿﻯﻰﻱﻲﻳﻴ',
        'ئ' => 'ﺉﺊﺋﺌ',
        'لا' => 'ﻼ',
        'ﻹ' => 'ﻺ',
        'ﻷ' => 'ﻸ',
        'ﻵ' => 'ﻶ'
    ];

    // Options
    private $options = [];

    /**
     * Construction
     *
     * @throws Exception
     */
    public function __construct($options = [])
    {
        if (!empty($options))
            if (is_array($options))
                $this->setOptions($options);
            else
                throw new Exception('Options should be an array.');
    }

    /**
     * Set options or update it
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $this->parseOptions($options);
    }

    /**
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return !empty($this->options) ? $this->options : $this->defaults;
    }

    /**
     * Parse options
     *
     * @param $options
     * @return array
     */
    public function parseOptions($options)
    {
        if (is_object($options)) {
            $parsed_args = get_object_vars($options);
        } elseif (is_array($options)) {
            $parsed_args =& $options;
        } else {
            parse_str((string)$options, $parsed_args);
        }

        $defaults = $this->defaults;

        if (!empty($parsed_args))
            return array_merge($defaults, $parsed_args);

        return $defaults;
    }

    /**
     * Converts numeral and selected html character-sets into original characters
     *
     * @param $text
     * @return string
     */
    public function decodeHTMLEntities($text)
    {
        // Negaresh patch (B2): upstream replaced every entity with an HTML placeholder it never
        // stored, which scrambled the restored tags. Decoding `&lt;` into `<` would also turn
        // escaped text into live markup, so this is a deliberate no-op.
        return $text;
    }

    /**
     * Cleanup Text
     *
     * @param $text
     * @return string
     * @throws Exception
     */
    public function cleanup($text)
    {
        if (!is_string($text))
            throw new Exception('Expected a String, but received ' . gettype($text));

        // trim text
        $text = trim($text);

        // don't bother if its empty or whitespace
        if (empty($text))
            return $text;

        $options = $this->getOptions();

        // Negaresh patch (B22): single space paddings around the string, as in the JS original.
        // Rules that need a following/preceding space now also see the first and last word;
        // the paddings are removed at the end of cleanup().
        $text = ' ' . $text . ' ';

        // preserves front matter data in the text
        if ($options["preserve_front_matter"]) {
            $front_matter = [];
            $text = preg_replace_callback('/^ ---[\S\s]*?---\n/' /* Negaresh patch (B21): matches again thanks to the B22 padding */, function ($matched) use (&$front_matter) { // Negaresh patch (B1): by reference
                $front_matter[] = $matched[0];
                return ' __FRONT__MATTER__PRESERVER__ ';
            }, $text);
        }

        // preserves all html tags in the text
        // @props: @wordpress/wordcount
        if ($options["preserve_HTML"]) {
            $html = [];
            $text = preg_replace_callback('/<\/?[a-z][^>]*?>/i', function ($matched) use (&$html) { // Negaresh patch (B1): by reference
                $html[] = $matched[0];
                return ' __HTML__PRESERVER__ ';
            }, $text);
        }

        // preserves all html comments in the text
        // @props: @wordpress/wordcount
        if ($options["preserve_comments"]) {
            $comments = [];
            $text = preg_replace_callback('/<!--[\s\S]*?-->/', function ($matched) use (&$comments) { // Negaresh patch (B1): by reference
                $comments[] = $matched[0];
                return ' __COMMENT__PRESERVER__ ';
            }, $text);
        }

        // preserves strings inside square brackets (`[]`)
        if ($options["preserve_brackets"]) {
            $brackets = [];
            $text = preg_replace_callback('/(\[.*?\])/', function ($matched) use (&$brackets) { // Negaresh patch (B1): by reference
                $brackets[] = $matched[0];
                return ' __BRACKETS__PRESERVER__ ';
            }, $text);
        }

        // preserve strings inside curly braces (`{}`)
        if ($options["preserve_braces"]) {
            $braces = [];
            $text = preg_replace_callback('/(\{.*?\})/', function ($matched) use (&$braces) { // Negaresh patch (B1): by reference
                $braces[] = $matched[0];
                return ' __BRACES__PRESERVER__ ';
            }, $text);
        }

        // preserves all uri strings in the text
        if ($options["preserve_URIs"]) {
            $md_links = [];
            $uris = [];

            // stores Markdown links separately
            $text = preg_replace_callback('/]\((.*?)\)/', function ($matched) use (&$md_links) { // Negaresh patch (B1): by reference
                if (is_string($matched))
                    $matched = [$matched];
                if (isset($matched[1])) {
                    $md_links[] = trim($matched[1]);
                    return '](__MD_LINK__PRESERVER__)'; // no padding!
                }
                return $matched[0];
            }, $text);

            $text = preg_replace_callback($this->patternURI, function ($matched) use (&$uris) { // Negaresh patch (B1): by reference
                $uris[] = $matched[0];
                return ' __URI__PRESERVER__ ';
            }, $text);
        }

        // preserves all no-break space entities in the text
        if ($options["preserve_nbsp"]) {
            $nbsps = [];
            $text = preg_replace_callback('/&nbsp;|&#160;/iu', function ($matched) use (&$nbsps) { // Negaresh patch (B1): by reference
                $nbsps[] = $matched[0];
                return ' __NBSPS__PRESERVER__ ';
            }, $text);
        }

        if ($options["decode_html_entities"]) {
            $text = $this->decodeHTMLEntities($text);
        }

        // preserves all html entities in the text
        // @props: @substack/node-ent
        if ($options["preserve_entities"]) {
            $entities = [];
            $text = preg_replace_callback('/&(#?[^;\W]+;?)/', function ($matched) use (&$entities) { // Negaresh patch (B1): by reference
                $entities[] = $matched[0];
                return ' __ENTITIES__PRESERVER__ ';
            }, $text);
        }

        if ($options["normalize_eol"]) {
            $text = $this->normalizeEOL($text);
        }

        if ($options["fix_persian_glyphs"]) {
            $text = $this->fixPersianGlyphs($text);
        }

        // Negaresh patch (I11): as in Virastar.js, on the whole text before the other rules (the PHP
        // port ran it per word, missing letters next to punctuation)
        if ($options["fix_misc_non_persian_chars"]) {
            $text = $this->fixMiscNonPersianChars($text);
        }

        if ($options["fix_dashes"]) {
            $text = $this->fixDashes($text);
        }

        if ($options["fix_three_dots"]) {
            $text = $this->fixThreeDots($text);
        }

        if ($options["normalize_ellipsis"]) {
            $text = $this->normalizeEllipsis($text);
        }

        // Negaresh patch (I11): Virastar.js option, default on
        if ($options["remove_spaces_before_ellipsis"]) {
            $text = $this->removeSpaceBeforeEllipsis($text);
        }

        if ($options["fix_english_quotes_pairs"]) {
            $text = $this->fixEnglishQuotesPairs($text);
        }

        if ($options["fix_english_quotes"]) {
            $text = $this->fixEnglishQuotes($text);
        }

        if ($options["fix_hamzeh"]) {
            if ($options["fix_hamzeh_arabic"]) {
                $text = $this->fixHamzehArabic($text);
            }
            $text = $this->fixHamzeh($text);
        }
        else if ($options["fix_suffix_spacing"]) {
            if ($options["fix_hamzeh_arabic"]) {
                $text = $this->fixHamzehArabicAlt($text);
            }
            $text = $this->fixSuffixSpacingHamzeh($text);
        }

        if ($options["cleanup_rlm"]) {
            $text = $this->cleanupRLM($text);
        }

        if ($options["cleanup_zwnj"]) {
            $text = $this->cleanupZWNJ($text);
        }

        if ($options["fix_arabic_numbers"]) {
            $text = $this->fixArabicNumbers($text);
        }

        // word tokenizer
        // Negaresh patch (B29): /u, or « and » (two bytes) were split and the word became invalid UTF-8
        $text = preg_replace_callback('/(^|\s+)([[({"\'“«]?)(\S+)([\])}"\'”»]?)(?=($|\s+))/u', function ($matches) use ($options) {
            $matched = $matches[0] ?? '';
            // $before = $matches[1] ?? '';
            // $leading = $matches[2] ?? '';
            $word = $matches[3] ?? '';
            $trailing = $matches[4] ?? '';
            $after = $matches[5] ?? '';

            // should not replace to persian chars in english phrases
            preg_match('/[a-zA-Z\-_]{2,}/u', $word, $word_match);
            if ($word_match) {
                return $matched;
            }

            // should not touch sprintf directives
            unset($word_match);
            // Negaresh patch (B30): single quotes, so `\$` reaches PCRE as a literal dollar sign (in double
            // quotes PHP ate the backslash and `$` meant "end of text", so %1$s lost its digit)
            preg_match_all('/%(?:\d+\$)?[+-]?(?:[ 0]|\'.{1})?-?\d*(?:\.\d+)?[bcdeEufFgGosxX]/u', $word, $word_match);
            if ($word_match && ( isset($word_match[0]) && !empty($word_match[0]) )) {
                return $matched;
            }

            // should not touch numbers in html entities
            unset($word_match);
            preg_match('/&#\d+;/u', $word, $word_match);
            if ($word_match) {
                return $matched;
            }

            // skips converting english numbers of ordered lists in markdown
            unset($word_match);
            preg_match('/(?:(?:\r?\n)|(?:\r\n?)|(?:^|\n))\d+\.\s/', $matched . $trailing . $after, $word_match);
            if ($options["skip_markdown_ordered_lists_numbers_conversion"] && $word_match) {
                return $matched;
            }

            if ($options["fix_english_numbers"]) {
                $matched = $this->fixEnglishNumbers($matched);
            }

            if ($options["fix_numeral_symbols"]) {
                $matched = $this->fixNumeralSymbols($matched);
            }

            if ($options["fix_punctuations"]) {
                $matched = $this->fixPunctuations($matched);
            }

            if ($options["fix_question_mark"]) {
                $matched = $this->fixQuestionMark($matched);
            }

            return $matched;
        }, $text);

        if ($options["normalize_dates"]) {
            $text = $this->normalizeDates($text);
        }

        if ($options["fix_prefix_spacing"]) {
            $text = $this->fixPrefixSpacing($text);
        }

        if ($options["fix_suffix_spacing"]) {
            $text = $this->fixSuffixSpacing($text);
        }

        if ($options["fix_suffix_misc"]) {
            $text = $this->fixSuffixMisc($text);
        }

        if ($options["fix_spacing_for_braces_and_quotes"]) {
            $text = $this->fixBracesSpacing($text);
        }

        if ($options["cleanup_extra_marks"]) {
            $text = $this->cleanupExtraMarks($text);
        }

        if ($options["fix_spacing_for_punctuations"]) {
            $text = $this->fixPunctuationSpacing($text);
        }

        if ($options["kashidas_as_parenthetic"]) {
            $text = $this->kashidasAsParenthetic($text);
        }

        if ($options["cleanup_kashidas"]) {
            $text = $this->cleanupKashidas($text);
        }

        if ($options["markdown_normalize_braces"]) {
            $text = $this->markdownNormalizeBraces($text);
        }

        if ($options["markdown_normalize_lists"]) {
            $text = $this->markdownNormalizeLists($text);
        }

        // doing it again after `fixPunctuationSpacing()`
        if ($options["fix_spacing_for_braces_and_quotes"]) {
            $text = $this->fixBracesSpacingInside($text);
        }

        if ($options["fix_misc_spacing"]) {
            $text = $this->fixMiscSpacing($text);
        }

        if ($options["remove_diacritics"]) {
            $text = $this->removeDiacritics($text);
        } else if ($options["fix_diacritics"]) {
            $text = $this->fixDiacritics($text);
        }

        if ($options["cleanup_spacing"]) {
            $text = $this->cleanupSpacing($text);
        }

        if ($options["cleanup_zwnj"]) {
            $text = $this->cleanupZWNJLate($text);
        }

        if ($options["cleanup_line_breaks"]) {
            $text = $this->cleanupLineBreaks($text);
        }

        // bringing back entities
        if ($options["preserve_entities"]) {
            // Negaresh patch (B1): restore what was captured, not the entity name table
            $entities = $entities ?? [];
            $text = preg_replace_callback('/[ ]?__ENTITIES__PRESERVER__[ ]?/', function () use (&$entities) {
                return (string) array_shift($entities);
            }, $text);
        }

        // bringing back nbsp
        if ($options["preserve_nbsp"]) {
            $nbsps = $nbsps ?? [];
            $text = preg_replace_callback('/[ ]?__NBSPS__PRESERVER__[ ]?/', function () use (&$nbsps) { // Negaresh patch (B1): by reference
                return (string) array_shift($nbsps);
            }, $text);
        }

        // bringing back URIs
        if ($options["preserve_URIs"]) {
            $md_links = $md_links ?? [];
            // no padding!
            $text = preg_replace_callback('/__MD_LINK__PRESERVER__/', function () use (&$md_links) { // Negaresh patch (B1): by reference
                return (string) array_shift($md_links);
            }, $text);

            $uris = $uris ?? [];
            $text = preg_replace_callback('/[ ]?__URI__PRESERVER__[ ]?/', function () use (&$uris) { // Negaresh patch (B1): by reference
                return (string) array_shift($uris);
            }, $text);
        }

        // bringing back braces
        if ($options["preserve_braces"]) {
            $braces = $braces ?? [];
            $text = preg_replace_callback('/[ ]?__BRACES__PRESERVER__[ ]?/', function () use (&$braces) { // Negaresh patch (B1): by reference
                return (string) array_shift($braces);
            }, $text);
        }

        // bringing back brackets
        if ($options["preserve_brackets"]) {
            $brackets = $brackets ?? [];
            $text = preg_replace_callback('/[ ]?__BRACKETS__PRESERVER__[ ]?/', function () use (&$brackets) { // Negaresh patch (B1): by reference
                return (string) array_shift($brackets);
            }, $text);
        }

        // bringing back HTML comments
        if ($options["preserve_comments"]) {
            $comments = $comments ?? [];
            $text = preg_replace_callback('/[ ]?__COMMENT__PRESERVER__[ ]?/', function () use (&$comments) { // Negaresh patch (B1): by reference
                return (string) array_shift($comments);
            }, $text);
        }

        // bringing back HTML tags
        if ($options["preserve_HTML"]) {
            $html = $html ?? [];
            $text = preg_replace_callback('/[ ]?__HTML__PRESERVER__[ ]?/', function () use (&$html) { // Negaresh patch (B1): by reference
                return (string) array_shift($html);
            }, $text);
        }

        // bringing back front matter
        if ($options["preserve_front_matter"]) {
            $front_matter = $front_matter ?? [];
            $text = preg_replace_callback('/[ ]?__FRONT__MATTER__PRESERVER__[ ]?/', function () use (&$front_matter) { // Negaresh patch (B1): by reference
                return (string) array_shift($front_matter);
            }, $text);
        }

        if ($options["cleanup_begin_and_end"]) {
            $text = $this->cleanupBeginAndEnd($text);
        } else {
            // removes single space paddings around the string
            $text = preg_replace('/^[ ]/', '', preg_replace('/[ ]$/', '', $text));
        }

        return $text;
    }

    /*
     * Negaresh patch (I11, B28, B29): the rule functions below follow Virastar.js 0.22.1
     * (https://github.com/brothersincode/virastar lib/virastar.js) step by step, in the JS order.
     * The PHP port nested its preg_replace() calls, so each rule ran its steps in reverse
     * (B28), and several patterns with Persian digits lacked /u and matched bytes (B29).
     * Differences from the JS, all deliberate: every pattern has /u; JS `\w` is spelled out as
     * [A-Za-z0-9_] because PHP's /u makes \w match Persian letters; steps the JS applies only to
     * the first match (no /g) apply to all; B1, B20, B22 and B26 fixes are kept.
     * tests/Unit/VirastarReferenceTest.php runs the JS test suite against this file.
     */

    protected function cleanupZWNJ($text)
    {
        // converts all soft hyphens (&shy;) into zwnj
        $text = preg_replace('/\x{00ad}/u', "\u{200c}", $text);
        // converts all angled dash (&not;) into zwnj
        $text = preg_replace('/\x{00ac}/u', "\u{200c}", $text);
        // removes more than one zwnj
        $text = preg_replace('/\x{200c}{2,}/u', "\u{200c}", $text);
        // cleans zwnj before and after numbers, english words, spaces and punctuations
        $around = '[A-Za-z0-9_\s۰-۹\[\](){}«»“”.…,:;?!$%@#*=+\-\/\\\\،؛٫٬×٪؟ـ]';
        $text = preg_replace('/\x{200c}(' . $around . ')/u', '$1', $text);
        $text = preg_replace('/(' . $around . ')\x{200c}/u', '$1', $text);
        // removes unnecessary zwnj on start/end of each line
        return preg_replace('/(^\x{200c}|\x{200c}$)/mu', '', $text);
    }

    // late checks for zwnjs
    protected function cleanupZWNJLate($text)
    {
        // cleans zwnj after characters that don't conncet to the next
        return preg_replace('/([إأةؤورزژاآدذ،؛,:«»\\\\\/@#$٪×*()ـ\-=|])\x{200c}/u', '$1', $text);
    }

    protected function charReplace($text, $fromBatch, $toBatch)
    {
        $fromChars = mb_str_split($fromBatch);
        $toChars = mb_str_split($toBatch);
        foreach ($fromChars as $key => $value) {
            $text = preg_replace("~" . $value . "~u", $toChars[$key] ?? '', $text);
        }
        return $text;
    }

    protected function arrReplace($text, $array)
    {
        foreach ($array as $key => $item) {
            $text = preg_replace('/[' . $item . ']/u', $key, $text);
        }
        return $text;
    }

    protected function convertPersianNumbers($text)
    {
        return preg_replace_callback('/[\x{0660}-\x{0669}\x{06f0}-\x{06f9}]/u', function ($matched) {
            return ord($matched[0][0] ?? '') & 0xf;
        }, $text);
    }

    protected function normalizeEOL($text)
    {
        // replaces windows end of lines with unix eol (`\n`)
        return preg_replace('/(\r?\n)|(\r\n?)/u', "\n", $text);
    }

    protected function fixDashes($text)
    {
        // replaces triple dash to mdash, then double dash to ndash
        $text = preg_replace('/-{3}/u', '—', $text);
        return preg_replace('/-{2}/u', '–', $text);
    }

    protected function fixThreeDots($text)
    {
        // removes spaces between dots
        $text = preg_replace('/\.([ ]+)(?=[.])/u', '.', $text);
        // replaces three dots with ellipsis character
        return preg_replace('/\.{3,}/u', '…', $text);
    }

    protected function normalizeEllipsis($text)
    {
        // replaces more than one ellipsis with one
        $text = preg_replace('/(…){2,}/u', '…', $text);
        // replaces more than one space before ellipsis with one space
        $text = preg_replace('/[ ]{2,}…/u', ' …', $text);
        // Negaresh patch (B26): no space is kept or added between an ellipsis and a line break
        $text = preg_replace('/…[ \t\x{200c}]+(?=\n)/u', '…', $text);
        // replaces (space|tab|zwnj) after ellipsis with one space
        return preg_replace('/…[ \t\x{200c}]*+(?!\n)/u', '… ', $text);
    }

    protected function removeSpaceBeforeEllipsis($text)
    {
        // removes spaces before ellipsis
        return preg_replace('/[ \t]*…/u', '…', $text);
    }

    protected function fixEnglishQuotesPairs($text)
    {
        // replaces english quote pairs with their persian equivalent
        return preg_replace('/(“)(.+?)(”)/u', '«$2»', $text);
    }

    // replaces english quote marks with their persian equivalent
    protected function fixEnglishQuotes($text)
    {
        return preg_replace('/(["\'`]+)(.+?)(\1)/u', '«$2»', $text);
    }

    protected function fixHamzeh($text)
    {
        $replacement = '$1هٔ$3';
        // converts arabic `TEH MARBUTA GOAL (U+06C3)` into `TEH MARBUTA (U+0629)`
        $text = preg_replace('/ۃ/u', 'ة', $text);
        // replaces ه followed by (space|ZWNJ|lrm) follow by ی with هٔ
        $text = preg_replace('/(\S)(ه[\s\x{200c}\x{200e}]+[یي])([\s\x{200c}\x{200e}])/u', $replacement, $text);
        // replaces ه followed by (space|ZWNJ|lrm|nothing) follow by ء with هٔ
        $text = preg_replace('/(\S)(ه[\s\x{200c}\x{200e}]?\x{0621})([\s\x{200c}\x{200e}])/u', $replacement, $text);
        // replaces هٓ or single-character ۀ with the standard هٔ
        return preg_replace('/(ۀ|هٓ)/u', 'هٔ', $text);
    }

    protected function fixHamzehArabic($text)
    {
        // converts arabic hamzeh ة to هٔ
        return preg_replace('/(\S)ة([\s\x{200c}\x{200e}])/u', '$1هٔ$2', $text);
    }

    protected function fixHamzehArabicAlt($text)
    {
        // converts arabic hamzeh ة to ه‌ی
        return preg_replace('/(\S)ة([\s\x{200c}\x{200e}])/u', "\$1ه\u{200c}ی\$2", $text);
    }

    protected function cleanupRLM($text)
    {
        // converts Right-to-left marks followed by persian characters to zero-width non-joiners (ZWNJ)
        return preg_replace('/([^a-zA-Z\-_])(\x{200f})/u', "\$1\u{200c}", $text);
    }

    // converts incorrect persian glyphs to standard characters
    protected function fixPersianGlyphs($text)
    {
        return $this->arrReplace($text, $this->glyphs);
    }

    protected function fixMiscNonPersianChars($text)
    {
        return $this->charReplace($text, 'كڪيےىۍېہە', 'ککیییییههه');
    }

    // replaces english numbers with their persian equivalent
    protected function fixEnglishNumbers($text)
    {
        return $this->charReplace($text, '1234567890', $this->digits);
    }

    // replaces arabic numbers with their persian equivalent
    protected function fixArabicNumbers($text)
    {
        return $this->charReplace($text, '١٢٣٤٥٦٧٨٩٠', $this->digits);
    }

    protected function fixNumeralSymbols($text)
    {
        // replaces english percent signs (U+066A)
        $text = preg_replace('/([۰-۹]) ?%/u', '$1٪', $text);
        // replaces dots between numbers into decimal separator (U+066B)
        $text = preg_replace('/([۰-۹])\.(?=[۰-۹])/u', '$1٫', $text);
        // replaces commas between numbers into thousands separator (U+066C)
        return preg_replace('/([۰-۹]),(?=[۰-۹])/u', '$1٬', $text);
    }

    protected function normalizeDates($text)
    {
        // re-orders date parts with slash as delimiter: day/month/year → year/month/day
        return preg_replace_callback('#([0-9۰-۹]{1,2})([/-])([0-9۰-۹]{1,2})\2([0-9۰-۹]{4})#u', function ($matched) {
            return $matched[4] . '/' . $matched[3] . '/' . $matched[1];
        }, $text);
    }

    protected function fixPunctuations($text)
    {
        return $this->charReplace($text, ',;', '،؛');
    }

    // replaces question marks with its persian equivalent
    protected function fixQuestionMark($text)
    {
        return preg_replace('/(\?)/u', "\u{061F}", $text); // ؟
    }

    // puts zwnj between the word and the prefix: mi* nemi* bi*
    protected function fixPrefixSpacing($text)
    {
        $replacement = "\$1\u{200c}\$3";
        $text = preg_replace('/((\s|^)ن?می) ([^ ])/u', $replacement, $text);
        return preg_replace('/((\s|^)بی) ([^ ])/u', $replacement, $text);
    }

    // puts zwnj between the word and the suffix
    protected function fixSuffixSpacing($text)
    {
        $replacement = "\$1\u{200c}\$2";
        $before = '([' . $this->charsPersian . $this->charsDiacritic . '])';
        $after = '[' . $this->patternAfter . ']';
        // *ha *haye (must be done before the others)
        $text = preg_replace('#' . $before . ' (ها(ی)?' . $after . ')#u', $replacement, $text);
        // *am *at *ash *ei *eid *eem *and *man *tan *shan
        $text = preg_replace('#' . $before . ' ((ام|ات|اش|ای|اید|ایم|اند|مان|تان|شان)' . $after . ')#u', $replacement, $text);
        // *tar *tari *tarin
        $text = preg_replace('#' . $before . ' (تر((ی)|(ین))?' . $after . ')#u', $replacement, $text);
        // *hayee *hayam *hayat *hayash *hayetan *hayeman *hayeshan
        return preg_replace('#' . $before . ' ((هایی|هایم|هایت|هایش|هایمان|هایتان|هایشان)' . $after . ')#u', $replacement, $text);
    }

    protected function fixSuffixSpacingHamzeh($text)
    {
        $replacement = "\$1\u{0647}\u{200c}\u{06cc}\$3";
        // heh + ye
        $text = preg_replace('/(\S)(ه[\s\x{200c}]+[یي])([\s\x{200c}])/u', $replacement, $text);
        // heh + standalone hamza
        $text = preg_replace('/(\S)(ه[\s\x{200c}]?\x{0621})([\s\x{200c}])/u', $replacement, $text);
        // heh + hamza above
        return preg_replace('/(\S)(ه[\s\x{200c}]?\x{0654})([\s\x{200c}])/u', $replacement, $text);
    }

    protected function fixSuffixMisc($text)
    {
        // replaces ه followed by ئ or ی, and then by ی, with ه‌ای (EXAMPLE: خانه‌ئی becomes خانه‌ای);
        // the trailing check is a lookahead so it also works at the end of the text (B20)
        return preg_replace('/(\S)ه[\x{200c}\x{200e}][ئی]ی(?=[\s\x{200c}\x{200e}]|$)/u', "\$1ه\u{200c}ای", $text);
    }

    protected function cleanupExtraMarks($text)
    {
        // removes space between different/same marks (combining for cleanup)
        $text = preg_replace('/([؟?!])([ ]+)(?=[؟?!])/u', '$1', $text);
        // replaces more than one exclamation mark with just one
        $text = preg_replace('/(!){2,}/u', '$1', $text);
        // replaces more than one english or persian question mark with just one
        $text = preg_replace('/(\x{061F}|\?){2,}/u', '$1', $text);
        // re-orders consecutive marks: `!?` --> `?!`
        return preg_replace('/(!)([ \t]*)([\x{061F}?])/u', '$3$1', $text);
    }

    // replaces kashidas to ndash in parenthetic
    protected function kashidasAsParenthetic($text)
    {
        $text = preg_replace('/(\s)\x{0640}+/u', '$1–', $text);
        return preg_replace('/\x{0640}+(\s)/u', '–$1', $text);
    }

    protected function cleanupKashidas($text)
    {
        // converts kashida between numbers to ndash
        $text = preg_replace('/([0-9۰-۹]+)ـ+([0-9۰-۹]+)/u', '$1–$2', $text);
        // removes all kashidas between non-whitespace characters
        return preg_replace('/([^\s.])\x{0640}+(?![\s.])/u', '$1', $text);
    }

    protected function fixPunctuationSpacing($text)
    {
        // removes space before punctuations
        $text = preg_replace('/[ \t\x{200c}]*([:;,؛،.؟?!]{1})/u', '$1', $text);
        // removes more than one space after punctuations, except followed by new-lines (or
        // preservers); Negaresh patch (B1): possessive `*+` so backtracking cannot skip the check
        $text = preg_replace('/([:;,؛،.؟?!]{1})[ \t\x{200c}]*+(?!\n|_{2})/u', '$1 ', $text);
        // removes space after colon that separates time parts
        $text = preg_replace('/([0-9۰-۹]+):\s+([0-9۰-۹]+)/u', '$1:$2', $text);
        // removes space after dots in numbers
        $text = preg_replace('/([0-9۰-۹]+)\. ([0-9۰-۹]+)/u', '$1.$2', $text);
        // removes space before common domain tlds
        $text = preg_replace('~([A-Za-z0-9_\-]+)\. (ir|com|org|net|info|edu|me)([\s/\\\\\])»:;.])~u', '$1.$2$3', $text);
        // removes space between different/same marks (double-check)
        return preg_replace('/([؟?!])([ ]+)(?=[؟?!])/u', '$1', $text);
    }

    protected function fixBracesSpacing($text)
    {
        // removes inside spaces and more than one outside for `()`, `[]`, `{}`, `“”` and `«»`
        $replacement = ' $1$2$3 ';
        foreach (['\(' => '\)', '\[' => '\]', '\{' => '\}', '“' => '”', '«' => '»'] as $open => $close) {
            $inner = '\\' === $close[0] ? $close[1] : $close;
            $text = preg_replace(
                '/[ \t\x{200c}]*(' . $open . ')\s*([^' . preg_quote($inner, '/') . ']+?)\s*?(' . $close . ')[ \t\x{200c}]*/u',
                $replacement,
                $text
            );
        }
        return $text;
    }

    protected function fixBracesSpacingInside($text)
    {
        // removes inside spaces for `()`, `[]`, `{}`, `“”` and `«»`
        foreach (['\(' => '\)', '\[' => '\]', '\{' => '\}', '“' => '”', '«' => '»'] as $open => $close) {
            $inner = '\\' === $close[0] ? $close[1] : $close;
            $text = preg_replace('/(' . $open . ')\s*([^' . preg_quote($inner, '/') . ']+?)\s*?(' . $close . ')/u', '$1$2$3', $text);
        }
        // removes markdown link spaces inside normal ()
        return preg_replace('/(\(\[.*?\]\(.*?\))\s+(\))/u', '$1$2', $text);
    }

    protected function markdownNormalizeBraces($text)
    {
        // removes space between ! and opening brace on markdown images: `! [alt] (src)` --> `![alt](src)`
        $text = preg_replace('/! (\[.*?\])[ ]?(\(.*?\))[ ]?/u', '!$1$2', $text);
        // removes spaces between [] and (): `[text] (link)` --> `[text](link)`
        $text = preg_replace('/(\[.*?\])[ \t]+(\(.*?\))/u', '$1$2', $text);
        // removes spaces inside double () [] {}: `[[ text ]]` --> `[[text]]`
        $text = preg_replace('/\(\([ \t]*(.*?)[ \t]*\)\)/u', '(($1))', $text);
        $text = preg_replace('/\[\[[ \t]*(.*?)[ \t]*\]\]/u', '[[$1]]', $text);
        $text = preg_replace('/\{\{[ \t]*(.*?)[ \t]*\}\}/u', '{{$1}}', $text);
        $text = preg_replace('/\{\{\{[ \t]*(.*?)[ \t]*\}\}\}/u', '{{{$1}}}', $text); // mustache escape
        // removes spaces between double () [] {}: `[[text] ]` --> `[[text]]`
        $text = preg_replace('/(\(\(.*\))[ \t]+(\))/u', '$1$2', $text);
        $text = preg_replace('/(\[\[.*\])[ \t]+(\])/u', '$1$2', $text);
        return preg_replace('/(\{\{.*\})[ \t]+(\})/u', '$1$2', $text);
    }

    protected function markdownNormalizeLists($text)
    {
        // replaces starting `MIDDLE DOT (U+00B7)` following single space with dash
        $text = preg_replace('/(^ |\n|^)([\x{00b7}]\s)/u', '$1- ', $text);
        // removes extra line between two items list
        $text = preg_replace('/((\n|^)\*.*?)\n+(?=\n\*)/u', '$1', $text);
        $text = preg_replace('/((\n|^)-.*?)\n+(?=\n-)/u', '$1', $text);
        return preg_replace('/((\n|^)#.*?)\n+(?=\n#)/u', '$1', $text);
    }

    protected function fixMiscSpacing($text)
    {
        // removes space before parentheses on misc cases
        $text = preg_replace('/ \((ص|عج|س|ع|ره)\)/u', '($1)', $text);
        // removes space before braces containing numbers
        return preg_replace('/ \[([0-9۰-۹]+)\]/u', '[$1]', $text);
    }

    protected function fixDiacritics($text)
    {
        $diacritics = '[' . $this->charsDiacritic . ']';
        // cleans zwnj before diacritic characters
        $text = preg_replace('/\x{200c}(' . $diacritics . ')/u', '$1', $text);
        // cleans more than one of each diacritic characters (different ones may be stacked)
        $text = preg_replace('/(' . $diacritics . ')\1+/u', '$1', $text);
        // cleans spaces before diacritic characters
        return preg_replace('/(\S)[ ]+(' . $diacritics . ')/u', '$1$2', $text);
    }

    protected function removeDiacritics($text)
    {
        // removes all diacritic characters
        return preg_replace('/[' . $this->charsDiacritic . ']+/u', '', $text);
    }

    protected function cleanupSpacing($text)
    {
        // replaces more than one space with just a single one, except before/after preservers and
        // before new-lines
        $text = preg_replace('/([^_])([ ]{2,})(?![_]{2}|\n)/u', '$1 ', $text);
        // cleans tab/space/zwnj/zwj/nbsp between new-lines
        return preg_replace('/^\n([\t\x{0020}\x{200c}\x{200d}\x{00a0}]*)\n$/mu', "\n\n", $text);
    }

    protected function cleanupLineBreaks($text)
    {
        // cleans whitespace/zwnj between new-lines
        $text = preg_replace('/\n[\s\x{200c}]*\n/u', "\n\n", $text);
        // cleans more than two contiguous line-breaks
        return preg_replace('/\n{2,}/u', "\n\n", $text);
    }

    protected function cleanupBeginAndEnd($text)
    {
        // removes space/tab/zwnj/nbsp from the beginning of the new-lines
        $text = preg_replace('/([\n]+)[ \t\x{200c}\x{00a0}]*/u', '$1', $text);
        // removes spaces, tabs, zwnj, direction marks and new lines from the beginning and end of text
        return preg_replace('/^[\s\x{200c}\x{200e}\x{200f}]+|[\s\x{200c}\x{200e}\x{200f}]+$/u', '', $text);
    }
}