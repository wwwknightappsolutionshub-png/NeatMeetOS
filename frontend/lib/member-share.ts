export type ShareTextResult = 'shared' | 'unsupported' | 'cancelled';

export async function shareTextLink(options: {
  title?: string;
  text: string;
  url?: string;
}): Promise<ShareTextResult> {
  if (typeof navigator.share !== 'function') return 'unsupported';
  try {
    await navigator.share({
      title: options.title,
      text: options.text,
      url: options.url,
    });
    return 'shared';
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return 'cancelled';
    return 'unsupported';
  }
}

export function openWhatsAppShare(text: string): void {
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener,noreferrer');
}

export function openFacebookShare(url: string): void {
  window.open(
    `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
    '_blank',
    'noopener,noreferrer',
  );
}

export function openSmsShare(text: string): void {
  window.location.href = `sms:?body=${encodeURIComponent(text)}`;
}

export function openEmailShare(subject: string, body: string): void {
  window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

export async function copyTextToClipboard(text: string): Promise<boolean> {
  if (typeof navigator === 'undefined') return false;
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}
