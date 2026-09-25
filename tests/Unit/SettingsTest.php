<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh\Vendor\Virastar\Virastar;
use Negaresh_Settings;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('sanitize_key')->alias(function ($key) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
        });
        Functions\when('get_post_types')->justReturn([
            'post' => (object) ['labels' => (object) ['singular_name' => 'Post']],
            'page' => (object) ['labels' => (object) ['singular_name' => 'Page']],
            'attachment' => (object) ['labels' => (object) ['singular_name' => 'Media']],
        ]);
        Functions\when('checked')->alias(function ($a, $b = true, $echo = true) {
            return (string) $a === (string) $b ? " checked='checked'" : '';
        });
    }

    /** The 30 rows a site that saved the 4.0 settings page has. */
    private function legacyRows(): array
    {
        $rows = array_fill_keys(Negaresh_Settings::LEGACY_OPTIONS, '');
        $rows['fix_dashes'] = '1';
        $rows['fix_english_numbers'] = '1';
        $rows['decode_html_entities'] = '1';
        return $rows;
    }

    public function testB5GetReturnsDefaultsWhenNothingIsSaved(): void
    {
        self::assertSame(Negaresh_Settings::defaults(), (new Negaresh_Settings())->get());
    }

    public function testGetMergesSavedValuesAndDropsUnknownKeys(): void
    {
        $this->options['negaresh_options'] = ['fix_dashes' => false, 'bogus' => 1];
        $got = (new Negaresh_Settings())->get();

        self::assertFalse($got['fix_dashes']);
        self::assertArrayNotHasKey('bogus', $got);
        self::assertTrue($got['fix_three_dots']);
    }

    public function testGetSurvivesCorruptOption(): void
    {
        $this->options['negaresh_options'] = 'not an array';

        self::assertSame(Negaresh_Settings::defaults(), (new Negaresh_Settings())->get());
    }

    public function testSanitizeTurnsMissingBoxesOffAndFiltersPostTypes(): void
    {
        $clean = (new Negaresh_Settings())->sanitize([
            'fix_dashes' => '1',
            'post_types' => ['page', 'attachment', 'nope', '<b>'],
            'apply_in_rest' => '1',
            'injected' => 'x',
        ]);

        self::assertTrue($clean['fix_dashes']);
        self::assertFalse($clean['fix_three_dots']);
        self::assertSame(['page'], $clean['post_types']);
        self::assertFalse($clean['apply_in_feeds']);
        self::assertTrue($clean['apply_in_rest']);
        self::assertArrayNotHasKey('injected', $clean);
        self::assertSame(array_keys(Negaresh_Settings::defaults()), array_keys($clean));
    }

    public function testSanitizeIsIdempotent(): void
    {
        $settings = new Negaresh_Settings();
        $once = $settings->sanitize(['fix_dashes' => '1', 'post_types' => ['post']]);

        self::assertSame($once, $settings->sanitize($once));
        self::assertSame($settings->sanitize(null), $settings->sanitize('garbage'));
    }

    public function testB9MigratesLegacyRowsIntoOneOption(): void
    {
        $this->options = $this->legacyRows();

        (new Negaresh_Settings())->maybe_migrate();

        $migrated = $this->options['negaresh_options'];
        self::assertTrue($migrated['fix_dashes']);
        self::assertTrue($migrated['fix_english_numbers']);
        self::assertFalse($migrated['fix_three_dots']); // saved unchecked in 4.0
        self::assertTrue($migrated['cleanup_kashidas']); // new visible rule keeps its old behaviour
        self::assertArrayNotHasKey('decode_html_entities', $migrated); // B2
        foreach (Negaresh_Settings::LEGACY_OPTIONS as $name) {
            self::assertArrayNotHasKey($name, $this->options, "$name left behind");
        }
        self::assertSame(2, $this->options['negaresh_db_version']);
    }

    public function testB9FreshInstallOnlyRecordsVersion(): void
    {
        (new Negaresh_Settings())->maybe_migrate();

        self::assertSame(['negaresh_db_version' => 2], $this->options);
    }

    public function testB9MigrationRunsOnce(): void
    {
        $this->options = ['negaresh_db_version' => 2, 'fix_dashes' => '1'];

        (new Negaresh_Settings())->maybe_migrate();

        self::assertSame('1', $this->options['fix_dashes']);
        self::assertArrayNotHasKey('negaresh_options', $this->options);
    }

    public function testB9MigrationDoesNotOverwriteNewOption(): void
    {
        $this->options = $this->legacyRows() + ['negaresh_options' => ['fix_dashes' => false]];

        (new Negaresh_Settings())->maybe_migrate();

        self::assertSame(['fix_dashes' => false], $this->options['negaresh_options']);
    }

    public function testB19DeleteAllRemovesEverything(): void
    {
        $this->options = $this->legacyRows() + ['negaresh_options' => [], 'negaresh_db_version' => 2, 'blogname' => 'x'];

        Negaresh_Settings::delete_all();

        self::assertSame(['blogname' => 'x'], $this->options);
    }

    public function testB14EveryRuleHasARealLabelInAKnownSection(): void
    {
        $settings = new Negaresh_Settings();
        $labels = $settings->rule_labels();

        self::assertSame(array_keys(Negaresh_Settings::RULE_DEFAULTS), array_keys($labels));
        foreach ($labels as $key => $field) {
            self::assertArrayHasKey($field['section'], $settings->sections(), $key);
            self::assertNotSame($key, $field['label']);
            self::assertDoesNotMatchRegularExpression('/_/', $field['label'], "$key label looks like a key");
        }
    }

    public function testRulesAreRealVirastarOptions(): void
    {
        $virastar = (new Virastar())->getOptions();

        foreach (array_keys(Negaresh_Settings::RULE_DEFAULTS) as $key) {
            self::assertArrayHasKey($key, $virastar);
        }
    }

    public function testB13CheckboxUsesPrefixedEscapedName(): void
    {
        ob_start();
        (new Negaresh_Settings())->render_checkbox(['key' => 'fix_dashes', 'example' => '-- → –']);
        $html = ob_get_clean();

        self::assertStringContainsString('name="negaresh_options[fix_dashes]"', $html);
        self::assertStringContainsString('id="negaresh_fix_dashes"', $html);
        self::assertStringContainsString("checked='checked'", $html);
    }

    public function testB8AndB13NoGlobalLeftovers(): void
    {
        self::assertFalse(function_exists('settings'));
        self::assertFalse(function_exists('checkboxHTML'));
        self::assertFalse(function_exists('yts_add_scripts'));

        $source = '';
        foreach (glob(NEGARESH_PLUGIN_DIR . '/{,includes/}*.php', GLOB_BRACE) as $file) {
            $source .= file_get_contents($file);
        }
        self::assertStringNotContainsString('wordcountplugin', $source);
        self::assertStringNotContainsString('wcp_first_section', $source);
        self::assertDoesNotMatchRegularExpression('/\b(include|require)(_once)?\s*\(\s*[\'"][^\/]/', $source, 'relative include (B12)');
        self::assertDoesNotMatchRegularExpression('/__\(\s*\$/', $source, 'variable passed to __() (B14)');
    }
}
