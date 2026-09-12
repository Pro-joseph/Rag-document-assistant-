import Link from 'next/link';

export default function Home(): React.JSX.Element {
  return (
    <main>
      <h1>Documents</h1>
      <p>Upload and chat pages are under construction. See components/ for UploadForm and DocumentList.</p>
      <Link href="/chat">Go to chat</Link>
    </main>
  );
}
