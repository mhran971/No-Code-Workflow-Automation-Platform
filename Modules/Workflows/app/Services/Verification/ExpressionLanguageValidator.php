<?php

namespace Modules\Workflows\Services\Verification;

use Modules\Workflows\Services\Expression\ExpressionLexer;
use Modules\Workflows\Services\Verification\Data\ExpressionValidationResult;

class ExpressionLanguageValidator
{
    protected ExpressionLexer $lexer;

    protected array $variables = [];

    public function validateBoolean(string $expression): ExpressionValidationResult
    {
        $expression = trim($expression);

        if ($expression === '') {
            return new ExpressionValidationResult(false, false, [], 'Expression cannot be empty.');
        }

        try {
            $this->lexer = new ExpressionLexer($expression);
            $this->variables = [];

            $type = $this->parseOr();

            if (! $this->lexer->atEnd()) {
                return new ExpressionValidationResult(false, false, $this->variables, 'Unexpected token near "'.$this->lexer->currentValue().'".');
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

    protected function parseOr(): string
    {
        $type = $this->parseAnd();

        while ($this->lexer->consumeOperator('||')) {
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

        while ($this->lexer->consumeOperator('&&')) {
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

        if ($this->lexer->consumeAnyOperator(['==', '!=', '>=', '<=', '>', '<'])) {
            $this->parseUnary();

            return 'boolean';
        }

        return $type;
    }

    protected function parseUnary(): string
    {
        if ($this->lexer->consumeOperator('!')) {
            $type = $this->parseUnary();
            $this->assertBooleanLike($type, 'Negated expression must be boolean.');

            return 'boolean';
        }

        return $this->parsePrimary();
    }

    protected function parsePrimary(): string
    {
        if ($this->lexer->consumeOperator('(')) {
            $type = $this->parseOr();

            if (! $this->lexer->consumeOperator(')')) {
                throw new \RuntimeException('Missing closing parenthesis.');
            }

            return $type;
        }

        $token = $this->lexer->next();

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
}
