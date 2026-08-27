<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\KnowledgeBase\Models\Document;
use Tests\TestCase;

/**
 * Guards the contract between DemoSeeder and DocumentService: the seeder writes its PDFs to the same
 * disk and path convention DocumentService::storeFile() uses, so DocumentService::getFileResponse()
 * can stream them back. If the two ever drift, `GET /documents/{id}/download` starts returning
 * "File not found." (404) and this test fails first.
 */
class DemoDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_knowledge_base_pdf_is_downloadable(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::query()->where('email', 'nadia.haddad@company.example')->firstOrFail();
        $token = auth('api')->login($user);
        $disk = Storage::disk(config('filesystems.default'));

        $documents = Document::query()->where('tenant_id', $user->tenant_id)->get();

        $this->assertCount(6, $documents, 'DemoSeeder should seed six knowledge-base documents.');

        foreach ($documents as $document) {
            $this->assertMatchesRegularExpression(
                '#^documents/\d+/[0-9a-f-]{36}\.pdf$#',
                $document->file_path,
                'Seeded path must match the DocumentService::storeFile() convention.',
            );
            $this->assertTrue($disk->exists($document->file_path), "Not on disk: {$document->file_path}");

            $download = $this->withHeader('Authorization', "Bearer {$token}")
                ->get("/api/v1/documents/{$document->id}/download");

            $download->assertOk();
            $download->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $download->streamedContent());
            $this->assertStringContainsString('attachment;', (string) $download->headers->get('content-disposition'));

            // Same bytes, but a disposition a viewer will render instead of saving.
            $preview = $this->withHeader('Authorization', "Bearer {$token}")
                ->get("/api/v1/documents/{$document->id}/preview");

            $preview->assertOk();
            $preview->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $preview->streamedContent());
            $this->assertStringContainsString('inline;', (string) $preview->headers->get('content-disposition'));
        }
    }

    public function test_document_payload_exposes_working_preview_and_download_urls(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::query()->where('email', 'nadia.haddad@company.example')->firstOrFail();
        $token = auth('api')->login($user);
        $document = Document::query()->where('tenant_id', $user->tenant_id)->firstOrFail();

        $payload = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('preview_url', $payload);
        $this->assertArrayHasKey('download_url', $payload);

        // The advertised URLs must actually serve the file, not just look plausible.
        foreach (['preview_url', 'download_url'] as $key) {
            $path = parse_url($payload[$key], PHP_URL_PATH);

            $this->withHeader('Authorization', "Bearer {$token}")
                ->get($path)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_download_is_denied_across_tenants(): void
    {
        $this->seed(DemoSeeder::class);

        $document = Document::query()->firstOrFail();
        $outsider = User::query()->create([
            'first_name' => 'Out', 'last_name' => 'Sider', 'name' => 'Out Sider',
            'email' => 'outsider@elsewhere.example', 'password' => bcrypt('secret'),
            'role' => 'business_owner', 'is_active' => true, 'tenant_id' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.auth('api')->login($outsider))
            ->get("/api/v1/documents/{$document->id}/download")
            ->assertNotFound();
    }
}
