import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'RAG Document Assistant',
  description: 'Upload documents and chat with grounded answers',
};

export default function RootLayout({ children }: { children: React.ReactNode }): React.JSX.Element {
  return (
    <html lang="en">
      <body className="min-h-screen">
        <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur">
          <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4">
            <a href="/" className="flex items-center gap-2 font-semibold tracking-tight">
              <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white shadow-card">
                R
              </span>
              <span>
                RAG Assistant
                <span className="ml-2 hidden rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 sm:inline">
                  grounded answers
                </span>
              </span>
            </a>
            <nav className="flex items-center gap-1 text-sm">
              <a href="/" className="rounded-lg px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                Documents
              </a>
              <a
                href="/chat"
                className="rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white shadow-card hover:bg-slate-700"
              >
                Chat
              </a>
            </nav>
          </div>
        </header>
        <div className="mx-auto w-full max-w-5xl px-4 pb-16">{children}</div>
        <footer className="mx-auto w-full max-w-5xl px-4 pb-8 text-center text-xs text-slate-400">
          Answers cite your documents · API at NEXT_PUBLIC_API_URL
        </footer>
      </body>
    </html>
  );
}
