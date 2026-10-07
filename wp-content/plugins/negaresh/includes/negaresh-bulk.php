<?php

/**
 * Fixing posts that already exist (I6): the engine behind `wp negaresh fix` and the bulk tool page.
 * Same rules and scope as save mode. Before a change the current text is kept as a revision, so
 * every change can be undone from the post's revisions (when the site keeps revisions).
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Bulk
{
    /** Statuses worth fixing; trash and auto drafts are left alone. */
    public const STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    /** Fields a save fixes, and the option that enables each (null: always). */
    private const FIELDS = ['post_content' => null, 'post_title' => 'fix_titles', 'post_excerpt' => 'fix_excerpts'];

    /** Above this many lines per side the diff shows the whole field as changed. */
    private const DIFF_MAX_LINES = 2000;

    /** @var Negaresh */
    private $plugin;

    /** @var Negaresh_Settings */
    private $settings;

    public function __construct(Negaresh $plugin, Negaresh_Settings $settings)
    {
        $this->plugin = $plugin;
        $this->settings = $settings;
    }

    /**
     * Post types a bulk run may touch: the requested ones (or every public type plus synced
     * patterns), limited to what save mode fixes.
     *
     * @param list<string> $requested
     * @return list<string>
     */
    public function types(array $requested = []): array
    {
        $candidates = $requested ?: array_merge(array_values(get_post_types(['public' => true])), ['wp_block']);
        return array_values(array_filter(array_unique($candidates), [$this->plugin, 'saves_type']));
    }

    /** Views of the bulk tool's list, as in the posts list (B33). */
    public const VIEWS = ['waiting', 'fixed', 'opted_out', 'all'];

    /**
     * IDs of posts to look at, oldest first. Opted out posts are never included; posts already
     * fixed with the current rules only with 'all'. Without a limit every ID comes at once, so a
     * run that marks posts as it goes cannot shift a page.
     *
     * @param array{post_type?: list<string>, all?: bool, limit?: int, ids?: list<int>} $args
     * @return list<int>
     */
    public function find(array $args): array
    {
        $types = $this->types($args['post_type'] ?? []);
        if (!$types) {
            return [];
        }

        $query = [
            'post_type' => $types,
            'post_status' => self::STATUSES,
            'fields' => 'ids',
            'posts_per_page' => isset($args['limit']) && $args['limit'] > 0 ? (int) $args['limit'] : -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => $this->meta_query(empty($args['all']) ? 'waiting' : 'checkable'),
        ];
        if (!empty($args['ids'])) {
            $query['post__in'] = array_map('intval', $args['ids']);
        }

        return array_values(array_map('intval', get_posts($query)));
    }

    /**
     * One page of the bulk tool's list (B33): posts in a view, newest first by default, and how
     * many there are in all.
     *
     * @param array{view?: string, post_type?: string, search?: string, paged?: int, per_page?: int,
     *              orderby?: string, order?: string} $args
     * @return array{posts: list<\WP_Post>, total: int}
     */
    public function listing(array $args): array
    {
        $types = $this->types(!empty($args['post_type']) ? [$args['post_type']] : []);
        if (!$types) {
            return ['posts' => [], 'total' => 0];
        }
        $view = in_array($args['view'] ?? '', self::VIEWS, true) ? (string) $args['view'] : 'waiting';
        $orderby = in_array($args['orderby'] ?? '', ['title', 'date', 'type'], true) ? (string) $args['orderby'] : 'date';
        $order = 'asc' === strtolower($args['order'] ?? '') ? 'ASC' : 'DESC';

        $query = [
            'post_type' => $types,
            'post_status' => self::STATUSES,
            'posts_per_page' => max(1, (int) ($args['per_page'] ?? 20)),
            'paged' => max(1, (int) ($args['paged'] ?? 1)),
            'orderby' => 'type' === $orderby ? 'post_type' : $orderby,
            'order' => $order,
            'ignore_sticky_posts' => true,
        ];
        $meta_query = $this->meta_query($view);
        if ($meta_query) {
            $query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }
        $search = trim((string) ($args['search'] ?? ''));
        if ('' !== $search) {
            $query['s'] = $search;
        }

        $result = new \WP_Query($query);
        $posts = [];
        foreach ($result->posts as $post) {
            if ($post instanceof \WP_Post) {
                $posts[] = $post;
            }
        }
        return ['posts' => $posts, 'total' => (int) $result->found_posts];
    }

    /**
     * Where a post stands: 'opted_out', 'fixed' (with the current rules) or 'waiting'.
     */
    public function state(int $post_id): string
    {
        if ('1' === (string) get_post_meta($post_id, Negaresh_Settings::SKIP_META, true)) {
            return 'opted_out';
        }
        return $this->settings->rules_hash() === (string) get_post_meta($post_id, Negaresh_Settings::FIXED_META, true)
            ? 'fixed' : 'waiting';
    }

    /**
     * Meta query for a view: 'checkable' (not opted out), 'waiting' (and not fixed with the current
     * rules), 'fixed' (with the current rules), 'opted_out'; 'all' has none.
     *
     * @return array<int|string, mixed>
     */
    private function meta_query(string $view): array
    {
        if ('all' === $view) {
            return [];
        }
        if ('opted_out' === $view) {
            return [['key' => Negaresh_Settings::SKIP_META, 'value' => '1', 'compare' => '=']];
        }
        $meta_query = [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => Negaresh_Settings::SKIP_META, 'compare' => 'NOT EXISTS'],
                ['key' => Negaresh_Settings::SKIP_META, 'value' => '1', 'compare' => '!='],
            ],
        ];
        if ('waiting' === $view) {
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => Negaresh_Settings::FIXED_META, 'compare' => 'NOT EXISTS'],
                ['key' => Negaresh_Settings::FIXED_META, 'value' => $this->settings->rules_hash(), 'compare' => '!='],
            ];
        } elseif ('fixed' === $view) {
            $meta_query[] = ['key' => Negaresh_Settings::FIXED_META, 'value' => $this->settings->rules_hash(), 'compare' => '='];
        }
        return $meta_query;
    }

    /**
     * Where the posts in scope stand (P3-5): fixed with the current rules, waiting (never fixed, or
     * fixed with older rules), and opted out.
     *
     * @param list<string> $post_type
     * @return array{total: int, fixed: int, waiting: int, opted_out: int}
     */
    public function stats(array $post_type = []): array
    {
        $total = count($this->find(['post_type' => $post_type, 'all' => true]));
        $waiting = count($this->find(['post_type' => $post_type]));

        $types = $this->types($post_type);
        $opted_out = $types ? count(get_posts([
            'post_type' => $types,
            'post_status' => self::STATUSES,
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => $this->meta_query('opted_out'),
        ])) : 0;

        return ['total' => $total, 'fixed' => $total - $waiting, 'waiting' => $waiting, 'opted_out' => $opted_out];
    }

    /**
     * Fixes one post. With $apply false nothing is written (dry run).
     *
     * @return array{id: int, title: string, type: string, changed: bool, skipped: string|null,
     *               fields: array<string, array{before: string, after: string}>}
     */
    public function process(int $post_id, bool $apply): array
    {
        $result = ['id' => $post_id, 'title' => '', 'type' => '', 'changed' => false, 'skipped' => null, 'fields' => []];

        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            $result['skipped'] = 'missing';
            return $result;
        }
        $result['title'] = $post->post_title;
        $result['type'] = $post->post_type;
        if ('1' === (string) get_post_meta($post_id, Negaresh_Settings::SKIP_META, true)) {
            $result['skipped'] = 'opted_out';
            return $result;
        }
        if (!$this->plugin->saves_type($post->post_type)) {
            $result['skipped'] = 'out_of_scope';
            return $result;
        }

        foreach (self::FIELDS as $field => $flag) {
            if (null !== $flag && !$this->settings->flag($flag)) {
                continue;
            }
            $before = (string) $post->$field;
            $after = $this->plugin->safe_fix($before);
            if ($after !== $before) {
                $result['fields'][$field] = ['before' => $before, 'after' => $after];
            }
        }
        $result['changed'] = [] !== $result['fields'];

        if (!$apply) {
            return $result;
        }

        if ($result['changed'] && !$this->update($post_id, $result['fields'])) {
            $result['skipped'] = 'failed';
            return $result;
        }
        update_post_meta($post_id, Negaresh_Settings::FIXED_META, $this->settings->rules_hash());
        // A post that needed no change is only marked, which fires no save_post: the dashboard
        // counts would stay stale for an hour (B33).
        delete_transient(Negaresh_Dashboard::STATS_TRANSIENT);

        return $result;
    }

    /**
     * wp_update_post() with kses switched off: only text between tags changes, and re-running kses
     * would strip embeds and scripts the author was allowed to add. kses is on for users without
     * unfiltered_html (site admins on multisite, sites with DISALLOW_UNFILTERED_HTML), which is who
     * may run the bulk tool page; WP-CLI switches it off by itself.
     *
     * @param array<string, array{before: string, after: string}> $fields
     */
    private function update(int $post_id, array $fields): bool
    {
        $update = ['ID' => $post_id];
        if (isset($fields['post_content'])) {
            $update['post_content'] = wp_slash($fields['post_content']['after']);
        }
        if (isset($fields['post_title'])) {
            $update['post_title'] = wp_slash($fields['post_title']['after']);
        }
        if (isset($fields['post_excerpt'])) {
            $update['post_excerpt'] = wp_slash($fields['post_excerpt']['after']);
        }

        // Keep the text as it is now as a revision, so the change can be undone from the post's
        // revisions. WordPress only saves a revision of the new text on update, and a post that was
        // never edited has no revision of its original. Skipped when revisions are off, or when
        // the latest revision already holds this text.
        wp_save_post_revision($post_id);

        $kses = false !== has_filter('content_save_pre', 'wp_filter_post_kses');
        if ($kses) {
            kses_remove_filters();
        }
        try {
            $id = wp_update_post($update, true);
        } finally {
            if ($kses) {
                kses_init_filters();
            }
        }
        return is_int($id) && $id > 0;
    }

    /**
     * Changed lines between two texts, in order: ['-', old line] and ['+', new line].
     *
     * @return list<array{string, string}>
     */
    public static function diff(string $before, string $after): array
    {
        $a = explode("\n", $before);
        $b = explode("\n", $after);
        $n = count($a);
        $m = count($b);
        if ($n > self::DIFF_MAX_LINES || $m > self::DIFF_MAX_LINES) {
            return array_merge(
                array_map(static function ($line) {
                    return ['-', $line];
                }, $a),
                array_map(static function ($line) {
                    return ['+', $line];
                }, $b)
            );
        }

        // Longest common subsequence table, then walk it to list removed and added lines.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $out = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['-', $a[$i++]];
            } else {
                $out[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $out[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $out[] = ['+', $b[$j++]];
        }
        return $out;
    }
}
