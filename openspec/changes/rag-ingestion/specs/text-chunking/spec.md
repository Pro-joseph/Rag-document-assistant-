## ADDED Requirements

### Requirement: TextChunker two-phase chunking with overlap
The system SHALL provide `App\Services\TextChunker` with `chunk(string $text, int $chunkSize=800, int $overlap=150): array` implementing split-then-merge: `splitIntoPieces(text, chunkSize, ["\n\n","\n",". "," "])` recursively exploding by separator, then `mergePieces(pieces, chunkSize, overlap)` merging with `mb_strlen`/`mb_substr`, trimming, and tail overlap `mb_substr(current, -overlap)`. Empty/whitespace input returns `[]`.

#### Scenario: Empty text returns empty array
- **WHEN** `chunk("")` or `chunk("   ")` is called
- **THEN** returns `[]`

#### Scenario: Small text single chunk
- **WHEN** `chunk("hello world", 800, 150)` is called
- **THEN** returns `["hello world"]`

#### Scenario: Boundary respect and size invariant
- **WHEN** a 2000-char text with paragraphs/sentences is chunked with `chunkSize 800`
- **THEN** every chunk satisfies `mb_strlen(chunk) <=800` and splits prefer `\n\n` then `\n` then `. ` then ` ` (no mid-sentence split if possible)

#### Scenario: Overlap preserved
- **WHEN** two consecutive chunks are produced with `overlap 150`
- **THEN** the start of chunk N+1 contains the tail 150 chars of chunk N (when length allows)

#### Scenario: Oversized piece handled
- **WHEN** a single piece longer than 800 is encountered (e.g., 1000-char word without separators)
- **THEN** `mergePieces` emits `mb_substr(current,0,800)` slices with overlap sliding window and no chunk exceeds 800

### Requirement: Chunker is invariant and tested in isolation
Chunker SHALL be unit-tested with real PDFs (malformed line breaks) and synthetic edge cases; design SHALL keep two-phase implementation per `rag-laravel-nextjs-guide.md:193` warning.

#### Scenario: Unit test boundary regression
- **WHEN** a fixture with `\n\n` and `. ` boundaries is chunked
- **THEN** chunk count and sizes match expected snapshot and overlap is verified
