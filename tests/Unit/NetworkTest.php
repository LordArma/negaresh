<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh_Settings;

/**
 * I10c: multisite network defaults.
 */
class NetworkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('sanitize_key')->alias(function ($key) {
            return strtolower((string) $key);
        });
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('get_post_types')->justReturn([]);
    }

    public function testSitesWithoutOwnSettingsUseTheNetworkDefaults(): void
    {
        $this->network_options[Negaresh_Settings::NETWORK_OPTION] = ['mode' => 'display', 'fix_english_numbers' => true];
        $got = (new Negaresh_Settings())->get();

        self::assertSame('display', $got['mode']);
        self::assertTrue($got['fix_english_numbers']);
        self::assertTrue($got['fix_three_dots'], 'keys the network did not set keep the code default');
    }

    public function testASitesOwnSettingsWin(): void
    {
        $this->network_options[Negaresh_Settings::NETWORK_OPTION] = ['mode' => 'display', 'fix_english_numbers' => true];
        $this->options[Negaresh_Settings::OPTION] = ['mode' => 'save'];
        $got = (new Negaresh_Settings())->get();

        self::assertSame('save', $got['mode']);
        self::assertTrue($got['fix_english_numbers'], 'not saved by the site: network value');
    }

    public function testNetworkOptionIgnoredOnASingleSite(): void
    {
        Functions\when('is_multisite')->justReturn(false);
        $this->network_options[Negaresh_Settings::NETWORK_OPTION] = ['mode' => 'display'];

        self::assertSame('save', (new Negaresh_Settings())->get()['mode']);
    }

    public function testSavingTheNetworkPageStoresSanitizedDefaults(): void
    {
        Functions\when('current_user_can')->alias(function ($cap) {
            return 'manage_network_options' === $cap;
        });
        (new Negaresh_Settings())->save_network([
            'mode' => 'display',
            'fix_dashes' => '1',
            'protected_words' => "نام\nنام",
            'bogus' => 'x',
        ]);

        $stored = $this->network_options[Negaresh_Settings::NETWORK_OPTION];
        self::assertSame('display', $stored['mode']);
        self::assertTrue($stored['fix_dashes']);
        self::assertFalse($stored['fix_three_dots'], 'unchecked on the form');
        self::assertSame(['نام'], $stored['protected_words']);
        self::assertArrayNotHasKey('bogus', $stored);
    }

    public function testOnlyNetworkAdminsMaySave(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('denied'));

        $this->expectException(\RuntimeException::class);
        (new Negaresh_Settings())->save_network(['mode' => 'display']);
    }

    public function testUninstallRemovesTheNetworkDefaults(): void
    {
        Functions\when('delete_post_meta_by_key')->justReturn(true);
        $this->network_options[Negaresh_Settings::NETWORK_OPTION] = ['mode' => 'display'];

        Negaresh_Settings::delete_network();

        self::assertArrayNotHasKey(Negaresh_Settings::NETWORK_OPTION, $this->network_options);
    }
}
