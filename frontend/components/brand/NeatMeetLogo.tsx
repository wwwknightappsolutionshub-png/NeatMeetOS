import Image from 'next/image';

type Props = {
  size?: number;
  withWordmark?: boolean;
  /** color = green mark on light UI; inverse/onDark = white mark on dark UI */
  variant?: 'color' | 'inverse' | 'onDark';
  className?: string;
  wordmarkClassName?: string;
};

const MARK_COLOR = '/brand/neatmeet-mark.png';
const MARK_WHITE = '/brand/neatmeet-mark-white.png';

/**
 * NeatMeet OS brand mark — shared across marketing, auth, admin, platform, and PWA.
 * Green mark on light backgrounds; white mark on dark backgrounds.
 */
export function NeatMeetLogo({
  size = 32,
  withWordmark = false,
  variant = 'color',
  className = '',
  wordmarkClassName = '',
}: Props) {
  const onDark = variant !== 'color';
  const mark = (
    <Image
      src={onDark ? MARK_WHITE : MARK_COLOR}
      alt="NeatMeet OS"
      width={size}
      height={size}
      className="object-contain"
      priority={size >= 40}
    />
  );

  if (!withWordmark) {
    return (
      <span className={`inline-flex shrink-0 ${className}`} style={{ width: size, height: size }}>
        {mark}
      </span>
    );
  }

  const textTone = onDark ? 'text-white' : 'text-stone-900';

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

/** @deprecated Prefer NeatMeetLogo */
export function NeatMeetMarkSvg({
  size = 32,
  inverse = false,
  className = '',
}: {
  size?: number;
  inverse?: boolean;
  className?: string;
}) {
  return (
    <NeatMeetLogo size={size} variant={inverse ? 'onDark' : 'color'} className={className} />
  );
}
