'use client';

import { useEffect, useState } from 'react';
import { deleteDocument, listDocuments, type DocumentItem } from '../lib/api';

export default function DocumentList({ refreshKey }: { refreshKey: number }): React.JSX.Element {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);

  async function refresh(): Promise<void> {
    const { data } = await listDocuments();
    setDocuments(data);
  }

  useEffect(() => {
    void refresh();
  }, [refreshKey]);

  return (
    <ul>
      {documents.map((doc) => (
        <li key={doc.id}>
          {doc.filename} — {doc.status}
          <button onClick={() => deleteDocument(doc.id).then(refresh)}>Delete</button>
        </li>
      ))}
    </ul>
  );
}
