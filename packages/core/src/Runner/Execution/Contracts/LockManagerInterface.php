<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Contracts;

use Alama\Arazzo\Runner\Contract\LockStrategyInterface;

// Framework port (kept as a seam): locking is adapter-specific (Redis cache lock, DB advisory lock, in-process).

interface LockManagerInterface extends LockStrategyInterface {}
