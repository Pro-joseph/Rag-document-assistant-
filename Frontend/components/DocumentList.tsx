'use client';

import { useEffect, useState } from 'react';
import { deleteDocument, listDocuments, type DocumentItem } from '../lib/api';

function badgeClass(status: DocumentItem['status']): string {
  if (status === 'ready') return 'bg-green-100 text-green-800';
  if (status === 'failed') return 'bg-red-100 text-red-800';
  return 'bg-yellow-100 text-yellow-800 animate-pulse';
}

export default function DocumentList({ refreshKey }: { refreshKey: number }): React.JSX.Element {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function refresh(): Promise<void> {
    try {
      setError(null);
      const { data } = await listDocuments();
      setDocuments([...data].sort((a, b) => b.created_at.localeCompare(a.created_at)));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'List failed');
    } finally {
      setLoading(false);
    }
  }

  async function onDelete(id: number): Promise<void> {
    try {
      await deleteDocument(id);
      await refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Delete failed');
    }
  }

  useEffect(() => {
    void refresh();
  }, [refreshKey]);

  useEffect(() => {
    if (!documents.some((doc) => doc.status === 'pending' || doc.status === 'processing')) return;
    const poll = setInterval(() => {
      void refresh();
    }, 2000);
    return () => clearInterval(poll);
  }, [documents]);

  if (loading) return <p>Loading documents…</p>;
  if (documents.length === 0) return <p>No documents yet.</p>;

  return (
    <div>
      {error !== null && (
        <p role="alert" className="text-red-600">
          {error}
        </p>
      )}
      <ul>
        {documents.map((doc) => (
          <li key={doc.id}>
            {doc.filename} <span className={badgeClass(doc.status)}>{doc.status}</span>{' '}
            <span className="text-sm">{new Date(doc.created_at).toLocaleString()}</span>{' '}
            <button onClick={() => onDelete(doc.id)}>Delete</button>
          </li>
        ))}
      </ul>
    </div>
  );
}
