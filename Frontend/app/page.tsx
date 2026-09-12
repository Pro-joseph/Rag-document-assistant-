'use client';

import { useState } from 'react';
import Link from 'next/link';
import DocumentList from '../components/DocumentList';
import UploadForm from '../components/UploadForm';

export default function Home(): React.JSX.Element {
  const [refreshKey, setRefreshKey] = useState(0);

  return (
    <main>
      <h1>Documents</h1>
      <UploadForm onUploaded={() => setRefreshKey((key) => key + 1)} />
      <DocumentList refreshKey={refreshKey} />
      <Link href="/chat">Go to chat</Link>
    </main>
  );
}
