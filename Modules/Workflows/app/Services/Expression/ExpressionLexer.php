<?php

namespace Modules\Workflows\Services\Expression;

class ExpressionLexer
{
    /** @var array<int, array{type: string, value: string}> */
    private array $tokens;

    private int $position = 0;

    public function __construct(string $expression)
    {
        $this->tokens = $this->tokenize($expression);
    }

    public function consumeOperator(string $operator): bool
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null && $token['type'] === 'operator' && $token['value'] === $operator) {
            $this->position++;

            return true;
        }

        return false;
    }

    public function consumeAnyOperator(array $operators): ?string
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
            $this->position++;

            return $token['value'];
        }

        return null;
    }

    public function next(): ?array
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null) {
            $this->position++;
        }

        return $token;
    }

    public function atEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }

    public function currentValue(): string
    {
        return (string) ($this->tokens[$this->position]['value'] ?? 'end of expression');
    }

    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        // Exact same tokenize() code from both classes
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
}
