<?php

/**
 * Tools → Negaresh (I6): existing posts in a list like the posts list (B33), with what would change
 * and fixing of the selected posts, or of every waiting post in batches.
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Bulk_Page
{
    public const PAGE = 'negaresh-bulk';

    /** Posts per request when checking or fixing. */
    public const BATCH = 10;

    /** Screen option (user meta) with the posts per page of the list, and its default (B33). */
    public const PER_PAGE_OPTION = 'negaresh_bulk_per_page';
    public const PER_PAGE = 50;

    /** Bulk action and row action that fix posts; nonce action of the row action is FIX_ONE . ID. */
    public const BULK_ACTION = 'negaresh_fix';
    public const FIX_ONE = 'negaresh_fix_one';

    /** @var Negaresh_Bulk */
    private $bulk;

    /** @var Negaresh_Bulk_Table|null */
    private $table;

    public function __construct(Negaresh_Bulk $bulk)
    {
        $this->bulk = $bulk;

        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_filter('set_screen_option_' . self::PER_PAGE_OPTION, [$this, 'save_per_page'], 10, 3);
    }

    /**
     * Address of the page with the given query arguments.
     *
     * @param array<string, string|int> $args
     */
    public static function url(array $args = []): string
    {
        return add_query_arg($args, admin_url('tools.php?page=' . self::PAGE));
    }

    /**
     * "Fix now" row action: works without JavaScript, nonce per post.
     */
    public static function fix_one_url(int $post_id): string
    {
        return wp_nonce_url(self::url(['action' => self::FIX_ONE, 'post' => $post_id]), self::FIX_ONE . $post_id);
    }

    public function add_page(): void
    {
        $hook = add_management_page(
            esc_html__('Negaresh: fix existing posts', 'negaresh'),
            esc_html__('Negaresh', 'negaresh'),
            'manage_options',
            self::PAGE,
            [$this, 'render_page']
        );
        if ($hook) {
            add_action('load-' . $hook, [$this, 'load_page']);
        }
    }

    /**
     * Keeps the "Number of items per page" screen option, between 1 and 999.
     *
     * @param mixed $keep
     * @param string $option
     * @param mixed $value
     * @return mixed
     */
    public function save_per_page($keep, $option, $value)
    {
        return self::PER_PAGE_OPTION === $option ? max(1, min(999, (int) $value)) : $keep;
    }

    /**
     * Before the page is sent: actions (as edit.php does: do, then redirect), screen option, list.
     */
    public function load_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $this->handle_actions();

        add_screen_option('per_page', [
            'label' => __('Number of items per page:', 'negaresh'),
            'default' => self::PER_PAGE,
            'option' => self::PER_PAGE_OPTION,
        ]);
        require_once __DIR__ . '/negaresh-bulk-table.php';
        $this->table = new Negaresh_Bulk_Table($this->bulk);
    }

    /**
     * The bulk action "Fix" and the row action "Fix now" without JavaScript (bulk.js does the same
     * in batches over REST). Redirects back to the list with the counts.
     */
    public function handle_actions(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked below, per action
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if ('' === $action || '-1' === $action) {
            $action = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '';
        }
        if (self::BULK_ACTION === $action) {
            check_admin_referer('bulk-negaresh-posts');
            $ids = isset($_REQUEST['post']) && is_array($_REQUEST['post']) ? array_map('intval', wp_unslash($_REQUEST['post'])) : [];
        } elseif (self::FIX_ONE === $action) {
            $id = isset($_REQUEST['post']) ? (int) $_REQUEST['post'] : 0;
            check_admin_referer(self::FIX_ONE . $id);
            $ids = [$id];
        } else {
            return;
        }
        // phpcs:enable

        $counts = $this->fix(array_values(array_filter($ids)));
        $drop = ['action', 'action2', 'post', '_wpnonce', '_wp_http_referer', 'negaresh_fixed', 'negaresh_checked', 'negaresh_failed'];
        $back = remove_query_arg($drop, wp_get_referer() ?: self::url());
        wp_safe_redirect(add_query_arg($counts, $back));
        exit;
    }

    /**
     * Fixes the given posts the user may edit.
     *
     * @param list<int> $ids
     * @return array{negaresh_fixed: int, negaresh_checked: int, negaresh_failed: int}
     */
    public function fix(array $ids): array
    {
        $counts = ['negaresh_fixed' => 0, 'negaresh_checked' => 0, 'negaresh_failed' => 0];
        foreach ($ids as $id) {
            if (!current_user_can('edit_post', $id)) {
                $counts['negaresh_failed']++;
                continue;
            }
            $result = $this->bulk->process($id, true);
            if (null !== $result['skipped']) {
                $counts['negaresh_failed']++;
            } elseif ($result['changed']) {
                $counts['negaresh_fixed']++;
            } else {
                $counts['negaresh_checked']++;
            }
        }
        return $counts;
    }

    public function register_rest_routes(): void
    {
        $admin = static function (): bool {
            return current_user_can('manage_options');
        };

        register_rest_route('negaresh/v1', '/bulk/find', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_find'],
            'permission_callback' => $admin,
            'args' => [
                'post_type' => [
                    'required' => false,
                    'validate_callback' => static function ($value): bool {
                        return null === $value || is_array($value);
                    },
                ],
            ],
        ]);

        register_rest_route('negaresh/v1', '/bulk/process', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_process'],
            'permission_callback' => $admin,
            'args' => [
                'ids' => [
                    'required' => true,
                    'validate_callback' => static function ($value): bool {
                        return is_array($value) && [] !== $value && count($value) <= self::BATCH;
                    },
                ],
            ],
        ]);
    }

    /**
     * POST negaresh/v1/bulk/find {post_type[], all} → {ids}.
     *
     * @return array{ids: list<int>}
     */
    public function rest_find(\WP_REST_Request $request): array
    {
        $types = $request->get_param('post_type');
        $types = is_array($types) ? array_values(array_map('strval', array_filter($types, 'is_scalar'))) : [];

        return ['ids' => $this->bulk->find(['post_type' => $types, 'all' => (bool) $request->get_param('all')])];
    }

    /**
     * POST negaresh/v1/bulk/process {ids[], apply} → {results}. Each post is also checked against
     * the user's right to edit it. Only changed lines are sent back, not whole posts.
     *
     * @return array{results: list<array<string, mixed>>}
     */
    public function rest_process(\WP_REST_Request $request): array
    {
        $ids = $request->get_param('ids');
        $apply = (bool) $request->get_param('apply');
        $results = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            $id = (int) $id;
            if (!current_user_can('edit_post', $id)) {
                $results[] = ['id' => $id, 'title' => '', 'changed' => false, 'skipped' => 'not_allowed', 'diff' => [], 'edit_link' => ''];
                continue;
            }

            $result = $this->bulk->process($id, $apply);
            $diff = [];
            // Only a check shows the changes; fixing does not need the (costly) diff.
            foreach ($apply ? [] : $result['fields'] as $field => $change) {
                $diff[] = ['field' => $field, 'lines' => Negaresh_Bulk::diff($change['before'], $change['after'])];
            }
            $results[] = [
                'id' => $id,
                'title' => $result['title'],
                'changed' => $result['changed'],
                'skipped' => $result['skipped'],
                'diff' => $diff,
                'edit_link' => (string) get_edit_post_link($id, 'raw'),
            ];
        }

        return ['results' => $results];
    }

    /**
     * @param mixed $hook_suffix
     */
    public function enqueue_assets($hook_suffix): void
    {
        if ('tools_page_' . self::PAGE !== $hook_suffix) {
            return;
        }
        $base = plugins_url('assets/', NEGARESH_FILE);
        wp_enqueue_style('negaresh-admin', $base . 'admin.css', [], NEGARESH_VERSION);
        wp_enqueue_script('negaresh-bulk', $base . 'bulk.js', ['wp-api-fetch'], NEGARESH_VERSION, true);
        wp_localize_script('negaresh-bulk', 'negareshBulk', [
            'batch' => self::BATCH,
            'action' => self::BULK_ACTION,
            'checking' => __('Checking…', 'negaresh'),
            'noChange' => __('Already correct: fixing only marks it as checked.', 'negaresh'),
            'upToDate' => __('Nothing to change.', 'negaresh'),
            /* translators: %d: changed lines. */
            'changes' => __('Show changes (%d lines)', 'negaresh'),
            /* translators: %s: error message. */
            'checkFailed' => __('Could not be checked: %s', 'negaresh'),
            'notAllowed' => __('You may not edit this post.', 'negaresh'),
            /* translators: 1: posts done so far, 2: posts to fix. */
            'fixing' => __('Fixing posts: %1$d of %2$d', 'negaresh'),
            /* translators: %d: posts to fix. */
            'confirmSelected' => __('Fix the selected posts (%d)? This changes the stored posts; each one can be restored from its revisions.', 'negaresh'),
            /* translators: %d: posts to fix. */
            'confirmAll' => __('Fix every waiting post (%d)? This changes the stored posts; each one can be restored from its revisions.', 'negaresh'),
            'noneSelected' => __('Select at least one post.', 'negaresh'),
            'nothingWaiting' => __('No posts are waiting.', 'negaresh'),
            'failed' => __('Something went wrong; the list shows what was done.', 'negaresh'),
        ]);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!$this->table) {
            require_once __DIR__ . '/negaresh-bulk-table.php';
            $this->table = new Negaresh_Bulk_Table($this->bulk);
        }
        $table = $this->table;
        $table->prepare_items();
        $stats = $table->stats();
        $type = $table->current_type();
        ?>
        <div class="wrap negaresh-bulk">
            <h1 class="wp-heading-inline"><?php esc_html_e('Negaresh: fix existing posts', 'negaresh'); ?></h1>
            <?php if ($stats['waiting'] > 0) : ?>
                <button type="button" class="page-title-action negaresh-fix-all"
                    data-type="<?php echo esc_attr($type); ?>" data-count="<?php echo (int) $stats['waiting']; ?>">
                    <?php
                    /* translators: %s: number of posts waiting. */
                    echo esc_html(sprintf(__('Fix all waiting posts (%s)', 'negaresh'), number_format_i18n($stats['waiting'])));
                    ?>
                </button>
            <?php endif; ?>
            <hr class="wp-header-end" />
            <?php $this->render_notice(); ?>
            <p>
                <?php esc_html_e('Posts are checked with the saved rules.', 'negaresh'); ?>
                <?php esc_html_e('The Changes column shows what fixing would change; nothing is saved until you fix.', 'negaresh'); ?>
                <?php esc_html_e('Posts marked “Leave this post alone” are never touched.', 'negaresh'); ?>
            </p>
            <div class="negaresh-bulk-status" role="status" aria-live="polite"></div>
            <?php $table->views(); ?>
            <form id="negaresh-posts-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>" />
                <input type="hidden" name="view" value="<?php echo esc_attr($table->current_view()); ?>" />
                <?php $table->search_box(__('Search posts', 'negaresh'), 'negaresh-search'); ?>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Result of the last fix, from the redirect after it.
     */
    private function render_notice(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- only counts to show
        if (!isset($_GET['negaresh_fixed'])) {
            return;
        }
        $fixed = absint($_GET['negaresh_fixed']);
        $checked = isset($_GET['negaresh_checked']) ? absint($_GET['negaresh_checked']) : 0;
        $failed = isset($_GET['negaresh_failed']) ? absint($_GET['negaresh_failed']) : 0;
        // phpcs:enable
        $parts = [
            /* translators: %s: number of posts. */
            sprintf(__('Posts fixed: %s.', 'negaresh'), number_format_i18n($fixed)),
            /* translators: %s: number of posts. */
            sprintf(__('Posts already correct, now marked as checked: %s.', 'negaresh'), number_format_i18n($checked)),
        ];
        if ($fixed > 0) {
            $parts[] = __('The text before each change is kept in the post\'s revisions.', 'negaresh');
        }
        if ($failed > 0) {
            /* translators: %s: number of posts. */
            $parts[] = sprintf(__('Posts that could not be fixed: %s.', 'negaresh'), number_format_i18n($failed));
        }
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            $failed > 0 ? 'warning' : 'success',
            esc_html(implode(' ', $parts))
        );
    }
}
