'use client';

import { useId, useRef, useState } from 'react';
import { listDocuments, uploadDocument } from '../lib/api';

const MAX_BYTES = 20 * 1024 * 1024;
const ACCEPT = '.pdf,.docx,.txt,.csv';

export default function UploadForm({ onUploaded }: { onUploaded: () => void }): React.JSX.Element {
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [dragOver, setDragOver] = useState(false);
  const [fileName, setFileName] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const inputId = useId();

  async function handleFile(file: File | undefined): Promise<void> {
    if (!file) return;

    if (file.size > MAX_BYTES) {
      setError(`"${file.name}" is too large — max 20 MB.`);
      return;
    }

    setError(null);
    setLoading(true);
    setFileName(file.name);

    try {
      const { data } = await uploadDocument(file);
      const uploadedId = data.id;

      const poll = setInterval(async () => {
        try {
          const { data: documents } = await listDocuments();
          const found = documents.find((doc) => doc.id === uploadedId);
          if (!found || found.status === 'ready' || found.status === 'failed') {
            clearInterval(poll);
            setLoading(false);
            setFileName(null);
            if (inputRef.current) inputRef.current.value = '';
            onUploaded();
          }
        } catch (err) {
          clearInterval(poll);
          setLoading(false);
          setError(err instanceof Error ? err.message : 'List failed');
        }
      }, 2000);
    } catch (err) {
      setLoading(false);
      setError(err instanceof Error ? err.message : 'Upload failed');
    }
  }

  return (
    <div>
      <div
        role="button"
        tabIndex={0}
        aria-label="Upload a document"
        onClick={() => inputRef.current?.click()}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') inputRef.current?.click();
        }}
        onDragOver={(event) => {
          event.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(event) => {
          event.preventDefault();
          setDragOver(false);
          void handleFile(event.dataTransfer.files?.[0]);
        }}
        className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed bg-white px-6 py-10 text-center shadow-card transition ${
          dragOver ? 'border-brand-500 bg-brand-50' : 'border-slate-300 hover:border-brand-500 hover:bg-brand-50/50'
        } ${loading ? 'pointer-events-none opacity-70' : ''}`}
      >
        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white shadow-card">
          ↑
        </span>
        <p className="font-semibold text-slate-900">
          {loading ? `Indexing ${fileName ?? 'document'}…` : 'Drop a document here, or click to browse'}
        </p>
        <p className="text-sm text-slate-500">PDF, DOCX, TXT, CSV · max 20 MB · answers cite your files</p>
        {loading && (
          <span className="mt-1 h-1.5 w-48 overflow-hidden rounded-full bg-slate-200">
            <span className="skeleton block h-full w-full rounded-full" />
          </span>
        )}
        <input
          id={inputId}
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          className="sr-only"
          disabled={loading}
          onChange={(event) => {
            void handleFile(event.target.files?.[0]);
          }}
        />
      </div>
      {error !== null && (
        <p role="alert" className="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}
