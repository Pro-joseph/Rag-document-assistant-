'use client';

import { useState } from 'react';
import Link from 'next/link';
import DocumentList from '../components/DocumentList';
import UploadForm from '../components/UploadForm';

export default function Home(): React.JSX.Element {
  const [refreshKey, setRefreshKey] = useState(0);

  return (
    <main className="space-y-6 pt-8">
      <section className="overflow-hidden rounded-2xl bg-slate-900 p-8 text-white shadow-pop">
        <p className="text-xs font-semibold uppercase tracking-widest text-indigo-300">RAG Document Assistant</p>
        <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Chat with your documents.</h1>
        <p className="mt-2 max-w-xl text-sm text-slate-300">
          Upload PDFs, Word files, text or spreadsheets. They get indexed, then every answer cites the exact source
          file it came from.
        </p>
        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            href="/chat"
            className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-card hover:bg-indigo-50"
          >
            Ask a question →
          </Link>
          <span className="rounded-xl border border-white/20 px-4 py-2 text-sm text-slate-300">
            Grounded · Cited · No hallucination
          </span>
        </div>
      </section>

      <section className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold tracking-tight">1 · Upload</h2>
          <span className="text-xs text-slate-500">PDF · DOCX · TXT · CSV · 20 MB</span>
        </div>
        <UploadForm onUploaded={() => setRefreshKey((key) => key + 1)} />
      </section>

      <section className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold tracking-tight">2 · Your files</h2>
          <Link href="/chat" className="text-sm font-semibold text-brand-600 hover:text-brand-700">
            Go to chat →
          </Link>
        </div>
        <DocumentList refreshKey={refreshKey} />
      </section>
    </main>
  );
}
