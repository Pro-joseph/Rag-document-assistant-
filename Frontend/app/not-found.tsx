import Link from 'next/link';

export default function NotFound(): React.JSX.Element {
  return (
    <main className="flex flex-col items-center gap-3 py-20 text-center">
      <span className="text-4xl">🔍</span>
      <h1 className="text-xl font-bold">Page not found</h1>
      <p className="text-sm text-slate-500">The page you are looking for does not exist.</p>
      <Link href="/" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
        Back to documents
      </Link>
    </main>
  );
}
