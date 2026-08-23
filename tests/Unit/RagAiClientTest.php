<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Workflows\Services\Ai\RagAiClient;
use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;
use Tests\TestCase;

class RagAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.rag.url', 'https://rag.test');
        config()->set('services.rag.timeout', 60);
        config()->set('services.rag.api_key', 'secret-key');
    }

    public function test_generate_sends_prompt_tenant_and_tone_and_returns_content(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/generate' => Http::response([
                'success' => true,
                'content' => 'Hi there! Following up on your order...',
                'provider' => 'groq',
                'documents_used' => ['doc_101'],
            ], 200),
        ]);

        $client = new RagAiClient;
        $content = $client->generate('Write a follow-up email', '42', ['tone' => 'friendly', 'knowledgeBaseDocuments' => [101]]);

        $this->assertSame('Hi there! Following up on your order...', $content);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rag.test/api/v1/ai/generate'
                && $request->hasHeader('X-API-Key', 'secret-key')
                && $request['prompt'] === 'Write a follow-up email'
                && $request['tenant_id'] === '42'
                && $request['tone'] === 'friendly'
                && $request['article_ids'] === ['101'];
        });
    }

    public function test_generate_omits_tone_and_article_ids_when_absent(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/generate' => Http::response([
                'success' => true, 'content' => 'ok', 'provider' => 'groq', 'documents_used' => [],
            ], 200),
        ]);

        (new RagAiClient)->generate('hi', '1', []);

        Http::assertSent(function ($request) {
            return ! array_key_exists('tone', $request->data())
                && ! array_key_exists('article_ids', $request->data());
        });
    }

    public function test_generate_throws_retryable_on_soft_failure(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/generate' => Http::response([
                'success' => false, 'content' => '', 'provider' => 'fallback',
                'documents_used' => [], 'error' => 'LLM provider error: Connection timed out',
            ], 200),
        ]);

        try {
            (new RagAiClient)->generate('hi', '1');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertSame('LLM provider error: Connection timed out', $e->getMessage());
        }
    }

    public function test_generate_throws_non_retryable_on_hard_failure(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/generate' => Http::response(['detail' => 'tenant_id is required'], 400),
        ]);

        try {
            (new RagAiClient)->generate('hi', '1');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->isRetryable());
            $this->assertSame(400, $e->status());
            $this->assertSame('tenant_id is required', $e->getMessage());
        }
    }

    public function test_generate_throws_retryable_on_server_error(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/generate' => Http::response(['detail' => 'Internal error'], 500),
        ]);

        try {
            (new RagAiClient)->generate('hi', '1');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_classify_sends_text_categories_and_tenant_and_returns_result(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/classify' => Http::response([
                'success' => true, 'classification' => 'shipping', 'confidence' => 0.87,
            ], 200),
        ]);

        $result = (new RagAiClient)->classify('I would like a refund', ['billing', 'shipping'], '42');

        $this->assertSame(['classification' => 'shipping', 'confidence' => 0.87], $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rag.test/api/v1/ai/classify'
                && $request->hasHeader('X-API-Key', 'secret-key')
                && $request['text'] === 'I would like a refund'
                && $request['categories'] === ['billing', 'shipping']
                && $request['tenant_id'] === '42';
        });
    }

    public function test_classify_throws_retryable_on_soft_failure(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/classify' => Http::response([
                'success' => false, 'classification' => null, 'confidence' => 0.0,
                'error' => 'Unable to classify: no matching category',
            ], 200),
        ]);

        try {
            (new RagAiClient)->classify('hi', ['a', 'b'], '1');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_classify_throws_non_retryable_on_hard_failure(): void
    {
        Http::fake([
            'https://rag.test/api/v1/ai/classify' => Http::response(['detail' => 'categories must contain at least 2 values'], 400),
        ]);

        try {
            (new RagAiClient)->classify('hi', ['a'], '1');
            $this->fail('Expected AiServiceException.');
        } catch (AiServiceException $e) {
            $this->assertFalse($e->isRetryable());
            $this->assertSame(400, $e->status());
        }
    }
}
