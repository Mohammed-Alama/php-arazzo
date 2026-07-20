<?php

declare(strict_types=1);

return [
    'openai' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'api_key' => env('OPENAI_API_KEY', ''),
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],
];
