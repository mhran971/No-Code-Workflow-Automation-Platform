<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ExpressionEvaluatorTest extends TestCase
{
    protected ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ExpressionEvaluator;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('booleanCases')]
    public function test_evaluate_boolean(string $expression, array $data, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluator->evaluateBoolean($expression, $data));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}>
     */
    public static function booleanCases(): array
    {
        $context = fn (array $c): array => ['context' => $c];

        return [
            'numeric gt true' => ['context.age > 18', $context(['age' => 21]), true],
            'numeric gt false' => ['context.age > 18', $context(['age' => 16]), false],
            'numeric gte boundary' => ['context.age >= 18', $context(['age' => 18]), true],
            'string eq' => ["context.status == 'active'", $context(['status' => 'active']), true],
            'string neq' => ["context.status != 'active'", $context(['status' => 'paused']), true],
            'and both true' => ['context.age > 18 && context.vip', $context(['age' => 40, 'vip' => true]), true],
            'and short circuit false' => ['context.age > 18 && context.missing > 5', $context(['age' => 40]), false],
            'or first true' => ['context.vip || context.age > 90', $context(['vip' => true, 'age' => 10]), true],
            'or both false' => ['context.vip || context.age > 90', $context(['vip' => false, 'age' => 10]), false],
            'negation' => ['!context.blocked', $context(['blocked' => false]), true],
            'parentheses precedence' => ['(context.a || context.b) && context.c', $context(['a' => true, 'b' => false, 'c' => true]), true],
            'missing identifier is null' => ['context.missing > 5', $context([]), false],
            'numeric string coercion' => ['context.amount == 100', $context(['amount' => '100']), true],
            'boolean literal' => ['true && !false', [], true],
            'nested path' => ['context.user.role == "manager"', $context(['user' => ['role' => 'manager']]), true],
        ];
    }

    public function test_evaluate_returns_raw_values(): void
    {
        $this->assertSame(42, $this->evaluator->evaluate('context.n', ['context' => ['n' => 42]]));
        $this->assertSame('hi', $this->evaluator->evaluate("'hi'"));
        $this->assertNull($this->evaluator->evaluate('context.nope', ['context' => []]));
    }

    public function test_empty_expression_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->evaluator->evaluate('   ');
    }

    public function test_unbalanced_parenthesis_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->evaluator->evaluateBoolean('(context.a == 1', ['context' => ['a' => 1]]);
    }
}
