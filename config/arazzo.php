<?php

declare(strict_types=1);

return [
    'strict_schema_validation' => false,

    'openai' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'api_key' => env('OPENAI_API_KEY', ''),
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    'definitions_table' => 'arazzo_definitions',
    'executions_table' => 'arazzo_executions',
    'events_table' => 'arazzo_events',

    'pending_correlations_table' => 'arazzo_pending_correlations',

    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'retry_ceiling' => env('ARAZZO_RETRY_CEILING', 10),

    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'state_ttl' => env('ARAZZO_STATE_TTL', 86400),

    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'webhook_prefix' => env('ARAZZO_WEBHOOK_PREFIX', 'api/arazzo'),
];
