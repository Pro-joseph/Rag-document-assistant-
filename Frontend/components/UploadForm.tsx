'use client';

import { listDocuments, uploadDocument } from '../lib/api';

const MAX_BYTES = 20 * 1024 * 1024;

export default function UploadForm({ onUploaded }: { onUploaded: () => void }): React.JSX.Element {
  async function onChange(event: React.ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > MAX_BYTES) {
      alert('File too large (max 20MB)');
      return;
    }

    await uploadDocument(file);

    const poll = setInterval(async () => {
      const { data } = await listDocuments();
      const latest = data[0];
      if (!latest || latest.status === 'ready' || latest.status === 'failed') {
        clearInterval(poll);
        onUploaded();
      }
    }, 2000);
  }

  return <input type="file" accept=".pdf,.docx,.txt,.csv" onChange={onChange} />;
}
