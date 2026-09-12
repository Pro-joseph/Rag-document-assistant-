const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export interface DocumentItem {
  id: number;
  filename: string;
  status: 'pending' | 'processing' | 'ready' | 'failed';
  created_at: string;
  updated_at: string;
}

export interface Source {
  content: string;
  filename: string;
  score: number | null;
}

export async function uploadDocument(file: File): Promise<{ data: DocumentItem }> {
  const formData = new FormData();
  formData.append('file', file);
  const res = await fetch(`${API_URL}/documents`, { method: 'POST', body: formData });
  if (!res.ok) throw new Error('Upload failed');
  return res.json();
}

export async function listDocuments(): Promise<{ data: DocumentItem[] }> {
  const res = await fetch(`${API_URL}/documents`);
  if (!res.ok) throw new Error('List failed');
  return res.json();
}

export async function deleteDocument(id: number): Promise<void> {
  const res = await fetch(`${API_URL}/documents/${id}`, { method: 'DELETE' });
  if (!res.ok) throw new Error('Delete failed');
}

export async function askQuestion(question: string): Promise<{ answer: string; sources: Source[] }> {
  const res = await fetch(`${API_URL}/query`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ question }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as { message?: string }).message ?? 'Query failed');
  }
  return res.json();
}
