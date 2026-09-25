<?php

/**
 * WP-CLI commands (I6). Loaded only under WP-CLI.
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fixes Persian text with Negaresh's saved rules.
 */
class Negaresh_CLI
{
    /** @var Negaresh_Bulk */
    private $bulk;

    /** @var Negaresh */
    private $plugin;

    /** @var Negaresh_Settings */
    private $settings;

    public function __construct(Negaresh_Bulk $bulk, Negaresh $plugin, Negaresh_Settings $settings)
    {
        $this->bulk = $bulk;
        $this->plugin = $plugin;
        $this->settings = $settings;
    }

    /**
     * Fixes existing posts. Shows what would change unless --apply is given.
     *
     * Uses the saved rules and the same post types as "fix before saving". Posts marked "leave this
     * post alone" are never touched; posts already fixed with the current rules are skipped unless
     * --all is given. Every changed post gets a revision, so a change can be undone from the post's
     * revisions screen.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : Only these post IDs.
     *
     * [--post_type=<types>]
     * : Comma separated post types. Default: every type Negaresh fixes.
     *
     * [--all]
     * : Include posts already fixed with the current rules.
     *
     * [--limit=<number>]
     * : Look at no more than this many posts.
     *
     * [--apply]
     * : Write the changes. Without it nothing is saved.
     *
     * [--diff]
     * : Print the changed lines of each post.
     *
     * [--format=<format>]
     * : How to print the list of changed posts.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # See what would change in every post
     *     $ wp negaresh fix
     *
     *     # Fix all pages and show the changed lines
     *     $ wp negaresh fix --post_type=page --apply --diff
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc_args
     */
    public function fix(array $args, array $assoc_args): void
    {
        $apply = !empty($assoc_args['apply']);
        $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
        $query = ['all' => !empty($assoc_args['all'])];
        if (!empty($assoc_args['post_type'])) {
            $query['post_type'] = array_values(array_filter(array_map('trim', explode(',', (string) $assoc_args['post_type']))));
        }
        if (!empty($assoc_args['limit'])) {
            $query['limit'] = (int) $assoc_args['limit'];
        }
        if ($args) {
            $query['ids'] = array_map('intval', $args);
        }

        $ids = $this->bulk->find($query);
        $rows = [];
        $counts = ['changed' => 0, 'unchanged' => 0, 'skipped' => 0];
        $progress = null;
        if ($apply && 'table' === $format) {
            // A real bar on a terminal; WP-CLI hands back a no-op object otherwise.
            $bar = \WP_CLI\Utils\make_progress_bar('Fixing posts', count($ids));
            $progress = $bar instanceof \cli\progress\Bar ? $bar : null;
        }

        foreach ($ids as $id) {
            $result = $this->bulk->process($id, $apply);
            if ($progress) {
                $progress->tick();
            }
            if (null !== $result['skipped']) {
                $counts['skipped']++;
                \WP_CLI::debug("Skipped post {$id}: {$result['skipped']}", 'negaresh');
                continue;
            }
            if (!$result['changed']) {
                $counts['unchanged']++;
                continue;
            }
            $counts['changed']++;
            $rows[] = [
                'ID' => $id,
                'type' => $result['type'],
                'title' => $result['title'],
                'fields' => implode(', ', array_keys($result['fields'])),
            ];
            if (!empty($assoc_args['diff']) && 'table' === $format) {
                $this->print_diff($id, $result['fields']);
            }
        }
        if ($progress) {
            $progress->finish();
        }

        if ('count' === $format) {
            \WP_CLI::line((string) $counts['changed']);
            return;
        }
        if ($rows) {
            \WP_CLI\Utils\format_items($format, $rows, ['ID', 'type', 'title', 'fields']);
        }
        if ('table' !== $format) {
            return;
        }

        $summary = sprintf(
            '%d of %d posts %s; %d unchanged; %d skipped.',
            $counts['changed'],
            count($ids),
            $apply ? 'fixed' : 'would change',
            $counts['unchanged'],
            $counts['skipped']
        );
        if ($apply) {
            \WP_CLI::success($summary);
        } else {
            \WP_CLI::line($summary . ($counts['changed'] ? ' Nothing was saved: run again with --apply.' : ''));
        }
    }

    /**
     * Shows the mode and where the posts stand: fixed with the current rules, waiting, opted out.
     *
     * ## OPTIONS
     *
     * [--post_type=<types>]
     * : Comma separated post types. Default: every type Negaresh fixes.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp negaresh status
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc_args
     */
    public function status(array $args, array $assoc_args): void
    {
        $types = empty($assoc_args['post_type']) ? [] : array_values(array_filter(array_map('trim', explode(',', (string) $assoc_args['post_type']))));
        $stats = $this->bulk->stats($types);
        $mode = $this->settings->mode();

        if ('json' === ($assoc_args['format'] ?? 'table')) {
            \WP_CLI::line((string) wp_json_encode(['mode' => $mode, 'post_types' => $this->bulk->types($types)] + $stats));
            return;
        }
        \WP_CLI::line('Mode: ' . ('save' === $mode ? 'fix when a post is saved' : 'fix when a post is displayed'));
        \WP_CLI::line('Post types: ' . implode(', ', $this->bulk->types($types)));
        \WP_CLI\Utils\format_items('table', [
            ['posts' => 'fixed with the current rules', 'count' => $stats['fixed']],
            ['posts' => 'waiting (wp negaresh fix --apply)', 'count' => $stats['waiting']],
            ['posts' => 'opted out (never changed)', 'count' => $stats['opted_out']],
        ], ['posts', 'count']);
    }

    /**
     * Prints text fixed with the saved rules: the argument, or standard input.
     *
     * ## OPTIONS
     *
     * [<text>]
     * : The text (HTML is fine). Read from standard input when missing.
     *
     * ## EXAMPLES
     *
     *     $ wp negaresh text "کتاب ها را خواندید ?"
     *     $ wp post get 12 --field=post_content | wp negaresh text
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc_args
     */
    public function text(array $args, array $assoc_args): void
    {
        $text = $args ? implode(' ', $args) : (string) stream_get_contents(STDIN);
        \WP_CLI::line($this->plugin->safe_fix(rtrim($text, "\n")));
    }

    /**
     * @param array<string, array{before: string, after: string}> $fields
     */
    private function print_diff(int $id, array $fields): void
    {
        foreach ($fields as $field => $change) {
            \WP_CLI::line(\WP_CLI::colorize("%B── post {$id}, {$field}%n"));
            foreach (Negaresh_Bulk::diff($change['before'], $change['after']) as [$sign, $line]) {
                \WP_CLI::line(\WP_CLI::colorize(('-' === $sign ? '%r- ' : '%g+ ') . str_replace('%', '%%', $line) . '%n'));
            }
        }
    }
}
