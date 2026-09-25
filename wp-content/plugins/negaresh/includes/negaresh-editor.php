<?php

/**
 * Editor integration (I6): the per post "Leave this post alone" choice (block editor sidebar and
 * classic editor box) and the block editor's "Fix this post" button.
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Editor
{
    /** Classic editor form field, nonce field and nonce action. */
    public const FIELD = 'negaresh_skip';
    public const NONCE_FIELD = 'negaresh_skip_nonce';
    public const NONCE_ACTION = 'negaresh_skip';

    /** @var Negaresh */
    private $plugin;

    /** @var Negaresh_Settings */
    private $settings;

    public function __construct(Negaresh $plugin, Negaresh_Settings $settings)
    {
        $this->plugin = $plugin;
        $this->settings = $settings;

        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_assets']);
    }

    public function register_meta(): void
    {
        register_post_meta('', Negaresh_Settings::SKIP_META, [
            'type' => 'boolean',
            'single' => true,
            'default' => false,
            'show_in_rest' => true,
            'auth_callback' => static function ($allowed, $meta_key, $post_id): bool {
                return current_user_can('edit_post', $post_id);
            },
        ]);
    }

    /**
     * Classic editor box. The block editor shows the sidebar panel from editor.js instead.
     */
    public function add_meta_box(): void
    {
        add_meta_box('negaresh', __('Negaresh', 'negaresh'), [$this, 'render_meta_box'], null, 'side', 'default', [
            '__back_compat_meta_box' => true,
        ]);
    }

    /**
     * @param mixed $post
     */
    public function render_meta_box($post): void
    {
        $skip = $post instanceof \WP_Post && '1' === (string) get_post_meta($post->ID, Negaresh_Settings::SKIP_META, true);
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr(self::FIELD),
            checked($skip, true, false),
            esc_html__('Leave this post alone (do not fix its text)', 'negaresh')
        );
    }

    /**
     * @param mixed $post_id
     */
    public function save_meta_box($post_id): void
    {
        if (
            !is_numeric($post_id)
            || !isset($_POST[self::NONCE_FIELD])
            || false === wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', (int) $post_id)
        ) {
            return;
        }

        if (!empty($_POST[self::FIELD])) {
            update_post_meta((int) $post_id, Negaresh_Settings::SKIP_META, '1');
        } else {
            delete_post_meta((int) $post_id, Negaresh_Settings::SKIP_META);
        }
    }

    public function register_rest_routes(): void
    {
        foreach (get_post_types(['show_in_rest' => true]) as $type) {
            add_filter('rest_pre_insert_' . $type, [$this, 'capture_rest_skip'], 10, 2);
        }

        register_rest_route('negaresh/v1', '/fix', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_fix'],
            'permission_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
            'args' => [
                'content' => [
                    'required' => true,
                    'validate_callback' => static function ($value): bool {
                        return is_string($value);
                    },
                ],
                'title' => [
                    'required' => false,
                    'validate_callback' => static function ($value): bool {
                        return null === $value || is_string($value);
                    },
                ],
            ],
        ]);
    }

    /**
     * `rest_pre_insert_{type}`: the block editor sends the opt out as meta in the same request as
     * the content, but WordPress stores meta after the content was saved.
     *
     * @param mixed $prepared
     * @param mixed $request
     * @return mixed
     */
    public function capture_rest_skip($prepared, $request)
    {
        if (!$request instanceof \WP_REST_Request) {
            return $prepared;
        }
        $meta = $request->get_param('meta');
        if (is_array($meta) && array_key_exists(Negaresh_Settings::SKIP_META, $meta)) {
            $id = is_object($prepared) && isset($prepared->ID) && is_numeric($prepared->ID) ? (int) $prepared->ID : 0;
            $this->plugin->override_skip($id, (bool) $meta[Negaresh_Settings::SKIP_META]);
        }
        return $prepared;
    }

    /**
     * POST negaresh/v1/fix {content, title} → the same fixed with the saved rules. Used by the
     * "Fix this post" button; the editor applies it as one step that Undo reverts.
     *
     * @return array{content: string, title: string}
     */
    public function rest_fix(\WP_REST_Request $request): array
    {
        $content = $request->get_param('content');
        $title = $request->get_param('title');
        $content = is_string($content) ? $content : '';
        $title = is_string($title) ? $title : '';

        return [
            'content' => $this->plugin->safe_fix($content),
            'title' => $this->settings->flag('fix_titles') ? $this->plugin->safe_fix($title) : $title,
        ];
    }

    public function enqueue_assets(): void
    {
        wp_enqueue_script(
            'negaresh-editor',
            plugins_url('assets/editor.js', NEGARESH_FILE),
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-api-fetch', 'wp-notices'],
            NEGARESH_VERSION,
            true
        );
        wp_localize_script('negaresh-editor', 'negareshEditor', [
            'meta' => Negaresh_Settings::SKIP_META,
            'title' => __('Negaresh', 'negaresh'),
            'skipLabel' => __('Leave this post alone (do not fix its text)', 'negaresh'),
            'fixLabel' => __('Fix this post now', 'negaresh'),
            'fixHelp' => __('Fixes the text in the editor with the saved rules. Undo reverts it.', 'negaresh'),
            'nothing' => __('Nothing to fix.', 'negaresh'),
            'done' => __('Text fixed. Use Undo to revert.', 'negaresh'),
            'failed' => __('The text could not be fixed.', 'negaresh'),
        ]);
    }
}
