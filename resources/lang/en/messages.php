<?php

declare(strict_types=1);

return [
    'agent_registry' => [
        'ok'                  => 'All agents with declared providers have credentials configured.',
        'missing_credentials' => 'Missing AI credentials for: :agents',
        'missing_model'       => 'Some agents declare a provider but no #[Model] attribute: :agents',
    ],
    'embedding_chain' => [
        'ok'                         => 'Primary embedding provider is healthy.',
        'no_providers'               => 'No embedding providers configured in the failover chain.',
        'primary_failed_fallback_ok' => 'Primary embedding provider failed; a fallback succeeded.',
        'all_failed'                 => 'All embedding providers in the chain failed.',
    ],
    'prompt_chain' => [
        'ok'                         => 'Primary text provider is healthy.',
        'primary_failed_fallback_ok' => 'Primary text provider failed; a fallback succeeded.',
    ],
];
