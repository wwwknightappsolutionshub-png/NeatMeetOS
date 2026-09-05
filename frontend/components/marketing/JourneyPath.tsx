'use client';

import { useEffect, useRef, useState } from 'react';

type JourneyStep = {
  title: string;
  body: string;
};

export function JourneyPath({ steps }: { steps: readonly JourneyStep[] }) {
  const ref = useRef<HTMLOListElement | null>(null);
  const [active, setActive] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    if (typeof IntersectionObserver === 'undefined') {
      setActive(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setActive(true);
          observer.disconnect();
        }
      },
      { threshold: 0.35 },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <ol ref={ref} className="relative mt-12">
      {/* Desktop / large progress line + traveling flash */}
      <div
        className="pointer-events-none absolute left-0 right-0 top-5 hidden h-px overflow-visible xl:block"
        aria-hidden
      >
        <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-[#2f5a45]/20 via-[#2f5a45]/50 to-[#2f5a45]/20" />
        <div
          className={[
            'nm-journey-flash absolute top-1/2 h-[3px] w-24 -translate-y-1/2 rounded-full',
            active ? 'nm-journey-flash-run' : '',
          ]
            .filter(Boolean)
            .join(' ')}
        />
        <div
          className={[
            'nm-journey-pulse absolute top-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#2f5a45]',
            active ? 'nm-journey-pulse-run' : '',
          ]
            .filter(Boolean)
            .join(' ')}
        />
      </div>
      <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 lg:gap-3">
        {steps.map((step, i) => (
          <li
            key={step.title}
            className={[
              'relative nm-journey-step',
              active ? 'nm-journey-step-visible' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            style={{ transitionDelay: active ? `${120 + i * 90}ms` : undefined }}
          >
            <div className="flex items-start gap-3 xl:flex-col xl:items-center xl:text-center">
              <span className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-[#2f5a45] bg-[#f3f1ec] font-mono text-xs font-semibold text-[#2f5a45] xl:mx-auto">
                {String(i + 1).padStart(2, '0')}
              </span>
              <div className="min-w-0 pt-0.5 xl:pt-3">
                <h3 className="text-base font-semibold text-stone-900 xl:text-sm">{step.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-stone-600 xl:text-xs">
                  {step.body}
                </p>
              </div>
            </div>
          </li>
        ))}
      </div>
    </ol>
  );
}
