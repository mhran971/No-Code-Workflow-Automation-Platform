<?php

namespace Modules\Workflows\Services\Execution\Expression;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Interpolates {{ expression }} placeholders in action config strings (e.g. send-email subject/body)
 * against the runtime context. Each placeholder body is evaluated by {@see ExpressionEvaluator}, so the
 * same dot-path resolution and sandboxing apply. An unresolvable placeholder renders as an empty string
 * rather than throwing, so a sparse context never breaks rendering.
 */
class TemplateInterpolator
{
    public function __construct(protected ExpressionEvaluator $evaluator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data = []): string
    {
        if (! str_contains($template, '{{')) {
            return $template;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function (array $matches) use ($data): string {
                $expression = trim($matches[1]);
                try {
                    $value = $this->evaluator->evaluate($expression, $data);

                    if ($value === null) {
                        Log::warning('workflow.template.unresolved', [
                            'expression'     => $expression,
                            'context_keys'   => array_keys((array) ($data['context'] ?? [])),
                            'trigger_keys'   => array_keys((array) ($data['trigger'] ?? [])),
                            'input_keys'     => array_keys((array) ($data['input'] ?? [])),
                        ]);
                    }

                    return $this->stringify($value);
                } catch (Throwable $e) {
                    Log::warning('workflow.template.eval_error', [
                        'expression' => $expression,
                        'error'      => $e->getMessage(),
                        'context_keys' => array_keys((array) ($data['context'] ?? [])),
                        'trigger_keys' => array_keys((array) ($data['trigger'] ?? [])),
                        'input_keys'   => array_keys((array) ($data['input'] ?? [])),
                    ]);

                    return '';
                }
            },
            $template,
        );
    }

    protected function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        };
    }
}
