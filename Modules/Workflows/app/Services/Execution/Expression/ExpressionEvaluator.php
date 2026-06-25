<?php

namespace Modules\Workflows\Services\Execution\Expression;

use RuntimeException;

/**
 * Runtime evaluator for the boolean/comparison expression language used by `if-node` and conditional edges.
 *
 * The grammar mirrors {@see \Modules\Workflows\Services\Verification\ExpressionLanguageValidator} exactly
 * (same tokens, same precedence) so that any expression the validator accepts at authoring time is evaluable
 * here at runtime — "validated ⇒ evaluable". It is intentionally sandboxed: only the operators
 * `&& || == != >= <= > < !`, parentheses, and string/number/true/false/null literals. No function calls,
 * no PHP eval — this is a user-authored-input boundary.
 *
 * Identifiers (e.g. `context.age`, `nodeKey.output.field`) resolve as dot-paths against the supplied data.
 * A missing identifier resolves to `null` rather than throwing, so a sparse context never crashes a run.
 */
class ExpressionEvaluator
{
    /** @var array<int, array{type: string, value: string}> */
    protected array $tokens = [];

    protected int $position = 0;

    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * Evaluate an expression to its raw value.
     *
     * @param  array<string, mixed>  $data  resolution scope (e.g. ['context' => [...], 'trigger' => [...]])
     */
    public function evaluate(string $expression, array $data = []): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new RuntimeException('Cannot evaluate an empty expression.');
        }

        $this->tokens = $this->tokenize($expression);
        $this->position = 0;
        $this->data = $data;

        $value = $this->parseOr();

        if (! $this->atEnd()) {
            throw new RuntimeException('Unexpected token near "'.$this->currentValue().'".');
        }

        return $value;
    }

    /**
     * Evaluate an expression and coerce the result to a boolean (truthiness).
     *
     * @param  array<string, mixed>  $data
     */
    public function evaluateBoolean(string $expression, array $data = []): bool
    {
        return $this->toBool($this->evaluate($expression, $data));
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    protected function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $offset = 0;

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
                $offset += strlen($match[0]);

                continue;
            }

            if (preg_match('/\G(&&|\|\||==|!=|>=|<=|>|<|!|\(|\))/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'operator', 'value' => $match[1]];
                $offset += strlen($match[1]);

                continue;
            }

            if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'string', 'value' => $match[0]];
                $offset += strlen($match[0]);

                continue;
            }

            if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'number', 'value' => $match[0]];
                $offset += strlen($match[0]);

                continue;
            }

            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/A', $expression, $match, 0, $offset)) {
                $value = $match[0];
                $tokens[] = [
                    'type' => in_array(strtolower($value), ['true', 'false', 'null'], true) ? 'literal' : 'identifier',
                    'value' => $value,
                ];
                $offset += strlen($value);

                continue;
            }

            throw new RuntimeException('Invalid expression token near "'.substr($expression, $offset, 12).'".');
        }

        return $tokens;
    }

    protected function parseOr(): mixed
    {
        $value = $this->parseAnd();

        while ($this->consumeOperator('||')) {
            if ($this->toBool($value)) {
                $this->skipAnd();          // short-circuit: still consume the RHS tokens
                $value = true;

                continue;
            }

            $value = $this->toBool($this->parseAnd());
        }

        return $value;
    }

    protected function parseAnd(): mixed
    {
        $value = $this->parseComparison();

        while ($this->consumeOperator('&&')) {
            if (! $this->toBool($value)) {
                $this->skipComparison();   // short-circuit: still consume the RHS tokens
                $value = false;

                continue;
            }

            $value = $this->toBool($this->parseComparison());
        }

        return $value;
    }

    protected function parseComparison(): mixed
    {
        $left = $this->parseUnary();

        $operator = $this->consumeAnyOperator(['==', '!=', '>=', '<=', '>', '<']);

        if ($operator !== null) {
            $right = $this->parseUnary();

            return $this->compare($operator, $left, $right);
        }

        return $left;
    }

    protected function parseUnary(): mixed
    {
        if ($this->consumeOperator('!')) {
            return ! $this->toBool($this->parseUnary());
        }

        return $this->parsePrimary();
    }

    protected function parsePrimary(): mixed
    {
        if ($this->consumeOperator('(')) {
            $value = $this->parseOr();

            if (! $this->consumeOperator(')')) {
                throw new RuntimeException('Missing closing parenthesis.');
            }

            return $value;
        }

        $token = $this->next();

        if ($token === null) {
            throw new RuntimeException('Unexpected end of expression.');
        }

        return match ($token['type']) {
            'identifier' => data_get($this->data, $token['value']),
            'number' => $this->parseNumber($token['value']),
            'string' => $this->parseString($token['value']),
            'literal' => $this->parseLiteral($token['value']),
            default => throw new RuntimeException('Unexpected token "'.$token['value'].'".'),
        };
    }

    protected function compare(string $operator, mixed $left, mixed $right): bool
    {
        return match ($operator) {
            '==' => $this->looseEquals($left, $right),
            '!=' => ! $this->looseEquals($left, $right),
            '>', '<', '>=', '<=' => $this->orderedCompare($operator, $left, $right),
            default => throw new RuntimeException('Unknown operator "'.$operator.'".'),
        };
    }

    protected function looseEquals(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return $this->toBool($a) === $this->toBool($b);
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    protected function orderedCompare(string $operator, mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        $ordering = (is_numeric($a) && is_numeric($b))
            ? (float) $a <=> (float) $b
            : strcmp((string) $a, (string) $b);

        return match ($operator) {
            '>' => $ordering > 0,
            '<' => $ordering < 0,
            '>=' => $ordering >= 0,
            '<=' => $ordering <= 0,
            default => false,
        };
    }

    protected function parseNumber(string $value): int|float
    {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    protected function parseString(string $value): string
    {
        return stripcslashes(substr($value, 1, -1));
    }

    protected function parseLiteral(string $value): ?bool
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    protected function consumeOperator(string $operator): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        if ($token !== null && $token['type'] === 'operator' && $token['value'] === $operator) {
            $this->position++;

            return true;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $operators
     */
    protected function consumeAnyOperator(array $operators): ?string
    {
        $token = $this->tokens[$this->position] ?? null;

        if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
            $this->position++;

            return $token['value'];
        }

        return null;
    }

    /**
     * Consume and discard an `&&`-level subexpression (used for short-circuit evaluation).
     */
    protected function skipComparison(): void
    {
        $this->parseComparison();
    }

    protected function skipAnd(): void
    {
        $this->parseAnd();
    }

    /**
     * @return array{type: string, value: string}|null
     */
    protected function next(): ?array
    {
        $token = $this->tokens[$this->position] ?? null;

        if ($token !== null) {
            $this->position++;
        }

        return $token;
    }

    protected function atEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }

    protected function currentValue(): string
    {
        return (string) ($this->tokens[$this->position]['value'] ?? 'end of expression');
    }
}
