'use client';

import Link from 'next/link';
import type { BookableService } from '@/lib/booking-types';
import { resolveMediaUrl } from '@/lib/media-url';

export function MemberServicesRail({
  services,
  bookHref,
}: {
  services: BookableService[];
  bookHref: string;
}) {
  if (services.length === 0) return null;

  return (
    <section className="rounded-3xl border border-[var(--book-line)] bg-white p-4 shadow-[var(--book-shadow)] sm:p-5">
      <div className="mb-3 flex items-end justify-between gap-2">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--book-moss)]">
            Services
          </p>
          <h2 className="book-display mt-0.5 text-xl font-bold text-[var(--book-ink)]">Book a look</h2>
        </div>
        <Link
          href={bookHref}
          className="shrink-0 text-xs font-semibold text-[var(--book-moss)] hover:underline"
        >
          See all
        </Link>
      </div>
      <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1 snap-x snap-mandatory">
        {services.map((service) => {
          const src = resolveMediaUrl(service.image_url);
          const initial = (service.name || '?').slice(0, 1).toUpperCase();
          return (
            <Link
              key={service.id}
              href={bookHref}
              className="snap-start w-[5.5rem] shrink-0 text-center"
            >
              <div className="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)] shadow-sm">
                {src ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={src} alt="" className="h-full w-full object-cover" />
                ) : (
                  <span className="text-lg font-bold text-[var(--book-moss)]">{initial}</span>
                )}
              </div>
              <p className="mt-1.5 line-clamp-2 text-[11px] font-semibold leading-tight text-[var(--book-ink)]">
                {service.name}
              </p>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
