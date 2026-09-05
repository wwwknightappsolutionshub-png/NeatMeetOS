'use client';

import { useEffect, useRef, useState } from 'react';

type Category = {
  title: string;
  items: string[];
};

export function CapabilitySwitcher({
  categories,
}: {
  categories: Category[];
}) {
  const [active, setActive] = useState(0);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);
  const scrollerRef = useRef<HTMLDivElement | null>(null);
  const current = categories[active] ?? categories[0];

  const updateScrollHints = () => {
    const el = scrollerRef.current;
    if (!el) return;
    const max = el.scrollWidth - el.clientWidth;
    setCanScrollLeft(el.scrollLeft > 4);
    setCanScrollRight(max - el.scrollLeft > 4);
  };

  useEffect(() => {
    updateScrollHints();
    const el = scrollerRef.current;
    if (!el) return;

    const onScroll = () => updateScrollHints();
    el.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', updateScrollHints);

    const ro =
      typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(() => updateScrollHints())
        : null;
    ro?.observe(el);

    return () => {
      el.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', updateScrollHints);
      ro?.disconnect();
    };
  }, [categories.length]);

  useEffect(() => {
    const scroller = scrollerRef.current;
    if (!scroller) return;
    const tab = scroller.querySelector<HTMLElement>(`#cap-tab-${active}`);
    tab?.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
  }, [active]);

  const scrollByAmount = (dir: -1 | 1) => {
    const el = scrollerRef.current;
    if (!el) return;
    el.scrollBy({ left: dir * Math.max(160, el.clientWidth * 0.6), behavior: 'smooth' });
  };

  return (
    <div className="mt-12 overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-sm">
      <div className="relative border-b border-stone-100 bg-[#f8f7f4]">
        {canScrollLeft ? (
          <button
            type="button"
            aria-label="Scroll tabs left"
            className="absolute left-1 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-stone-700 shadow ring-1 ring-stone-200 sm:hidden"
            onClick={() => scrollByAmount(-1)}
          >
            ‹
          </button>
        ) : null}
        {canScrollRight ? (
          <button
            type="button"
            aria-label="Scroll tabs right"
            className="absolute right-1 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-stone-700 shadow ring-1 ring-stone-200 sm:hidden"
            onClick={() => scrollByAmount(1)}
          >
            ›
          </button>
        ) : null}

        <div
          ref={scrollerRef}
          className="flex snap-x snap-mandatory gap-1 overflow-x-auto overscroll-x-contain px-2 py-2 [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:justify-center sm:overflow-visible sm:snap-none [&::-webkit-scrollbar]:hidden"
          role="tablist"
          aria-label="Platform capability categories"
        >
          {categories.map((cat, i) => {
            const selected = i === active;
            return (
              <button
                key={cat.title}
                type="button"
                role="tab"
                aria-selected={selected}
                id={`cap-tab-${i}`}
                aria-controls={`cap-panel-${i}`}
                className={[
                  'shrink-0 snap-start rounded-lg px-3 py-2.5 text-center text-sm font-medium whitespace-nowrap transition',
                  selected
                    ? 'bg-[#2f5a45] text-white shadow-sm'
                    : 'text-stone-600 hover:bg-white hover:text-stone-900',
                ].join(' ')}
                onClick={() => setActive(i)}
              >
                {cat.title}
              </button>
            );
          })}
        </div>

        {canScrollRight ? (
          <div
            className="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-[#f8f7f4] to-transparent sm:hidden"
            aria-hidden
          />
        ) : null}
        {canScrollLeft ? (
          <div
            className="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-[#f8f7f4] to-transparent sm:hidden"
            aria-hidden
          />
        ) : null}
      </div>

      <div
        id={`cap-panel-${active}`}
        role="tabpanel"
        aria-labelledby={`cap-tab-${active}`}
        className="grid gap-8 p-6 sm:grid-cols-[0.9fr_1.1fr] sm:p-8"
      >
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
            Outcome
          </p>
          <h3 className="mt-2 text-2xl font-semibold tracking-tight text-stone-900">
            {current.title}
          </h3>
          <p className="mt-3 text-sm leading-relaxed text-stone-600">
            Capabilities that support this outcome — selected from the live NeatMeet platform.
          </p>
        </div>
        <ul className="columns-1 gap-x-8 sm:columns-2">
          {current.items.map((item) => (
            <li
              key={item}
              className="mb-3 break-inside-avoid border-l-2 border-[#2f5a45]/35 pl-3 text-sm font-medium text-stone-800"
            >
              {item}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
