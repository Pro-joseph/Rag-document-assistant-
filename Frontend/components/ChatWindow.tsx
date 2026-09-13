'use client';

import { useEffect, useRef, useState } from 'react';
import { askQuestion, type Source } from '../lib/api';

interface Message {
  role: 'user' | 'assistant';
  content: string;
  sources?: Source[];
}

const SUGGESTIONS = ['What are my documents about?', 'Summarize the key points', 'What numbers are mentioned?'];

function truncate(content: string, length = 280): string {
  return content.length > length ? `${content.slice(0, length)}…` : content;
}

function TypingDots(): React.JSX.Element {
  return (
    <span className="flex items-center gap-1 py-1" aria-label="Assistant is typing">
      {[0, 1, 2].map((dot) => (
        <span
          key={dot}
          className="h-2 w-2 animate-bounceDot rounded-full bg-slate-400"
          style={{ animationDelay: `${dot * 0.15}s` }}
        />
      ))}
    </span>
  );
}

function SourceCard({ source, index }: { source: Source; index: number }): React.JSX.Element {
  const [expanded, setExpanded] = useState(false);
  const body = expanded ? source.content : truncate(source.content);

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="truncate text-xs font-semibold text-slate-900">
          [{index + 1}] {source.filename}
        </p>
        {source.score !== null && (
          <span className="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700">
            {(source.score * 100).toFixed(0)}%
          </span>
        )}
      </div>
      <p className="answer-body mt-1 text-xs text-slate-600">{body}</p>
      {source.content.length > 280 && (
        <button
          onClick={() => setExpanded((value) => !value)}
          className="mt-1 text-xs font-semibold text-brand-600 hover:text-brand-700"
        >
          {expanded ? 'Show less' : 'Expand'}
        </button>
      )}
    </div>
  );
}

export default function ChatWindow(): React.JSX.Element {
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages, loading]);

  async function send(question: string): Promise<void> {
    const trimmed = question.trim();
    if (trimmed === '' || loading) return;

    setMessages((prev) => [...prev, { role: 'user', content: trimmed }]);
    setInput('');
    setLoading(true);
    setError(null);

    try {
      const { answer, sources } = await askQuestion(trimmed);
      setMessages((prev) => [...prev, { role: 'assistant', content: answer, sources }]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Query failed');
    } finally {
      setLoading(false);
    }
  }

  async function onSubmit(event: React.FormEvent): Promise<void> {
    event.preventDefault();
    await send(input);
  }

  function onKeyDown(event: React.KeyboardEvent<HTMLTextAreaElement>): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      void send(input);
    }
  }

  return (
    <div className="flex h-[calc(100vh-14rem)] min-h-[420px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card">
      <div className="thread-scroll flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
        {messages.length === 0 && !loading && (
          <div className="flex h-full flex-col items-center justify-center gap-3 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white shadow-card">
              ✦
            </span>
            <p className="text-lg font-semibold text-slate-900">Ask anything about your files</p>
            <p className="max-w-sm text-sm text-slate-500">
              Every answer cites the exact document it came from. Try a suggestion below.
            </p>
            <div className="flex flex-wrap justify-center gap-2">
              {SUGGESTIONS.map((suggestion) => (
                <button
                  key={suggestion}
                  onClick={() => send(suggestion)}
                  className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700"
                >
                  {suggestion}
                </button>
              ))}
            </div>
          </div>
        )}

        {messages.map((message, index) =>
          message.role === 'user' ? (
            <div key={index} className="flex justify-end gap-2">
              <div className="max-w-[80%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-2.5 text-sm text-white shadow-card">
                <p className="answer-body">{message.content}</p>
              </div>
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                You
              </span>
            </div>
          ) : (
            <div key={index} className="flex justify-start gap-2">
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
                AI
              </span>
              <div className="max-w-[85%] rounded-2xl rounded-bl-md border border-slate-200 bg-slate-50 px-4 py-3 shadow-card">
                <p className="answer-body text-sm text-slate-900">{message.content}</p>
                {message.sources !== undefined && message.sources.length > 0 && (
                  <div className="mt-3 space-y-2 border-t border-slate-200 pt-3">
                    <p className="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                      Sources · {message.sources.length}
                    </p>
                    {message.sources.map((source, sourceIndex) => (
                      <SourceCard key={sourceIndex} source={source} index={sourceIndex} />
                    ))}
                  </div>
                )}
                {(message.sources === undefined || message.sources.length === 0) && (
                  <p className="mt-2 text-xs italic text-slate-500">
                    No relevant information found in the documents.
                  </p>
                )}
              </div>
            </div>
          ),
        )}

        {loading && (
          <div className="flex justify-start gap-2">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
              AI
            </span>
            <div className="rounded-2xl rounded-bl-md border border-slate-200 bg-slate-50 px-4 py-3">
              <TypingDots />
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      <div className="border-t border-slate-200 bg-white p-3 sm:p-4">
        {error !== null && (
          <div role="alert" className="mb-2 flex items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
            <span>{error}</span>
            <button onClick={() => setError(null)} className="font-semibold underline">
              Dismiss
            </button>
          </div>
        )}
        <form onSubmit={onSubmit} className="flex items-end gap-2">
          <textarea
            value={input}
            onChange={(event) => setInput(event.target.value)}
            onKeyDown={onKeyDown}
            placeholder="Ask about your documents… (Enter to send)"
            disabled={loading}
            rows={1}
            className="max-h-32 flex-1 resize-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-card placeholder:text-slate-400 focus:border-brand-500 disabled:opacity-60"
          />
          <button
            type="submit"
            disabled={loading || input.trim() === ''}
            className="shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {loading ? '…' : 'Send'}
          </button>
        </form>
      </div>
    </div>
  );
}
