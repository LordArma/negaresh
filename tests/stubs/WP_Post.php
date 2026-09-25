<?php

/**
 * Minimal stand in for WordPress's final class WP_Post, for unit tests only.
 */
final class WP_Post
{
    /** @var int */
    public $ID = 0;

    /** @var string */
    public $post_content = '';

    /** @var string */
    public $post_type = 'post';
}
