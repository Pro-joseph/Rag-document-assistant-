'use client';

import { useEffect, useState } from 'react';
import { deleteDocument, listDocuments, type DocumentItem } from '../lib/api';

function badgeClass(status: DocumentItem['status']): string {
  if (status === 'ready') return 'bg-green-100 text-green-800 ring-green-200';
  if (status === 'failed') return 'bg-red-100 text-red-800 ring-red-200';
  return 'bg-amber-100 text-amber-800 ring-amber-200 animate-pulse';
}

function statusLabel(status: DocumentItem['status']): string {
  if (status === 'ready') return '● Ready';
  if (status === 'failed') return '● Failed';
  if (status === 'processing') return '◌ Indexing…';
  return '◌ Queued';
}

function fileIcon(filename: string): string {
  const ext = filename.split('.').pop()?.toLowerCase() ?? '';
  if (ext === 'pdf') return '📕';
  if (ext === 'docx' || ext === 'doc') return '📘';
  if (ext === 'csv') return '📊';
  return '📄';
}

function SkeletonRow(): React.JSX.Element {
  return (
    <li className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-card">
      <span className="skeleton h-10 w-10 rounded-xl" />
      <div className="flex-1 space-y-2">
        <span className="skeleton block h-4 w-2/3 rounded" />
        <span className="skeleton block h-3 w-1/3 rounded" />
      </div>
    </li>
  );
}

export default function DocumentList({ refreshKey }: { refreshKey: number }): React.JSX.Element {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [confirmId, setConfirmId] = useState<number | null>(null);

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
      setConfirmId(null);
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

  if (loading) {
    return (
      <ul className="grid gap-3">
        <SkeletonRow />
        <SkeletonRow />
        <SkeletonRow />
      </ul>
    );
  }

  if (documents.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-card">
        <span className="text-4xl">📚</span>
        <p className="font-semibold text-slate-900">No documents yet</p>
        <p className="max-w-sm text-sm text-slate-500">
          Upload a PDF, DOCX, TXT or CSV above — it will appear here once indexed, then you can chat with it.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {error !== null && (
        <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
          {error}{' '}
          <button className="ml-2 font-semibold underline" onClick={() => refresh()}>
            Retry
          </button>
        </p>
      )}
      <ul className="grid gap-3">
        {documents.map((doc) => (
          <li
            key={doc.id}
            className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-card transition hover:shadow-pop"
          >
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl">
              {fileIcon(doc.filename)}
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium text-slate-900" title={doc.filename}>
                {doc.filename}
              </p>
              <p className="text-xs text-slate-500">{new Date(doc.created_at).toLocaleString()}</p>
            </div>
            <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${badgeClass(doc.status)}`}>
              {statusLabel(doc.status)}
            </span>
            {confirmId === doc.id ? (
              <span className="flex shrink-0 gap-1">
                <button
                  onClick={() => onDelete(doc.id)}
                  className="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-500"
                >
                  Confirm
                </button>
                <button
                  onClick={() => setConfirmId(null)}
                  className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                >
                  Keep
                </button>
              </span>
            ) : (
              <button
                onClick={() => setConfirmId(doc.id)}
                className="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-400 hover:bg-red-50 hover:text-red-600"
                aria-label={`Delete ${doc.filename}`}
              >
                Delete
              </button>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
