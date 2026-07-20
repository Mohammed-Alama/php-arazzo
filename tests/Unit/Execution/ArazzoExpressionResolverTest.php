<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Expression;
use PHPUnit\Framework\TestCase;

class ArazzoExpressionResolverTest extends TestCase
{
    public function test_compiles_request(): void
    {
        // Simple test for MVP
        $evaluator = new ExpressionEvaluator();
        $resolver = new ArazzoExpressionResolver($evaluator);
        
        $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], [], []);
        $context = new WorkflowContext('def_1');
        
        $request = $resolver->compileRequest($step, $context);
        
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://api.example.com/users', (string)$request->getUri());
    }
    
    public function test_extracts_outputs(): void
    {
        $evaluator = new ExpressionEvaluator();
        $resolver = new ArazzoExpressionResolver($evaluator);

        $step = new Step('step1', null, null, null, null, [], null, [], [], [], [
            'userId' => new Expression('$.data.id', null),
            'status' => new Expression('active', null) // literal fallback
        ], []);
        
        $responseData = [
            'data' => ['id' => 123]
        ];
        
        $outputs = $resolver->extractOutputs($step, $responseData);
        
        $this->assertEquals(123, $outputs['userId']);
        $this->assertEquals('active', $outputs['status']);
    }
}
