<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Enum;

/**
 * Why a single success criterion reached its {@see CriterionOutcome}.
 *
 * `Evaluated` covers both a clean pass and a clean fail. Every other case means
 * the criterion could not be evaluated, so the outcome is `Error`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum CriterionReason: string
{
    case Evaluated = 'evaluated';
    case SyntaxError = 'syntax_error';
    case ContextUnresolved = 'context_unresolved';
    case SelectorError = 'selector_error';
    case PluginError = 'plugin_error';
}
