<?php

/**
 * Minimal stand in for WP_REST_Request, for unit tests only.
 */
class WP_REST_Request
{
    /** @var array<string, mixed> */
    private $params;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * @return mixed
     */
    public function get_param(string $key)
    {
        return $this->params[$key] ?? null;
    }
}
