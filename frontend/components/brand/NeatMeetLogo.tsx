import Image from 'next/image';

type Props = {
  size?: number;
  withWordmark?: boolean;
  /** color = green mark; inverse = white mark for dark backgrounds */
  variant?: 'color' | 'inverse' | 'onDark';
  className?: string;
  wordmarkClassName?: string;
};

/**
 * NeatMeet OS brand mark — shared across marketing, auth, admin, and platform.
 */
export function NeatMeetLogo({
  size = 32,
  withWordmark = false,
  variant = 'color',
  className = '',
  wordmarkClassName = '',
}: Props) {
  const useRaster = variant === 'color' && size >= 48;
  const mark = useRaster ? (
    <Image
      src="/brand/neatmeet-mark.png"
      alt="NeatMeet OS"
      width={size}
      height={size}
      className="rounded-lg object-cover"
      priority={size >= 48}
    />
  ) : (
    <NeatMeetMarkSvg size={size} inverse={variant !== 'color'} />
  );

  if (!withWordmark) {
    return (
      <span className={`inline-flex shrink-0 ${className}`} style={{ width: size, height: size }}>
        {mark}
      </span>
    );
  }

  const textTone =
    variant === 'color'
      ? 'text-stone-900'
      : 'text-white';

  return (
    <span className={`inline-flex items-center gap-2.5 ${className}`}>
      <span className="inline-flex shrink-0" style={{ width: size, height: size }}>
        {mark}
      </span>
      <span
        className={`text-sm font-semibold tracking-tight ${textTone} ${wordmarkClassName}`}
      >
        NeatMeet OS
      </span>
    </span>
  );
}

/** Crisp vector fallback / inverse mark for dark UI chrome */
export function NeatMeetMarkSvg({
  size = 32,
  inverse = false,
  className = '',
}: {
  size?: number;
  inverse?: boolean;
  className?: string;
}) {
  const bg = inverse ? '#ffffff' : '#2f5a45';
  const fg = inverse ? '#2f5a45' : '#ffffff';

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden={!className}
      role="img"
    >
      <title>NeatMeet OS</title>
      <rect width="64" height="64" rx="14" fill={bg} />
      <path
        d="M18 46V18h7.2l11.6 18.2V18H44v28h-7.2L25.2 27.8V46H18z"
        fill={fg}
      />
      <path
        d="M18 46c6.5-2.8 13.8-4.2 22-4.2 1.4 0 2.8.1 4.2.2"
        stroke={fg}
        strokeWidth="2.4"
        strokeLinecap="round"
        opacity="0.45"
      />
    </svg>
  );
}
