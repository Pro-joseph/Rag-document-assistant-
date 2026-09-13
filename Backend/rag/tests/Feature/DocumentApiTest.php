<?php

use App\Jobs\IngestDocument;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('document upload creates associated record and dispatches ingestion', function () {
    Queue::fake();
    Storage::fake('local');

    $response = $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
    ]);

    $response->assertStatus(202)
        ->assertJsonStructure(['data' => ['id', 'filename', 'status', 'created_at', 'updated_at']])
        ->assertJsonPath('data.filename', 'doc.txt')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('documents', ['filename' => 'doc.txt']);
    Queue::assertPushed(IngestDocument::class);
});

test('document upload rejects oversized file', function () {
    $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('big.pdf', 21 * 1024, 'application/pdf'),
    ])->assertStatus(422);
});

test('document upload rejects invalid mime', function () {
    $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('image.png', 100, 'image/png'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

test('document list is ordered newest first', function () {
    $old = Document::create(['filename' => 'a.txt']);
    $old->created_at = now()->subDay();
    $old->save();
    Document::create(['filename' => 'b.txt']);

    $response = $this->getJson('/api/documents');

    $response->assertOk()->assertJsonPath('data.0.filename', 'b.txt');
});

test('document delete cascades chunks', function () {
    $doc = Document::create(['filename' => 'doc.pdf', 'status' => 'ready']);
    $doc->chunks()->create(['chunk_index' => 0, 'content' => 'hello']);

    $this->deleteJson("/api/documents/{$doc->id}")->assertNoContent();

    $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    $this->assertDatabaseCount('chunks', 0);
});

test('document delete removes stored file', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('doc.txt', 10, 'text/plain')->storeAs('uploads', 'doc.txt', 'local');
    $doc = Document::create(['filename' => 'doc.txt', 'storage_path' => $path]);

    Storage::disk('local')->assertExists($path);

    $this->deleteJson("/api/documents/{$doc->id}")->assertNoContent();

    Storage::disk('local')->assertMissing($path);
});

test('deleted document is not returned by search', function () {
    $doc = Document::create(['filename' => 'gone.pdf', 'status' => 'ready']);
    $doc->chunks()->create(['chunk_index' => 0, 'content' => 'secret revenue']);

    $docId = $doc->id;
    $this->deleteJson("/api/documents/{$docId}")->assertNoContent();

    expect(Document::find($docId))->toBeNull()
        ->and(Chunk::where('document_id', $docId)->count())->toBe(0);
});
