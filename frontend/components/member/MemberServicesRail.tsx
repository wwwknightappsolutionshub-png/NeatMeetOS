'use client';

import Link from 'next/link';
import type { BookableService } from '@/lib/booking-types';
import { bookServiceHref, resolveServiceImageSrc } from '@/lib/booking-media';

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
      <div className="-mx-1 flex gap-3 overflow-x-auto px-1 pb-1 snap-x snap-mandatory [scrollbar-width:thin]">
        {services.map((service) => {
          const src = resolveServiceImageSrc(service);
          return (
            <Link
              key={service.id}
              href={bookServiceHref(bookHref, service.id)}
              className="group snap-start w-[7.25rem] shrink-0 sm:w-[7.75rem]"
            >
              <div className="relative mx-auto h-32 w-full overflow-hidden rounded-2xl border border-[var(--book-line)] bg-[var(--book-wash)] shadow-sm transition group-hover:border-[var(--book-moss)] sm:h-36">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={src}
                  alt=""
                  className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                />
                <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/30 to-transparent" />
              </div>
              <p className="mt-2 line-clamp-2 text-center text-[11px] font-semibold leading-tight text-[var(--book-ink)]">
                {service.name}
              </p>
            </Link>
          );
        })}
      </div>
    </section>
  );
}
