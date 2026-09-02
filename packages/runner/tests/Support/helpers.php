<?php

declare(strict_types=1);

if (!function_exists('tempStateDir')) {
    function tempStateDir(): string
    {
        return sys_get_temp_dir().'/arazzo-state-'.bin2hex(random_bytes(4));
    }
}

