<?php

declare(strict_types=1);

return [
    'openai' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'api_key' => env('OPENAI_API_KEY', ''),
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'hot_state_ttl' => env('ARAZZO_HOT_STATE_TTL', 86400),
    'definitions_table' => 'arazzo_definitions',
    'executions_table' => 'arazzo_executions',
    'events_table' => 'arazzo_events',
];
