export async function fileFromImageUrl(url: string, filename?: string): Promise<File> {
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error('Could not load image for sharing');
  }
  const blob = await res.blob();
  const type = blob.type || 'image/jpeg';
  return new File([blob], filename || `my-look-${Date.now()}.jpg`, { type });
}

export function saveFileToDevice(file: File, filename?: string): void {
  if (typeof window === 'undefined') return;
  const url = URL.createObjectURL(file);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename || file.name || `my-look-${Date.now()}.jpg`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export type ShareImageResult = 'shared' | 'unsupported' | 'cancelled';

export async function shareImageFile(
  file: File,
  options: { title?: string; text?: string },
): Promise<ShareImageResult> {
  if (typeof navigator.share !== 'function') return 'unsupported';
  const shareData: ShareData = {
    title: options.title,
    text: options.text,
    files: [file],
  };
  if (typeof navigator.canShare === 'function' && !navigator.canShare(shareData)) {
    return 'unsupported';
  }
  try {
    await navigator.share(shareData);
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

export function openInstagramProfile(url: string | null | undefined): void {
  if (!url?.trim()) return;
  window.open(url.trim(), '_blank', 'noopener,noreferrer');
}
