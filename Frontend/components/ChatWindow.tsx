'use client';

import { useState } from 'react';
import { askQuestion, type Source } from '../lib/api';

interface Message {
  role: 'user' | 'assistant';
  content: string;
  sources?: Source[];
}

function truncate(content: string, length = 500): string {
  return content.length > length ? `${content.slice(0, length)}…` : content;
}

export default function ChatWindow(): React.JSX.Element {
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: React.FormEvent): Promise<void> {
    event.preventDefault();
    const question = input.trim();
    if (question === '' || loading) return;

    setMessages((prev) => [...prev, { role: 'user', content: question }]);
    setInput('');
    setLoading(true);
    setError(null);

    try {
      const { answer, sources } = await askQuestion(question);
      setMessages((prev) => [...prev, { role: 'assistant', content: answer, sources }]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Query failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 overflow-y-auto">
        {messages.map((message, index) => (
          <div key={index} className={message.role === 'user' ? 'self-end bg-blue-100' : 'self-start bg-gray-100'}>
            <p>{message.content}</p>
            {message.role === 'assistant' && message.sources !== undefined && message.sources.length > 0 && (
              <ul className="mt-2 border-t pt-2 text-sm">
                {message.sources.map((source, sourceIndex) => (
                  <li key={sourceIndex}>
                    [{sourceIndex + 1}] {source.filename} — {truncate(source.content)}
                    {source.score !== null && ` (${source.score.toFixed(2)})`}
                  </li>
                ))}
              </ul>
            )}
            {message.role === 'assistant' && (message.sources === undefined || message.sources.length === 0) && (
              <p className="mt-2 text-sm italic">No relevant information found in the documents.</p>
            )}
          </div>
        ))}
      </div>

      {error !== null && <p role="alert" className="text-red-600">{error}</p>}

      <form onSubmit={onSubmit} className="flex gap-2">
        <input
          value={input}
          onChange={(event) => setInput(event.target.value)}
          placeholder="Ask about your documents…"
          disabled={loading}
          className="flex-1 border p-2"
        />
        <button type="submit" disabled={loading || input.trim() === ''}>
          {loading ? 'Sending…' : 'Send'}
        </button>
      </form>
    </div>
  );
}
