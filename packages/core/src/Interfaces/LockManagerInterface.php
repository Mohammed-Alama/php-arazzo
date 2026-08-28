<?php

declare(strict_types=1);

namespace Alama\Arazzo\Interfaces;

// Framework port (kept as a seam): locking is adapter-specific (Redis cache lock, DB advisory lock, in-process).

interface LockManagerInterface extends LockStrategyInterface {}
