'use client';

import { useEffect, useState } from 'react';
import { listDocuments, uploadDocument } from '../lib/api';

const MAX_BYTES = 20 * 1024 * 1024;

export default function UploadForm({ onUploaded }: { onUploaded: () => void }): React.JSX.Element {
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    return () => {};
  }, []);

  async function onChange(event: React.ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > MAX_BYTES) {
      setError('File too large (max 20MB)');
      return;
    }

    setError(null);
    setLoading(true);

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
      <input type="file" accept=".pdf,.docx,.txt,.csv" onChange={onChange} disabled={loading} />
      {loading && <p>Uploading…</p>}
      {error !== null && (
        <p role="alert" className="text-red-600">
          {error}
        </p>
      )}
    </div>
  );
}
