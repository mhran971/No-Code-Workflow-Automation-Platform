<?php

namespace Tests\Unit\Execution;

use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Tests\TestCase;

class TemplateInterpolatorTest extends TestCase
{
    protected TemplateInterpolator $interpolator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interpolator = new TemplateInterpolator(new ExpressionEvaluator);
    }

    public function test_renders_placeholder(): void
    {
        $result = $this->interpolator->render(
            'Hello {{ context.firstName }}!',
            ['context' => ['firstName' => 'Ada']],
        );

        $this->assertSame('Hello Ada!', $result);
    }

    public function test_missing_value_renders_empty(): void
    {
        $this->assertSame('Hello !', $this->interpolator->render('Hello {{ context.firstName }}!', ['context' => []]));
    }

    public function test_passthrough_when_no_placeholders(): void
    {
        $this->assertSame('plain text', $this->interpolator->render('plain text'));
    }

    public function test_stringifies_scalars_and_bools(): void
    {
        $data = ['context' => ['count' => 3, 'active' => true]];

        $this->assertSame('n=3 active=true', $this->interpolator->render('n={{ context.count }} active={{ context.active }}', $data));
    }
}
