'use client';

import { useEffect, useState } from 'react';

interface ToastProps {
  message: string | null;
  onDismiss: () => void;
  durationMs?: number;
}

export function Toast({ message, onDismiss, durationMs = 3200 }: ToastProps) {
  useEffect(() => {
    if (!message) return;
    const t = window.setTimeout(onDismiss, durationMs);
    return () => window.clearTimeout(t);
  }, [message, onDismiss, durationMs]);

  if (!message) return null;

  return (
    <div
      role="status"
      className="fixed bottom-6 left-1/2 z-50 max-w-sm -translate-x-1/2 rounded-xl border border-[#2f5a45]/30 bg-[#1c1917] px-4 py-3 text-sm font-medium text-white shadow-lg"
    >
      {message}
    </div>
  );
}

export function useToast() {
  const [message, setMessage] = useState<string | null>(null);

  return {
    message,
    showToast: (next: string) => setMessage(next),
    dismissToast: () => setMessage(null),
  };
}
