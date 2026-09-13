import Link from 'next/link';
import ChatWindow from '../../components/ChatWindow';

export default function ChatPage(): React.JSX.Element {
  return (
    <main className="space-y-4 pt-8">
      <div className="flex items-end justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-widest text-brand-600">Grounded Q&A</p>
          <h1 className="text-2xl font-bold tracking-tight">Chat with your files</h1>
          <p className="text-sm text-slate-500">Answers cite the exact sources — never invented.</p>
        </div>
        <Link
          href="/"
          className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 shadow-card hover:bg-slate-50"
        >
          ← Documents
        </Link>
      </div>
      <ChatWindow />
    </main>
  );
}
