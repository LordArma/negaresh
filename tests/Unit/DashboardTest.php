<?php

namespace Negaresh\Tests\Unit;

use Brain\Monkey\Functions;
use Negaresh;
use Negaresh_Bulk;
use Negaresh_Dashboard;
use Negaresh_Settings;

/**
 * I10a: dashboard widget and the "posts are waiting" notice.
 */
class DashboardTest extends TestCase
{
    /** @var array<string, mixed> */
    private $transients = [];

    /** @var array<int, array<string, mixed>> */
    private $user_meta = [];

    /** @var array{total: int, fixed: int, waiting: int, opted_out: int} */
    private $stats = ['total' => 10, 'fixed' => 7, 'waiting' => 3, 'opted_out' => 1];

    /** @var int */
    private $stats_calls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubOptions();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        $this->transients = $this->user_meta = [];
        $this->stats_calls = 0;
        Functions\when('get_transient')->alias(function ($key) {
            return $this->transients[$key] ?? false;
        });
        Functions\when('set_transient')->alias(function ($key, $value, $ttl) {
            $this->transients[$key] = $value;
            return true;
        });
        Functions\when('delete_transient')->alias(function ($key) {
            unset($this->transients[$key]);
            return true;
        });
        Functions\when('get_current_user_id')->justReturn(5);
        Functions\when('get_user_meta')->alias(function ($id, $key, $single = false) {
            return $this->user_meta[$id][$key] ?? '';
        });
        Functions\when('update_user_meta')->alias(function ($id, $key, $value) {
            $this->user_meta[$id][$key] = $value;
            return true;
        });
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('admin_url')->alias(function ($path) {
            return 'https://example.com/wp-admin/' . $path;
        });
        Functions\when('wp_nonce_url')->alias(function ($url, $action) {
            return $url . '&_wpnonce=nonce-' . $action;
        });
    }

    private function dashboard(): Negaresh_Dashboard
    {
        $settings = new Negaresh_Settings();
        $test = $this;
        $bulk = new class (new Negaresh($settings), $settings, $test) extends Negaresh_Bulk {
            /** @var DashboardTest */
            private $test;

            public function __construct(Negaresh $plugin, Negaresh_Settings $settings, DashboardTest $test)
            {
                parent::__construct($plugin, $settings);
                $this->test = $test;
            }

            public function stats(array $post_type = []): array
            {
                return $this->test->fakeStats();
            }
        };
        return new Negaresh_Dashboard($bulk, $settings);
    }

    /**
     * @return array{total: int, fixed: int, waiting: int, opted_out: int}
     */
    public function fakeStats(): array
    {
        $this->stats_calls++;
        return $this->stats;
    }

    public function testCountsAreCachedAndClearedWhenPostsOrSettingsChange(): void
    {
        $dashboard = $this->dashboard();

        self::assertSame(3, $dashboard->stats()['waiting']);
        self::assertSame(7, $dashboard->stats()['fixed']);
        self::assertSame(1, $this->stats_calls, 'second call comes from the transient');

        self::assertNotFalse(has_action('save_post', [$dashboard, 'forget_stats']));
        self::assertNotFalse(has_action('update_option_negaresh_options', [$dashboard, 'forget_stats']));
        $dashboard->forget_stats();
        $dashboard->stats();
        self::assertSame(2, $this->stats_calls);
    }

    public function testWidgetShowsCountsAndTheToolsLink(): void
    {
        ob_start();
        $this->dashboard()->render_widget();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<strong>7</strong>', $html);
        self::assertStringContainsString('<strong>3</strong>', $html);
        self::assertStringContainsString('<strong>1</strong>', $html);
        self::assertStringContainsString('tools.php?page=negaresh-bulk', $html);
    }

    public function testWidgetOnlyForAdmins(): void
    {
        $added = [];
        Functions\when('wp_add_dashboard_widget')->alias(function ($id) use (&$added) {
            $added[] = $id;
        });
        Functions\when('current_user_can')->justReturn(false);
        $this->dashboard()->add_widget();
        self::assertSame([], $added);

        Functions\when('current_user_can')->justReturn(true);
        $this->dashboard()->add_widget();
        self::assertSame(['negaresh'], $added);
    }

    public function testNoticeWhenPostsAreWaitingOnDashboardAndPlugins(): void
    {
        foreach (['dashboard' => true, 'plugins' => true, 'edit-post' => false] as $screen => $shown) {
            Functions\when('get_current_screen')->justReturn((object) ['id' => $screen]);
            ob_start();
            $this->dashboard()->render_notice();
            $html = (string) ob_get_clean();
            self::assertSame($shown, false !== strpos($html, 'notice'), $screen);
        }
    }

    public function testNoNoticeWhenNothingIsWaitingOrAfterDismissing(): void
    {
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'dashboard']);
        $this->stats['waiting'] = 0;
        ob_start();
        $this->dashboard()->render_notice();
        self::assertStringNotContainsString('notice', (string) ob_get_clean());

        $this->stats['waiting'] = 3;
        $this->transients = [];
        $this->user_meta[5][Negaresh_Dashboard::DISMISSED_META] = '1';
        ob_start();
        $this->dashboard()->render_notice();
        self::assertStringNotContainsString('notice', (string) ob_get_clean());
    }

    public function testDismissIsRememberedPerUser(): void
    {
        $this->dashboard()->dismiss();

        self::assertSame('1', $this->user_meta[5][Negaresh_Dashboard::DISMISSED_META]);
        // The nonce is checked by check_admin_referer() in handle_dismiss(); see the e2e test.
    }
}
