import Image from 'next/image';

type Props = {
  size?: number;
  withWordmark?: boolean;
  /** color = green mark; inverse/onDark = mark on white tile for dark chrome */
  variant?: 'color' | 'inverse' | 'onDark';
  className?: string;
  wordmarkClassName?: string;
};

const MARK_SRC = '/brand/neatmeet-mark.png';

/**
 * NeatMeet OS brand mark — shared across marketing, auth, admin, platform, and PWA.
 * Always uses the canonical chair + calendar mark asset.
 */
export function NeatMeetLogo({
  size = 32,
  withWordmark = false,
  variant = 'color',
  className = '',
  wordmarkClassName = '',
}: Props) {
  const onDark = variant !== 'color';
  const mark = onDark ? (
    <span
      className="inline-flex overflow-hidden rounded-[22%] bg-white"
      style={{ width: size, height: size }}
    >
      <Image
        src={MARK_SRC}
        alt="NeatMeet OS"
        width={size}
        height={size}
        className="object-contain p-[14%]"
        priority={size >= 40}
      />
    </span>
  ) : (
    <Image
      src={MARK_SRC}
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

/** @deprecated Prefer NeatMeetLogo — kept for rare SVG-only needs */
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
    <NeatMeetLogo
      size={size}
      variant={inverse ? 'onDark' : 'color'}
      className={className}
    />
  );
}
