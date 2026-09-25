<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;

/**
 * Harness smoke test: the_content filter runs end to end with WordPress mocked.
 */
class NegareshFilterTest extends TestCase
{
    public function testConstructorRegistersHooks(): void
    {
        $plugin = new Negaresh();

        self::assertNotFalse(has_filter('the_content', [$plugin, 'fix_farsi_typoes']));
        self::assertNotFalse(has_action('admin_menu', [$plugin, 'negaresh_menu']));
    }

    public function testFilterKeepsLinksWithAllRulesOff(): void
    {
        Functions\when('get_option')->justReturn(false);

        $out = (new Negaresh())->fix_farsi_typoes('<p>به <a href="https://example.com">این صفحه</a> بروید.</p>');

        self::assertStringContainsString('<a href="https://example.com">این صفحه</a>', $out);
    }
}
