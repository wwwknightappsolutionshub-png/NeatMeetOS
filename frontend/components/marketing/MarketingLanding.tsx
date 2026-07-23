'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useRef, useState } from 'react';
import { NeatMeetLogo } from '@/components/brand/NeatMeetLogo';
import { ExitIntentTrialModal } from '@/components/marketing/ExitIntentTrialModal';
import { resolveReferralCode } from '@/lib/referral-cookie';

const HERO =
  'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1800&q=75';

const SESSION_KEY = 'nm_exit_intent_shown';

const PAIN_POINTS = [
  {
    title: 'Too many apps',
    body: 'Booking in one place. Clients in another. Money in a third. Hard to keep up.',
  },
  {
    title: 'Missed payments',
    body: 'A client books, then does not pay the deposit. You find out too late.',
  },
  {
    title: 'Empty chairs',
    body: 'Someone cancels or does not come. The chair sits empty. You lose money.',
  },
] as const;

const START_TODAY = [
  'No Credit Card Required',
  'Ready in under 10 Minutes',
  'Import your clients',
  'Cancel Anytime',
] as const;

const PROOF_STATS = [
  { value: '250+', label: 'Onboarding' },
  { value: '< 10 min', label: 'Average setup' },
  { value: '30 days', label: 'Free trial' },
  { value: '1 system', label: 'For the whole salon' },
] as const;

const INSTEAD_OF = [
  'Checking multiple business apps',
  'Chasing deposits',
  'Updating spreadsheets',
  'Texting reminders manually',
] as const;

const YOU_SIMPLY = [
  'Accept Bookings',
  'Take payments',
  'Sell Your Products',
  'Schedule Automated Reminders',
  'Track Inventory',
] as const;

const NAV = [
  { href: '#how-it-works', label: 'How it works' },
  { href: '#product', label: 'Product' },
  { href: '#trial', label: 'Trial' },
] as const;

const CAPABILITIES: Array<{
  title: string;
  body: string;
  group: string;
}> = [
  {
    group: 'Front of house',
    title: 'Booking & scheduling',
    body: 'Clients book online. You see the calendar. Less double-booking.',
  },
  {
    group: 'Front of house',
    title: 'Client CRM',
    body: 'All client notes, photos, and history stay in one place.',
  },
  {
    group: 'Front of house',
    title: 'Next Visit',
    body: 'Book the next visit before the client leaves the salon.',
  },
  {
    group: 'Commerce',
    title: 'POS & checkout',
    body: 'Take payment at the till. Linked to the same booking and client.',
  },
  {
    group: 'Commerce',
    title: 'Memberships & loyalty',
    body: 'Sell plans, packages, and points so clients come back.',
  },
  {
    group: 'Commerce',
    title: 'Payments',
    body: 'See who paid, who owes a deposit, and what is still open.',
  },
  {
    group: 'Operations',
    title: 'Inventory',
    body: 'Track products in stock without a separate spreadsheet.',
  },
  {
    group: 'Operations',
    title: 'Staff & rota',
    body: 'See who works when. Manage time off in the same system.',
  },
  {
    group: 'Operations',
    title: 'Integrations',
    body: 'Connect email, SMS, and payments you already use.',
  },
  {
    group: 'Growth',
    title: 'Marketing automation',
    body: 'Send reminders and offers automatically while you work.',
  },
  {
    group: 'Growth',
    title: 'Gallery & Lookbook',
    body: 'Show your best work so clients book the right style.',
  },
  {
    group: 'Growth',
    title: 'Notifications & analytics',
    body: 'See bookings, money, and return visits in simple reports.',
  },
];

const GROUPS = ['Front of house', 'Commerce', 'Operations', 'Growth'] as const;

export function MarketingLanding() {
  const searchParams = useSearchParams();
  const [modalOpen, setModalOpen] = useState(false);
  const [modalSource, setModalSource] = useState<'exit' | 'cta'>('cta');
  const [refCode, setRefCode] = useState<string | null>(null);
  const [stickyVisible, setStickyVisible] = useState(false);
  const [navSolid, setNavSolid] = useState(false);
  const [mobileNav, setMobileNav] = useState(false);
  const modalOpenRef = useRef(false);
  modalOpenRef.current = modalOpen;

  useEffect(() => {
    setRefCode(resolveReferralCode(searchParams.get('ref')));
  }, [searchParams]);

  const openTrial = useCallback((source: 'exit' | 'cta') => {
    setModalSource(source);
    setModalOpen(true);
    setMobileNav(false);
    if (source === 'exit' && typeof sessionStorage !== 'undefined') {
      sessionStorage.setItem(SESSION_KEY, '1');
    }
  }, []);

  useEffect(() => {
    let armed = false;
    let fired = false;
    let scrolledPastHero = false;
    const armTimer = window.setTimeout(() => {
      armed = true;
    }, 1800);

    const tryShowExit = () => {
      if (!armed || fired || modalOpenRef.current) return;
      if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(SESSION_KEY)) {
        return;
      }
      fired = true;
      openTrial('exit');
    };

    /** Desktop: mouse leaves the top of the page (toward tabs / close). */
    const onDocMouseLeave = (e: MouseEvent) => {
      if (e.clientY > 12) return;
      tryShowExit();
    };

    /** Fallback for browsers that fire mouseout instead of mouseleave. */
    const onMouseOut = (e: MouseEvent) => {
      const next = e.relatedTarget as Node | null;
      if (next && document.documentElement.contains(next)) return;
      if (e.clientY > 12) return;
      tryShowExit();
    };

    /** Mobile / tab switch: leaving after reading past the hero. */
    const onVisibility = () => {
      if (document.visibilityState !== 'hidden') return;
      if (!scrolledPastHero) return;
      tryShowExit();
    };

    const onScroll = () => {
      setStickyVisible(window.scrollY > window.innerHeight * 0.45);
      setNavSolid(window.scrollY > 40);
      if (window.scrollY > window.innerHeight * 0.35) {
        scrolledPastHero = true;
      }
    };

    /** Mobile back-ish: history pop while on landing. */
    const onPageHide = () => {
      if (!scrolledPastHero) return;
      tryShowExit();
    };

    document.documentElement.addEventListener('mouseleave', onDocMouseLeave);
    document.addEventListener('mouseout', onMouseOut);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('pagehide', onPageHide);
    onScroll();

    return () => {
      window.clearTimeout(armTimer);
      document.documentElement.removeEventListener('mouseleave', onDocMouseLeave);
      document.removeEventListener('mouseout', onMouseOut);
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('pagehide', onPageHide);
    };
  }, [openTrial]);

  return (
    <div className="min-h-screen bg-[#f3f1ec] text-stone-900">
      <header
        className={[
          'fixed inset-x-0 top-0 z-40 transition-colors duration-300',
          navSolid
            ? 'border-b border-stone-200/80 bg-[#f3f1ec]/95 backdrop-blur-md'
            : 'bg-transparent',
        ].join(' ')}
      >
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
          <a href="#top" className="shrink-0">
            <NeatMeetLogo
              size={36}
              withWordmark
              variant={navSolid ? 'color' : 'onDark'}
              wordmarkClassName={navSolid ? 'text-stone-900' : 'text-white'}
            />
          </a>

          <nav className="hidden items-center gap-1 md:flex">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                className={[
                  'rounded-lg px-3 py-2 text-sm font-medium transition',
                  navSolid
                    ? 'text-stone-600 hover:bg-stone-200/60 hover:text-stone-900'
                    : 'text-white/85 hover:bg-white/10 hover:text-white',
                ].join(' ')}
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className="flex items-center gap-2">
            <Link
              href="/login"
              className={[
                'hidden rounded-lg px-3 py-2 text-sm font-semibold sm:inline-flex',
                navSolid
                  ? 'text-[#2f5a45] hover:bg-stone-200/70'
                  : 'text-white hover:bg-white/10',
              ].join(' ')}
            >
              Sign in
            </Link>
            <button
              type="button"
              onClick={() => openTrial('cta')}
              className="rounded-lg bg-[#2f5a45] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#264a39]"
            >
              Start trial
            </button>
            <button
              type="button"
              className={[
                'inline-flex h-9 w-9 items-center justify-center rounded-lg md:hidden',
                navSolid ? 'text-stone-800' : 'text-white',
              ].join(' ')}
              aria-label="Open menu"
              onClick={() => setMobileNav((v) => !v)}
            >
              <span className="text-lg leading-none">{mobileNav ? '✕' : '☰'}</span>
            </button>
          </div>
        </div>

        {mobileNav ? (
          <div className="border-t border-stone-200/60 bg-[#f3f1ec] px-5 py-3 md:hidden">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                onClick={() => setMobileNav(false)}
                className="block rounded-lg px-2 py-2.5 text-sm font-medium text-stone-700"
              >
                {item.label}
              </a>
            ))}
            <Link
              href="/login"
              className="mt-1 block rounded-lg px-2 py-2.5 text-sm font-semibold text-[#2f5a45]"
              onClick={() => setMobileNav(false)}
            >
              Sign in
            </Link>
          </div>
        ) : null}
      </header>

      {/* Hero — centered, pronounced */}
      <section id="top" className="relative isolate min-h-[100svh] overflow-hidden">
        <Image
          src={HERO}
          alt="Salon floor ready for the next client"
          fill
          priority
          sizes="100vw"
          className="object-cover"
        />
        <div className="absolute inset-0 bg-stone-950/72" />
        <div className="absolute inset-0 bg-gradient-to-b from-stone-950/55 via-stone-950/45 to-stone-950/80" />

        <div className="relative z-10 flex min-h-[100svh] flex-col items-center justify-center px-5 pb-20 pt-28 text-center sm:px-10">
          <NeatMeetLogo size={56} variant="color" className="mb-6 shadow-lg shadow-black/30" />
          <p className="text-xs font-semibold tracking-[0.22em] text-white/80 uppercase sm:text-sm">
            NeatMeet OS
          </p>
          <h1 className="mt-4 max-w-4xl text-[2.35rem] font-semibold leading-[1.05] tracking-tight text-white sm:text-5xl md:text-6xl lg:text-[4.25rem]">
            <span className="nm-hero-line block">
              One System That Simplifies Your Beauty Business
            </span>
          </h1>
          <p className="nm-hero-line nm-hero-line-delay-2 mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg">
            Built for salons, barbershops, spas, and beauty studios — one place for
            bookings, clients, payments, memberships, and reminders.
          </p>
          <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
            <button
              type="button"
              onClick={() => openTrial('cta')}
              className="rounded-lg bg-[#2f5a45] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#264a39]"
            >
              Start 30-day free trial
            </button>
          </div>
          {refCode ? (
            <p className="mt-6 text-sm text-white/75">
              You&apos;re joining via a salon referral — they earn a reward when you activate.
            </p>
          ) : null}
        </div>
      </section>

      <section id="how-it-works" className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              The problem
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Too Many Apps. Too Many Mistakes
            </h2>
            <p className="mx-auto mt-4 text-base leading-relaxed text-stone-600">
              You do not need more apps. You need one simple system for bookings, clients,
              and money — so nothing gets lost.
            </p>
          </div>

          <ul className="mt-12 grid gap-4 sm:grid-cols-3">
            {PAIN_POINTS.map((item, index) => (
              <li
                key={item.title}
                className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"
              >
                <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#2f5a45]/10 text-sm font-bold text-[#2f5a45]">
                  {index + 1}
                </span>
                <h3 className="mt-4 text-lg font-semibold text-stone-900">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-stone-600">{item.body}</p>
              </li>
            ))}
          </ul>

          <div className="mt-10 rounded-2xl border border-[#2f5a45]/20 bg-[#2f5a45] px-6 py-7 text-center sm:px-10">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/65">
              The fix
            </p>
            <p className="mx-auto mt-3 max-w-3xl text-2xl font-semibold leading-snug text-white sm:text-3xl">
              NeatMeet OS puts your calendar, clients, till, and follow-up in one place.
            </p>
            <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/75">
              Less stress. Fewer mistakes. More time for your clients.
            </p>
            <a
              href="#product"
              className="mt-6 inline-flex rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-[#2f5a45] transition hover:bg-[#f3f1ec]"
            >
              See what is included
            </a>
          </div>
        </div>
      </section>

      <section
        aria-label="Proof of service"
        className="border-b border-stone-200/70 bg-white px-5 py-10 sm:px-8"
      >
        <div className="mx-auto max-w-6xl">
          <p className="text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
            Proof of service
          </p>
          <dl className="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4 sm:gap-4">
            {PROOF_STATS.map((stat) => (
              <div key={stat.label} className="text-center">
                <dt className="text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">
                  {stat.value}
                </dt>
                <dd className="mt-1 text-sm text-stone-600">{stat.label}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      <section id="product" className="border-y border-stone-200/80 bg-[#ebe8e1] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Platform
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Everything that runs the salon
            </h2>
            <p className="mt-3 text-base text-stone-600">
              One system for the whole salon — from first booking to next visit.
            </p>
          </div>

          <div className="mt-14 space-y-10">
            {GROUPS.map((group) => {
              const items = CAPABILITIES.filter((c) => c.group === group);
              return (
                <div key={group}>
                  <div className="mb-4 flex items-center gap-3">
                    <span className="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2f5a45]">
                      {group}
                    </span>
                    <span className="h-px flex-1 bg-stone-300/80" />
                  </div>
                  <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, index) => (
                      <li
                        key={item.title}
                        className="group relative overflow-hidden rounded-xl border border-stone-200/90 bg-[#f3f1ec] p-5 transition hover:border-[#2f5a45]/35 hover:bg-white"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <h3 className="text-base font-semibold text-stone-900">
                            {item.title}
                          </h3>
                          <span className="font-mono text-[11px] font-semibold text-stone-400">
                            {String(index + 1).padStart(2, '0')}
                          </span>
                        </div>
                        <p className="mt-2 text-sm leading-relaxed text-stone-600">
                          {item.body}
                        </p>
                        <span className="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 origin-left scale-x-0 bg-[#2f5a45] transition group-hover:scale-x-100" />
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section id="imagine-your-day" className="border-b border-stone-200/70 bg-[#f3f1ec] px-5 py-16 sm:px-8 sm:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#2f5a45]">
              Day in the life
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
              Imagine Your Day
            </h2>
            <p className="mt-3 text-base text-stone-600">
              Swap the chaos of juggling tools for one calm, mobile-friendly workspace.
            </p>
          </div>

          <div className="mt-12 grid gap-6 lg:grid-cols-2">
            <div className="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
              <h3 className="text-lg font-semibold text-stone-900">Instead of:</h3>
              <ul className="mt-5 space-y-3">
                {INSTEAD_OF.map((item) => (
                  <li key={item} className="flex gap-3 text-sm leading-relaxed text-stone-600">
                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-stone-400" aria-hidden />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </div>

            <div className="rounded-2xl border border-[#2f5a45]/25 bg-[#2f5a45] p-6 sm:p-8">
              <h3 className="text-lg font-semibold text-white">You Simply:</h3>
              <ul className="mt-5 space-y-3">
                {YOU_SIMPLY.map((item) => (
                  <li key={item} className="flex gap-3 text-sm leading-relaxed text-white/90">
                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-white/80" aria-hidden />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-6 border-t border-white/15 pt-5 text-sm leading-relaxed text-white/80">
                All from a simple mobile friendly place.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section id="trial" className="relative overflow-hidden bg-[#2f5a45] px-5 py-16 sm:px-8 sm:py-20">
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.12]"
          style={{
            backgroundImage:
              'radial-gradient(circle at 12% 20%, #fff 0.6px, transparent 0.7px), radial-gradient(circle at 80% 70%, #fff 0.6px, transparent 0.7px)',
            backgroundSize: '28px 28px',
          }}
          aria-hidden
        />
        <div className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl" aria-hidden />
        <div className="pointer-events-none absolute -bottom-28 -left-16 h-80 w-80 rounded-full bg-black/20 blur-3xl" aria-hidden />

        <div className="relative mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:gap-14">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/90">
              <NeatMeetLogo size={18} variant="onDark" />
              Basic trial · 30 days
            </div>
            <h2 className="mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-[2.75rem] lg:leading-[1.1]">
              30 days free.
              <span className="mt-1 block text-white/75">Workspace ready in minutes.</span>
            </h2>
            <p className="mt-4 max-w-xl text-base leading-relaxed text-white/80">
              No card to start. Claim your trial, open your inbox, sign in, and finish
              Creating Your Workspace — your Basic trial begins when the salon is provisioned.
            </p>
            <div className="mt-8 flex flex-wrap items-center gap-3">
              <button
                type="button"
                onClick={() => openTrial('cta')}
                className="rounded-lg bg-white px-6 py-3.5 text-sm font-semibold text-[#2f5a45] transition hover:bg-[#f3f1ec]"
              >
                Start 30-day free trial
              </button>
              <a
                href="#product"
                className="rounded-lg border border-white/30 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Review the platform
              </a>
            </div>
            <dl className="mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-white/15 pt-6">
              {[
                { label: 'Setup', value: '< 10 min' },
                { label: 'Card', value: 'Not required' },
                { label: 'Plan', value: 'Basic trial' },
              ].map((stat) => (
                <div key={stat.label}>
                  <dt className="text-[10px] font-semibold uppercase tracking-[0.14em] text-white/55">
                    {stat.label}
                  </dt>
                  <dd className="mt-1 text-sm font-semibold text-white">{stat.value}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div className="rounded-2xl border border-white/15 bg-[#264a39]/80 p-6 shadow-xl backdrop-blur-sm sm:p-7">
            <h3 className="text-xl font-semibold tracking-tight text-white">
              START TODAY
            </h3>
            <ul className="mt-5 space-y-3">
              {START_TODAY.map((item) => (
                <li key={item} className="flex gap-3 text-sm leading-relaxed text-white/90">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-white/80" aria-hidden />
                  <span>{item}</span>
                </li>
              ))}
            </ul>
            <p className="mt-6 border-t border-white/15 pt-4 text-[11px] leading-relaxed text-white/55">
              Already have login details?{' '}
              <Link href="/login" className="font-semibold text-white underline-offset-2 hover:underline">
                Sign in
              </Link>
            </p>
          </div>
        </div>
      </section>

      <footer className="border-t border-stone-200 px-5 py-10 text-sm text-stone-500 sm:px-8">
        <div className="mx-auto flex max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <NeatMeetLogo size={28} withWordmark variant="color" wordmarkClassName="text-stone-700" />
          <div className="flex flex-wrap gap-4">
            {NAV.map((item) => (
              <a key={item.href} href={item.href} className="hover:text-stone-800">
                {item.label}
              </a>
            ))}
            <Link href="/login" className="font-medium text-[#2f5a45]">
              Sign in
            </Link>
          </div>
        </div>
      </footer>

      {stickyVisible && !modalOpen ? (
        <div className="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-[#f3f1ec]/95 p-3 backdrop-blur sm:hidden">
          <button
            type="button"
            onClick={() => openTrial('cta')}
            className="w-full rounded-lg bg-[#2f5a45] py-3 text-sm font-semibold text-white"
          >
            Start 30-day free trial
          </button>
        </div>
      ) : null}

      <ExitIntentTrialModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        referralCode={refCode}
        source={modalSource}
      />
    </div>
  );
}
