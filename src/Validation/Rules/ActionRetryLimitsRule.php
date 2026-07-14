<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ActionRetryLimitsRule implements Rule
{
    public function code(): string { return 'action.retry_limits'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->onFailure as $i => $a) {
                    if (!$a instanceof RetryAction) continue;
                    $base = "/workflows/{$wi}/steps/{$si}/onFailure/{$i}";
                    if ($a->retryAfter !== null && $a->retryAfter < 0) {
                        $errors->error($this->code(), "retryAfter must be >= 0.", "{$base}/retryAfter");
                    }
                    if ($a->retryLimit !== null && $a->retryLimit < 0) {
                        $errors->error($this->code(), "retryLimit must be >= 0.", "{$base}/retryLimit");
                    }
                }
            }
        }
    }
}
