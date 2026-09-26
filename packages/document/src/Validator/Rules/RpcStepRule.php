<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * rpc-step rules (PR #556):
 *  - rpcMethod must be source-qualified: $sourceDescriptions.<name>.<service>/<method>
 *  - the named source must exist in the document
 *  - rpcProtocol must be set whenever rpcMethod is set
 *  - request content-type must be allowed for the declared protocol
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcStepRule implements Rule
{
    private const RPC_METHOD_PATTERN = '/^\$sourceDescriptions\.[A-Za-z0-9_-]+(?:\.[A-Za-z_][A-Za-z0-9_]*)+\/[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<string, list<string>> */
    private const CONTENT_TYPES = [
        'grpc' => ['application/grpc'],
        'grpc-web' => ['application/grpc-web+proto', 'application/grpc-web-text'],
        'twirp' => ['application/protobuf', 'application/json'],
        'connect' => ['application/json', 'application/proto', 'application/connect+json', 'application/connect+proto'],
    ];

    public function code(): string
    {
        return 'step.rpc_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->rpcMethod === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if (preg_match(self::RPC_METHOD_PATTERN, $s->target->rpcMethod) !== 1) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' rpcMethod must be source-qualified as "
                        .'$sourceDescriptions.<name>.<service>/<method>.',
                        $path,
                    );

                    continue;
                }

                $sourceName = explode('.', $s->target->rpcMethod)[1];
                if ($s->target->rpcProtocol === null) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod requires rpcProtocol to be set.", $path);
                } elseif (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod references unknown source '{$sourceName}'.", $path);
                } elseif (($type = $s->io->requestBody?->contentType) !== null
                    && !in_array($type, self::CONTENT_TYPES[$s->target->rpcProtocol->value], true)) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' content type '{$type}' is not allowed for {$s->target->rpcProtocol->value} RPC.",
                        $path,
                    );
                }
            }
        }
    }
}
