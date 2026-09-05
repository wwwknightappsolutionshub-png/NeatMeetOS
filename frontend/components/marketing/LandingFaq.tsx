'use client';

import { useId, useState } from 'react';
import { trackMarketingEvent } from '@/lib/marketing-events';

const FAQS: Array<{ q: string; a: string }> = [
  {
    q: 'Is NeatMeet OS just a booking system?',
    a: 'No. Booking is one part of NeatMeet OS. The platform also brings customer management, payments, POS, loyalty, memberships, marketing and business performance intelligence into one system built to help you grow repeat business.',
  },
  {
    q: 'I already use booking software. Why would I need NeatMeet?',
    a: 'If your current system already handles appointments, NeatMeet’s value is helping you understand and grow the customer relationship — including customer visibility, retention, loyalty, follow-up and business performance. You can import existing customers from a spreadsheet during setup.',
  },
  {
    q: 'Can I try NeatMeet before paying?',
    a: 'Yes. NeatMeet offers a 30-day free trial, subject to current signup terms. Your Basic trial begins when your salon workspace is provisioned.',
  },
  {
    q: 'Do I need a credit card to start?',
    a: 'No card is required to start the trial signup flow. Claim your trial, open your inbox, sign in, and finish Creating Your Workspace.',
  },
  {
    q: 'How long does setup take?',
    a: 'Many salons complete Creating Your Workspace in under 10 minutes. Exact time depends on how many services, staff and clients you add.',
  },
  {
    q: 'Can I import my existing customers?',
    a: 'Yes. NeatMeet includes a client import flow so you can bring existing customer records into the CRM from a file during onboarding or later from admin.',
  },
  {
    q: 'Does NeatMeet work for barbers?',
    a: 'Yes. NeatMeet OS is built for hair salons, barbershops, beauty studios, spas and multi-location beauty businesses.',
  },
  {
    q: 'Does NeatMeet support loyalty?',
    a: 'Yes. Depending on your plan, you can run memberships, packages and loyalty rewards so returning customers are recognised and encouraged.',
  },
  {
    q: 'Can NeatMeet help bring customers back?',
    a: 'Yes. Tools such as next-visit booking, reminders, marketing automation, loyalty, memberships and customer history are designed to encourage the next appointment — not only the first one.',
  },
  {
    q: 'What is the Salon Growth Assessment?',
    a: 'It is a free short diagnostic that gives an indicative view of customer visibility, retention, re-engagement and potential repeat-revenue opportunity based on the answers you provide.',
  },
  {
    q: 'Is the revenue opportunity figure guaranteed?',
    a: 'No. It is an estimate based on the information you provide and is intended to highlight potential opportunity — not guaranteed lost revenue or accounting results.',
  },
  {
    q: 'Is my business and customer data secure?',
    a: 'NeatMeet OS is a multi-tenant platform with authentication, tenant isolation and role-based access controls. Customer and salon data is kept within the appropriate tenant boundaries. See the platform Terms for how the service is provided.',
  },
  {
    q: 'Can I cancel?',
    a: 'Yes. You can cancel according to your subscription terms. Trial and paid plans are designed so you are not locked into a surprise long-term commitment on the public signup path.',
  },
  {
    q: 'Do you provide onboarding support?',
    a: 'Yes. Platform onboarding is available as part of getting your salon live. Reach out through your account or the contacts you receive during signup if you need help finishing setup.',
  },
];

export function LandingFaq() {
  const baseId = useId();
  const [open, setOpen] = useState<number | null>(0);

  return (
    <div className="mx-auto max-w-3xl divide-y divide-stone-200 rounded-2xl border border-stone-200/90 bg-white">
      {FAQS.map((item, index) => {
        const panelId = `${baseId}-panel-${index}`;
        const buttonId = `${baseId}-button-${index}`;
        const isOpen = open === index;
        return (
          <div key={item.q}>
            <h3>
              <button
                type="button"
                id={buttonId}
                aria-expanded={isOpen}
                aria-controls={panelId}
                className="flex w-full items-start justify-between gap-4 px-5 py-4 text-left text-sm font-semibold text-stone-900 hover:bg-stone-50 sm:px-6 sm:text-base"
                onClick={() => {
                  const next = isOpen ? null : index;
                  setOpen(next);
                  if (next !== null) {
                    trackMarketingEvent('faq_opened', { question: item.q.slice(0, 80) });
                  }
                }}
              >
                <span>{item.q}</span>
                <span className="mt-0.5 shrink-0 text-lg leading-none text-[#2f5a45]" aria-hidden>
                  {isOpen ? '−' : '+'}
                </span>
              </button>
            </h3>
            <div
              id={panelId}
              role="region"
              aria-labelledby={buttonId}
              hidden={!isOpen}
              className="px-5 pb-5 text-sm leading-relaxed text-stone-600 sm:px-6"
            >
              {item.a}
            </div>
          </div>
        );
      })}
    </div>
  );
}
