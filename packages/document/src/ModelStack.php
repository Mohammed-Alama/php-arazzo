<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Document\Parser\Loader;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Validator\PreflightValidator;

/**
 * The pure model half of the Document graph: parsing primitives, the
 * validator and the expression engine. No vendor sits behind any of these,
 * so a consumer may construct them without violating the dependency rule.
 *
 * The engine is injected rather than built here: ExpressionEngine is
 * another package's concrete facade, and this class is not a facade.
 */
final readonly class ModelStack
{
    public function __construct(
        public Loader $loader,
        public Parser $parser,
        public Validator $validator,
        public ExpressionEngineInterface $engine,
        public ?PreflightValidator $preflight = null,
        public ?OpenApiOperationResolver $operationResolver = null,
    ) {}

    public static function default(
        ExpressionEngineInterface $engine,
        ?PreflightValidator $preflight = null,
        ?OpenApiOperationResolver $operationResolver = null,
    ): self {
        return new self(
            loader: Loader::new(),
            parser: new Parser(),
            validator: new Validator(RuleSet::default($engine)),
            engine: $engine,
            preflight: $preflight,
            operationResolver: $operationResolver,
        );
    }
}
