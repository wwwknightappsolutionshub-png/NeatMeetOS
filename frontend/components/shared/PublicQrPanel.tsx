'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import QRCode from 'qrcode';
import { Button } from '@/components/ui/Button';

interface PublicQrPanelProps {
  url: string;
  filename: string;
  heading: string;
  printSubtitle?: string;
  brandName?: string | null;
  variant?: 'admin' | 'portal';
}

/** Shared QR panel: canvas render, PNG download, copy link, print. */
export function PublicQrPanel({
  url,
  filename,
  heading,
  printSubtitle = '',
  brandName,
  variant = 'admin',
}: PublicQrPanelProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !url) return;
    QRCode.toCanvas(canvas, url, {
      width: variant === 'portal' ? 160 : 220,
      margin: 2,
      color: { dark: '#18181b', light: '#ffffff' },
    }).catch((e) => setError(e instanceof Error ? e.message : 'QR failed'));
  }, [url, variant]);

  const downloadPng = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const anchor = document.createElement('a');
    anchor.href = canvas.toDataURL('image/png');
    anchor.download = filename;
    anchor.click();
  }, [filename]);

  const copyLink = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setError('Could not copy link');
    }
  }, [url]);

  const printQr = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const dataUrl = canvas.toDataURL('image/png');
    const title = brandName || heading;
    const subtitle = printSubtitle || heading;
    const win = window.open('', '_blank', 'noopener,noreferrer,width=480,height=640');
    if (!win) {
      setError('Pop-up blocked — allow pop-ups to print');
      return;
    }
    win.document.write(`<!DOCTYPE html><html><head><title>${heading} — ${title}</title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; text-align: center; padding: 40px 24px; color: #18181b; }
  h1 { font-size: 28px; margin: 0 0 8px; }
  p { color: #52525b; font-size: 14px; margin: 0 0 24px; }
  img { width: 280px; height: 280px; }
  .url { margin-top: 20px; font-family: ui-monospace, monospace; font-size: 11px; word-break: break-all; color: #71717a; }
  @media print { body { padding: 16px; } }
</style></head><body>
  <h1>${title}</h1>
  <p>${subtitle}</p>
  <img src="${dataUrl}" alt="QR code" />
  <p class="url">${url}</p>
  <script>window.onload = function () { window.print(); }</script>
</body></html>`);
    win.document.close();
  }, [brandName, heading, printSubtitle, url]);

  const shell =
    variant === 'portal'
      ? 'rounded-2xl border border-[var(--book-line)] bg-white p-5 shadow-[var(--book-shadow)]'
      : 'rounded-xl border border-zinc-200 bg-white p-4';

  return (
    <div className={shell}>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
        <div className="mx-auto shrink-0 rounded-lg border border-zinc-100 bg-white p-2 sm:mx-0">
          <canvas ref={canvasRef} aria-label={`${heading} QR code`} />
        </div>
        <div className="min-w-0 flex-1 space-y-3">
          <div>
            <p
              className={
                variant === 'portal'
                  ? 'text-sm font-semibold text-[var(--book-ink)]'
                  : 'text-sm font-semibold text-zinc-900'
              }
            >
              {heading}
            </p>
            <p
              className={
                variant === 'portal'
                  ? 'mt-1 break-all font-mono text-xs text-[var(--book-muted)]'
                  : 'mt-1 break-all font-mono text-xs text-zinc-500'
              }
            >
              {url || '…'}
            </p>
          </div>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" onClick={downloadPng}>
              Download PNG
            </Button>
            <Button type="button" variant="secondary" onClick={() => void copyLink()}>
              {copied ? 'Copied' : 'Copy link'}
            </Button>
            <Button type="button" variant="secondary" onClick={printQr}>
              Print
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
