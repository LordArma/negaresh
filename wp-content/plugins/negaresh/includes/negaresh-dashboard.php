<?php

/**
 * Dashboard widget and "posts are waiting" notice (I10a).
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Dashboard
{
    /** Transient holding the counts; cleared when a post is saved or the settings change. */
    public const STATS_TRANSIENT = 'negaresh_stats';

    /** User meta: '1' once the user dismissed the notice. */
    public const DISMISSED_META = 'negaresh_notice_dismissed';

    /** admin-post action and nonce action of the dismiss link. */
    public const DISMISS_ACTION = 'negaresh_dismiss_notice';

    /** Screens the notice may appear on. */
    private const NOTICE_SCREENS = ['dashboard', 'plugins'];

    /** @var Negaresh_Bulk */
    private $bulk;

    /** @var Negaresh_Settings */
    private $settings;

    public function __construct(Negaresh_Bulk $bulk, Negaresh_Settings $settings)
    {
        $this->bulk = $bulk;
        $this->settings = $settings;

        add_action('wp_dashboard_setup', [$this, 'add_widget']);
        add_action('admin_notices', [$this, 'render_notice']);
        add_action('admin_post_' . self::DISMISS_ACTION, [$this, 'handle_dismiss']);
        add_action('save_post', [$this, 'forget_stats']);
        add_action('update_option_' . Negaresh_Settings::OPTION, [$this, 'forget_stats']);
        add_action('add_option_' . Negaresh_Settings::OPTION, [$this, 'forget_stats']);
    }

    /**
     * Counts from Negaresh_Bulk::stats(), cached for an hour (a query over every post in scope).
     *
     * @return array{total: int, fixed: int, waiting: int, opted_out: int}
     */
    public function stats(): array
    {
        $cached = get_transient(self::STATS_TRANSIENT);
        if (is_array($cached) && isset($cached['total'], $cached['fixed'], $cached['waiting'], $cached['opted_out'])) {
            return [
                'total' => (int) $cached['total'],
                'fixed' => (int) $cached['fixed'],
                'waiting' => (int) $cached['waiting'],
                'opted_out' => (int) $cached['opted_out'],
            ];
        }
        $stats = $this->bulk->stats();
        set_transient(self::STATS_TRANSIENT, $stats, 3600);
        return $stats;
    }

    public function forget_stats(): void
    {
        delete_transient(self::STATS_TRANSIENT);
    }

    public function add_widget(): void
    {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget('negaresh', esc_html__('Negaresh', 'negaresh'), [$this, 'render_widget']);
        }
    }

    public function render_widget(): void
    {
        $stats = $this->stats();
        $mode = 'save' === $this->settings->mode()
            ? __('Fixing text when a post is saved.', 'negaresh')
            : __('Fixing text when a post is displayed.', 'negaresh');
        $rows = [
            __('Fixed with the current rules', 'negaresh') => $stats['fixed'],
            __('Waiting to be checked', 'negaresh') => $stats['waiting'],
            __('Left alone (opted out)', 'negaresh') => $stats['opted_out'],
        ];

        echo '<p>' . esc_html($mode) . '</p><table class="widefat striped"><tbody>';
        foreach ($rows as $label => $count) {
            printf('<tr><td>%s</td><td><strong>%d</strong></td></tr>', esc_html($label), (int) $count);
        }
        echo '</tbody></table><p>';
        if ($stats['waiting'] > 0) {
            printf(
                '<a class="button button-primary" href="%s">%s</a> ',
                esc_url(admin_url('tools.php?page=negaresh-bulk')),
                esc_html__('Fix existing posts', 'negaresh')
            );
        }
        printf(
            '<a class="button" href="%s">%s</a></p>',
            esc_url(admin_url('options-general.php?page=' . Negaresh_Settings::PAGE)),
            esc_html__('Settings', 'negaresh')
        );
    }

    public function render_notice(): void
    {
        if (!current_user_can('manage_options') || '1' === (string) get_user_meta(get_current_user_id(), self::DISMISSED_META, true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = is_object($screen) && property_exists($screen, 'id') ? $screen->id : '';
        if (!in_array($screen_id, self::NOTICE_SCREENS, true)) {
            return;
        }
        $waiting = $this->stats()['waiting'];
        if ($waiting <= 0) {
            return;
        }

        printf(
            '<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a></p></div>',
            esc_html(sprintf(
                /* translators: %d: posts not yet checked with the current rules. */
                __('Negaresh: posts not yet checked with the current rules: %d. You can see what would change and fix them in Tools → Negaresh.', 'negaresh'),
                $waiting
            )),
            esc_url(admin_url('tools.php?page=negaresh-bulk')),
            esc_html__('Fix existing posts', 'negaresh'),
            esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . self::DISMISS_ACTION), self::DISMISS_ACTION)),
            esc_html__('Dismiss', 'negaresh')
        );
    }

    /**
     * Remembers that the current user dismissed the notice.
     */
    public function dismiss(): void
    {
        update_user_meta(get_current_user_id(), self::DISMISSED_META, '1');
    }

    /**
     * admin-post.php?action=negaresh_dismiss_notice: check the nonce (WordPress answers 403 when it
     * is wrong), dismiss, then back to where the user was.
     */
    public function handle_dismiss(): void
    {
        check_admin_referer(self::DISMISS_ACTION);
        $this->dismiss();
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
