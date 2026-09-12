<?php

use App\Jobs\IngestDocument;
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

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'filename', 'status']])
        ->assertJsonPath('data.filename', 'doc.txt');

    $this->assertDatabaseHas('documents', ['filename' => 'doc.txt']);
    Queue::assertPushed(IngestDocument::class);
});

test('document upload rejects oversized file', function () {
    $this->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('big.pdf', 21 * 1024, 'application/pdf'),
    ])->assertStatus(422);
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
