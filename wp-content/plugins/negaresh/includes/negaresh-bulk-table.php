<?php

/**
 * The list on Tools → Negaresh (B33), built like the posts list (edit.php): views, search, post type
 * filter, bulk actions, row actions, pagination and a per page screen option. Loaded only on that
 * page, after WordPress's WP_List_Table.
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Negaresh_Bulk_Table extends WP_List_Table
{
    /** @var Negaresh_Bulk */
    private $bulk;

    /** @var string one of Negaresh_Bulk::VIEWS */
    private $view = 'waiting';

    /** @var string post type filter, '' for all */
    private $type = '';

    /** @var array{total: int, fixed: int, waiting: int, opted_out: int} */
    private $stats = ['total' => 0, 'fixed' => 0, 'waiting' => 0, 'opted_out' => 0];

    public function __construct(Negaresh_Bulk $bulk)
    {
        $this->bulk = $bulk;
        parent::__construct([
            'singular' => 'negaresh-post',
            'plural' => 'negaresh-posts',
            'ajax' => false,
            'screen' => 'tools_page_' . Negaresh_Bulk_Page::PAGE,
        ]);
    }

    /**
     * Post type of the filter, or '' when none (or one Negaresh does not fix) is chosen.
     */
    public static function requested_type(Negaresh_Bulk $bulk): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter, nothing is changed
        $type = isset($_GET['negaresh_type']) ? sanitize_key(wp_unslash($_GET['negaresh_type'])) : '';
        return '' !== $type && in_array($type, $bulk->types(), true) ? $type : '';
    }

    /**
     * The view in the address, or 'waiting'.
     */
    public static function requested_view(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter, nothing is changed
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        return in_array($view, Negaresh_Bulk::VIEWS, true) ? $view : 'waiting';
    }

    /**
     * @return array{total: int, fixed: int, waiting: int, opted_out: int}
     */
    public function stats(): array
    {
        return $this->stats;
    }

    public function current_view(): string
    {
        return $this->view;
    }

    public function current_type(): string
    {
        return $this->type;
    }

    public function prepare_items(): void
    {
        $this->view = self::requested_view();
        $this->type = self::requested_type($this->bulk);
        $this->stats = $this->bulk->stats('' !== $this->type ? [$this->type] : []);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- list arguments, nothing is changed
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'date';
        $order = isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'desc';
        // phpcs:enable

        $per_page = $this->get_items_per_page(Negaresh_Bulk_Page::PER_PAGE_OPTION, Negaresh_Bulk_Page::PER_PAGE);
        $page = $this->bulk->listing([
            'view' => $this->view,
            'post_type' => $this->type,
            'search' => $search,
            'paged' => $this->get_pagenum(),
            'per_page' => $per_page,
            'orderby' => $orderby,
            'order' => $order,
        ]);

        $this->items = $page['posts'];
        $this->set_pagination_args([
            'total_items' => $page['total'],
            'per_page' => $per_page,
            'total_pages' => (int) ceil($page['total'] / max(1, $per_page)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => esc_html__('Title', 'negaresh'),
            'type' => esc_html__('Type', 'negaresh'),
            'negaresh_state' => esc_html__('Status', 'negaresh'),
            'negaresh_changes' => esc_html__('Changes', 'negaresh'),
            'date' => esc_html__('Date', 'negaresh'),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    protected function get_sortable_columns()
    {
        return [
            'title' => ['title', false],
            'type' => ['type', false],
            'date' => ['date', true],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function get_bulk_actions()
    {
        return 'opted_out' === $this->view ? [] : [Negaresh_Bulk_Page::BULK_ACTION => esc_html__('Fix', 'negaresh')];
    }

    /**
     * @return array<string, string>
     */
    protected function get_views()
    {
        $views = [
            'waiting' => [__('Waiting', 'negaresh'), $this->stats['waiting']],
            'fixed' => [__('Fixed', 'negaresh'), $this->stats['fixed']],
            'opted_out' => [__('Left alone', 'negaresh'), $this->stats['opted_out']],
            'all' => [__('All', 'negaresh'), $this->stats['total'] + $this->stats['opted_out']],
        ];
        $base = Negaresh_Bulk_Page::url('' !== $this->type ? ['negaresh_type' => $this->type] : []);

        $links = [];
        foreach ($views as $view => [$label, $count]) {
            $current = $view === $this->view;
            $links[$view] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                esc_url(add_query_arg('view', $view, $base)),
                $current ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                esc_html(number_format_i18n($count))
            );
        }
        return $links;
    }

    /**
     * @param string $which
     */
    protected function extra_tablenav($which): void
    {
        if ('top' !== $which) {
            return;
        }
        $types = $this->bulk->types();
        if (count($types) < 2) {
            return;
        }
        echo '<div class="alignleft actions">';
        echo '<label for="negaresh-filter-type" class="screen-reader-text">' . esc_html__('Filter by post type', 'negaresh') . '</label>';
        echo '<select name="negaresh_type" id="negaresh-filter-type"><option value="">' . esc_html__('All post types', 'negaresh') . '</option>';
        foreach ($types as $type) {
            $object = get_post_type_object($type);
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($type),
                selected($type, $this->type, false),
                esc_html($object ? $object->labels->name : $type)
            );
        }
        echo '</select>';
        submit_button(__('Filter', 'negaresh'), '', 'filter_action', false, ['id' => 'negaresh-filter-submit']);
        echo '</div>';
    }

    public function no_items(): void
    {
        if ('waiting' === $this->view) {
            esc_html_e('No posts are waiting: every post is checked with the current rules.', 'negaresh');
        } else {
            esc_html_e('No posts found.', 'negaresh');
        }
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_cb($item): void
    {
        if ('opted_out' === $this->bulk->state($item->ID) || !current_user_can('edit_post', $item->ID)) {
            return;
        }
        printf(
            '<label class="screen-reader-text" for="negaresh-cb-%1$d">%2$s</label><input id="negaresh-cb-%1$d" type="checkbox" name="post[]" value="%1$d" />',
            (int) $item->ID,
            /* translators: %s: post title. */
            esc_html(sprintf(__('Select %s', 'negaresh'), $this->title_of($item)))
        );
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_title($item): void
    {
        $link = (string) get_edit_post_link($item->ID);
        echo '<strong>';
        if ($link) {
            printf('<a class="row-title" href="%s">%s</a>', esc_url($link), esc_html($this->title_of($item)));
        } else {
            echo esc_html($this->title_of($item));
        }
        $status = get_post_status_object((string) $item->post_status);
        if ('publish' !== $item->post_status && $status) {
            echo ' &mdash; <span class="post-state">' . esc_html((string) $status->label) . '</span>';
        }
        echo '</strong>';
    }

    /**
     * Row actions like the posts list: Edit, View, Fix now.
     *
     * @param \WP_Post $item
     * @param string $column_name
     * @param string $primary
     */
    protected function handle_row_actions($item, $column_name, $primary)
    {
        if ($column_name !== $primary) {
            return '';
        }
        $actions = [];
        if (current_user_can('edit_post', $item->ID)) {
            $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url((string) get_edit_post_link($item->ID)), esc_html__('Edit', 'negaresh'));
        }
        $permalink = (string) get_permalink($item);
        if ('' !== $permalink && is_post_type_viewable($item->post_type)) {
            $actions['view'] = sprintf('<a href="%s">%s</a>', esc_url($permalink), esc_html__('View', 'negaresh'));
        }
        if ('opted_out' !== $this->bulk->state($item->ID) && current_user_can('edit_post', $item->ID)) {
            $actions['negaresh_fix'] = sprintf(
                '<a class="negaresh-fix-one" data-id="%d" href="%s">%s</a>',
                (int) $item->ID,
                esc_url(Negaresh_Bulk_Page::fix_one_url($item->ID)),
                esc_html__('Fix now', 'negaresh')
            );
        }
        return $this->row_actions($actions);
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_type($item): void
    {
        $object = get_post_type_object($item->post_type);
        echo esc_html($object ? $object->labels->singular_name : $item->post_type);
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_negaresh_state($item): void
    {
        $labels = [
            'waiting' => __('Waiting', 'negaresh'),
            'fixed' => __('Fixed', 'negaresh'),
            'opted_out' => __('Left alone', 'negaresh'),
        ];
        $state = $this->bulk->state($item->ID);
        printf('<span class="negaresh-state negaresh-state-%s">%s</span>', esc_attr($state), esc_html($labels[$state] ?? $state));
    }

    /**
     * Filled in by bulk.js, which checks the posts on this page (nothing is saved).
     *
     * @param \WP_Post $item
     */
    protected function column_negaresh_changes($item): void
    {
        $state = $this->bulk->state($item->ID);
        if ('opted_out' === $state) {
            echo '&mdash;';
            return;
        }
        printf('<div class="negaresh-changes" data-id="%d" data-state="%s">&mdash;</div>', (int) $item->ID, esc_attr($state));
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_date($item): void
    {
        $date = get_the_date('', $item);
        echo esc_html(is_string($date) ? $date : '');
    }

    /**
     * Each row carries its post ID, for bulk.js.
     *
     * @param \WP_Post $item
     */
    public function single_row($item): void
    {
        printf('<tr id="negaresh-post-%1$d" data-id="%1$d">', (int) $item->ID);
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * @return string
     */
    protected function get_primary_column_name()
    {
        return 'title';
    }

    private function title_of(\WP_Post $item): string
    {
        /* translators: %d: post ID, shown for posts without a title. */
        return '' !== trim($item->post_title) ? $item->post_title : sprintf(__('(no title) #%d', 'negaresh'), $item->ID);
    }
}
