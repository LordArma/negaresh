<?php

/**
 * Tools → Negaresh (I6): scan existing posts, see what would change, fix them in batches.
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Bulk_Page
{
    public const PAGE = 'negaresh-bulk';

    /** Posts per request when scanning or fixing. */
    public const BATCH = 10;

    /** @var Negaresh_Bulk */
    private $bulk;

    public function __construct(Negaresh_Bulk $bulk)
    {
        $this->bulk = $bulk;

        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function add_page(): void
    {
        add_management_page(
            esc_html__('Negaresh: fix existing posts', 'negaresh'),
            esc_html__('Negaresh', 'negaresh'),
            'manage_options',
            self::PAGE,
            [$this, 'render_page']
        );
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
            foreach ($result['fields'] as $field => $change) {
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
            /* translators: 1: posts checked so far, 2: posts to check. */
            'scanning' => __('Checking posts: %1$d of %2$d', 'negaresh'),
            /* translators: 1: posts fixed so far, 2: posts to fix. */
            'fixing' => __('Fixing posts: %1$d of %2$d', 'negaresh'),
            /* translators: 1: posts that would change, 2: posts checked. */
            'found' => __('Posts that would change: %1$d of %2$d. Nothing has been saved yet.', 'negaresh'),
            'none' => __('Nothing to fix: every post checked is already correct.', 'negaresh'),
            /* translators: %d: posts fixed. */
            'fixed' => __('Done. Posts fixed: %d. The text before each change is kept in the post\'s revisions.', 'negaresh'),
            /* translators: %d: posts to fix. */
            'confirm' => __('Fix the listed posts now (%d)? This changes the stored posts; each one can be restored from its revisions.', 'negaresh'),
            'failed' => __('Something went wrong; the list shows what was done.', 'negaresh'),
            'notAllowed' => __('You may not edit this post.', 'negaresh'),
            'edit' => __('Edit', 'negaresh'),
            'changes' => __('Changes', 'negaresh'),
        ]);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $types = $this->bulk->types();
        ?>
        <div class="wrap negaresh-bulk">
            <h1><?php esc_html_e('Negaresh: fix existing posts', 'negaresh'); ?></h1>
            <p>
                <?php esc_html_e('Check the posts you already have with the saved rules, see what would change, then fix them.', 'negaresh'); ?>
                <?php esc_html_e('Posts marked “Leave this post alone” are never touched.', 'negaresh'); ?>
            </p>
            <form class="negaresh-bulk-form" onsubmit="return false;">
                <fieldset>
                    <legend><?php esc_html_e('Post types', 'negaresh'); ?></legend>
                    <?php foreach ($types as $type) : ?>
                        <?php $object = get_post_type_object($type); ?>
                        <label>
                            <input type="checkbox" name="post_type" value="<?php echo esc_attr($type); ?>" checked />
                            <?php echo esc_html($object ? $object->labels->name : $type); ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
                <p>
                    <label>
                        <input type="checkbox" name="all" value="1" />
                        <?php esc_html_e('Also check posts already fixed with the current rules', 'negaresh'); ?>
                    </label>
                </p>
                <p>
                    <button type="button" class="button button-primary negaresh-scan">
                        <?php esc_html_e('Scan (nothing is saved)', 'negaresh'); ?>
                    </button>
                    <button type="button" class="button negaresh-apply" disabled><?php esc_html_e('Fix all listed posts', 'negaresh'); ?></button>
                </p>
            </form>
            <p class="negaresh-bulk-status" aria-live="polite"></p>
            <table class="widefat striped negaresh-bulk-results" hidden>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Post', 'negaresh'); ?></th>
                        <th scope="col"><?php esc_html_e('Changes', 'negaresh'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <?php
    }
}
