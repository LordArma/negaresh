<?php

/**
 * Minimal stand in for WP_Query, for unit tests only: records the query and returns the posts and
 * total set beforehand.
 */
class WP_Query
{
    /** @var array<string, mixed> arguments of the last query */
    public static $last = [];

    /** @var list<\WP_Post> what the next query returns */
    public static $next = [];

    /** @var int found_posts of the next query */
    public static $next_found = 0;

    /** @var list<\WP_Post> */
    public $posts;

    /** @var int */
    public $found_posts;

    /**
     * @param array<string, mixed> $query
     */
    public function __construct(array $query = [])
    {
        self::$last = $query;
        $this->posts = self::$next;
        $this->found_posts = self::$next_found;
    }
}
