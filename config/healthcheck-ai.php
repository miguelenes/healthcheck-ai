<?php

declare(strict_types=1);

return [
    /*
     * Cache TTL for the embedding chain health check results in seconds.
     */
    'embedding_cache_ttl_seconds' => 300,

    /*
     * The expected dimensions for the embedding check.
     */
    'embedding_dimensions' => 768,

    /*
     * The text to use for the embedding canary check.
     */
    'embedding_canary_text' => 'legal embedding health check',

    /*
     * Cache TTL for the prompt chain health check results in seconds.
     */
    'prompt_cache_ttl_seconds' => 300,

    /*
     * The text to use for the prompt canary check.
     */
    'prompt_text' => 'Reply with exactly: OK',

    /*
     * Timeout for the prompt check in seconds.
     */
    'prompt_timeout_seconds' => 25,
];
