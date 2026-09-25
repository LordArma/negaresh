<?php

/**
 * Minimal stand in for WordPress's final class WP_Comment, for unit tests only.
 */
final class WP_Comment
{
    /** @var string numeric string, as in WordPress */
    public $comment_ID = '0';

    /** @var string */
    public $comment_content = '';

    /** @var string */
    public $comment_post_ID = '0';
}
