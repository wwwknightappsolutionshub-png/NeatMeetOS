interface SocialFooterIconsProps {
  facebookUrl?: string | null;
  instagramUrl?: string | null;
  tiktokUrl?: string | null;
  className?: string;
}

function normalizeUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  const trimmed = url.trim();
  return trimmed.length > 0 ? trimmed : null;
}

const iconClass = 'h-5 w-5';

function FacebookIcon() {
  return (
    <svg className={iconClass} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.85c0-2.37 1.4-3.69 3.56-3.69 1.03 0 2.11.18 2.11.18v2.32h-1.19c-1.17 0-1.54.73-1.54 1.48v1.78h2.62l-.42 2.9h-2.2V22c4.78-.75 8.44-4.91 8.44-9.93z" />
    </svg>
  );
}

function InstagramIcon() {
  return (
    <svg className={iconClass} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5a4.25 4.25 0 0 0 4.25-4.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zm8.75 1.75a1 1 0 1 1 0 2 1 1 0 0 1 0-2zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z" />
    </svg>
  );
}

function TikTokIcon() {
  return (
    <svg className={iconClass} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.83 2.83 0 1 1-2-2.71V9.4a6.27 6.27 0 1 0 5.45 6.21V9.05a8.16 8.16 0 0 0 4.77 1.52V7.12a4.85 4.85 0 0 1-1-.43z" />
    </svg>
  );
}

export function SocialFooterIcons({
  facebookUrl,
  instagramUrl,
  tiktokUrl,
  className,
}: SocialFooterIconsProps) {
  const facebook = normalizeUrl(facebookUrl);
  const instagram = normalizeUrl(instagramUrl);
  const tiktok = normalizeUrl(tiktokUrl);

  if (!facebook && !instagram && !tiktok) return null;

  const linkClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-full text-[var(--book-muted,#57534e)] transition hover:bg-stone-100 hover:text-[#2f5a45]';

  return (
    <nav
      className={['flex items-center justify-center gap-2', className].filter(Boolean).join(' ')}
      aria-label="Social media"
    >
      {facebook ? (
        <a
          href={facebook}
          target="_blank"
          rel="noopener noreferrer"
          className={linkClass}
          aria-label="Facebook"
        >
          <FacebookIcon />
        </a>
      ) : null}
      {instagram ? (
        <a
          href={instagram}
          target="_blank"
          rel="noopener noreferrer"
          className={linkClass}
          aria-label="Instagram"
        >
          <InstagramIcon />
        </a>
      ) : null}
      {tiktok ? (
        <a
          href={tiktok}
          target="_blank"
          rel="noopener noreferrer"
          className={linkClass}
          aria-label="TikTok"
        >
          <TikTokIcon />
        </a>
      ) : null}
    </nav>
  );
}
