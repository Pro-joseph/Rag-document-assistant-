## ADDED Requirements

### Requirement: DocumentParser dispatches by extension
The system SHALL provide `App\Services\DocumentParser` with `parse(string $path, string $extension): string` dispatching via `match(strtolower(ext))` to `parsePdf|parseDocx|txt native|parseCsv`, throwing `InvalidArgumentException` for unsupported types.

#### Scenario: PDF parsed to text
- **WHEN** `parse('/tmp/a.pdf','pdf')` is called on a valid PDF
- **THEN** returns non-empty string via `Smalot\PdfParser\Parser()->parseFile()->getText()`

#### Scenario: DOCX parsed via PhpWord
- **WHEN** `parse('/tmp/a.docx','docx')` is called
- **THEN** returns concatenated `getText()` from `PhpOffice\PhpWord\IOFactory::load` sections/elements

#### Scenario: TXT passthrough
- **WHEN** `parse('/tmp/a.txt','txt')` is called
- **THEN** returns `file_get_contents($path)`

#### Scenario: CSV flattened to text
- **WHEN** `parse('/tmp/a.csv','csv')` is called
- **THEN** returns `implode("\n", array_map(fn($r)=>implode(', ', $r), array_map('str_getcsv', file($path))))`

#### Scenario: Unsupported extension throws
- **WHEN** `parse('/tmp/a.png','png')` is called
- **THEN** throws `InvalidArgumentException` with message `Unsupported file type`

### Requirement: Parser is service injectable and stateless
`DocumentParser` SHALL be stateless, bindable in container, and have no controller logic.

#### Scenario: DI resolution
- **WHEN** resolved via `app(DocumentParser::class)` inside a Job
- **THEN** instance is returned and `parse` is callable
