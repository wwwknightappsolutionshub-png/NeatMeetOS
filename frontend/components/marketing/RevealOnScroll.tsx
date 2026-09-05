'use client';

import { useEffect, useRef, useState, type ReactNode } from 'react';

type Props = {
  children: ReactNode;
  className?: string;
  /** Direction the content slides from */
  from?: 'up' | 'down' | 'left' | 'right';
  delayMs?: number;
};

/**
 * One-shot scroll reveal for landing sections.
 */
export function RevealOnScroll({
  children,
  className = '',
  from = 'up',
  delayMs = 0,
}: Props) {
  const ref = useRef<HTMLDivElement | null>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    if (typeof IntersectionObserver === 'undefined') {
      setVisible(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={ref}
      className={['nm-reveal', `nm-reveal-${from}`, visible ? 'nm-reveal-visible' : '', className]
        .filter(Boolean)
        .join(' ')}
      style={delayMs ? { transitionDelay: `${delayMs}ms` } : undefined}
    >
      {children}
    </div>
  );
}
