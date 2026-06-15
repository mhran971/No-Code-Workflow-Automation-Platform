<?php

namespace Modules\Workflows\Services\Verification;

class ExpressionLanguageValidator
{
    protected array $tokens = [];

    protected int $position = 0;

    protected array $variables = [];

    public function validateBoolean(string $expression): ExpressionValidationResult
    {
        $expression = trim($expression);

        if ($expression === '') {
            return new ExpressionValidationResult(false, false, [], 'Expression cannot be empty.');
        }

        try {
            $this->tokens = $this->tokenize($expression);
            $this->position = 0;
            $this->variables = [];

            $type = $this->parseOr();

            if (! $this->atEnd()) {
                return new ExpressionValidationResult(false, false, $this->variables, 'Unexpected token near "'.$this->currentValue().'".');
            }

            $isBoolean = $type === 'boolean';

            return new ExpressionValidationResult(
                $isBoolean,
                $isBoolean,
                array_values(array_unique($this->variables)),
                $isBoolean ? null : 'Expression must use a comparison or logical operator (e.g. context.age > 18). A bare variable reference is not a valid condition.'
            );
        } catch (\RuntimeException $exception) {
            return new ExpressionValidationResult(false, false, array_values(array_unique($this->variables)), $exception->getMessage());
        }
    }

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

            throw new \RuntimeException('Invalid expression token near "'.substr($expression, $offset, 12).'".');
        }

        return $tokens;
    }

    protected function parseOr(): string
    {
        $type = $this->parseAnd();

        while ($this->consumeOperator('||')) {
            $right = $this->parseAnd();
            $this->assertBooleanLike($type, 'Left side of || must be boolean.');
            $this->assertBooleanLike($right, 'Right side of || must be boolean.');
            $type = 'boolean';
        }

        return $type;
    }

    protected function parseAnd(): string
    {
        $type = $this->parseComparison();

        while ($this->consumeOperator('&&')) {
            $right = $this->parseComparison();
            $this->assertBooleanLike($type, 'Left side of && must be boolean.');
            $this->assertBooleanLike($right, 'Right side of && must be boolean.');
            $type = 'boolean';
        }

        return $type;
    }

    protected function parseComparison(): string
    {
        $type = $this->parseUnary();

        if ($this->consumeAnyOperator(['==', '!=', '>=', '<=', '>', '<'])) {
            $this->parseUnary();

            return 'boolean';
        }

        return $type;
    }

    protected function parseUnary(): string
    {
        if ($this->consumeOperator('!')) {
            $type = $this->parseUnary();
            $this->assertBooleanLike($type, 'Negated expression must be boolean.');

            return 'boolean';
        }

        return $this->parsePrimary();
    }

    protected function parsePrimary(): string
    {
        if ($this->consumeOperator('(')) {
            $type = $this->parseOr();

            if (! $this->consumeOperator(')')) {
                throw new \RuntimeException('Missing closing parenthesis.');
            }

            return $type;
        }

        $token = $this->next();

        if ($token === null) {
            throw new \RuntimeException('Unexpected end of expression.');
        }

        if ($token['type'] === 'identifier') {
            $this->variables[] = $token['value'];

            return 'variable';
        }

        if ($token['type'] === 'number') {
            return 'number';
        }

        if ($token['type'] === 'string') {
            return 'string';
        }

        if ($token['type'] === 'literal') {
            return strtolower($token['value']) === 'null' ? 'null' : 'boolean';
        }

        throw new \RuntimeException('Unexpected token "'.$token['value'].'".');
    }

    protected function assertBooleanLike(string $type, string $message): void
    {
        if (! in_array($type, ['boolean', 'variable'], true)) {
            throw new \RuntimeException($message);
        }
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

    protected function consumeAnyOperator(array $operators): ?string
    {
        $token = $this->tokens[$this->position] ?? null;

        if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
            $this->position++;

            return $token['value'];
        }

        return null;
    }

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
