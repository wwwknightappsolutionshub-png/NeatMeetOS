'use client';

import { useCallback, useEffect, useState } from 'react';
import type { SalonReview } from '@/lib/review-types';
import { fetchPublicReviews, submitPublicReview } from '@/services/review.service';

function Stars({ rating }: { rating: number }) {
  return (
    <span className="tracking-tight text-amber-600" aria-label={`${rating} out of 5 stars`}>
      {'★'.repeat(Math.max(0, Math.min(5, rating)))}
      <span className="text-stone-300">{'★'.repeat(Math.max(0, 5 - rating))}</span>
    </span>
  );
}

export function BookingReviewsSection({ tenantSlug }: { tenantSlug: string }) {
  const [reviews, setReviews] = useState<SalonReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [authorName, setAuthorName] = useState('');
  const [rating, setRating] = useState(5);
  const [body, setBody] = useState('');
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setReviews(await fetchPublicReviews(tenantSlug));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load reviews');
    } finally {
      setLoading(false);
    }
  }, [tenantSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const created = await submitPublicReview(tenantSlug, {
        author_name: authorName.trim(),
        rating,
        body: body.trim(),
      });
      setReviews((prev) => [created, ...prev]);
      setAuthorName('');
      setBody('');
      setRating(5);
      setFormOpen(false);
      setNotice('Thanks — your review is live.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not submit review');
    } finally {
      setSaving(false);
    }
  }

  const loop = reviews.length > 1 ? [...reviews, ...reviews] : reviews;

  return (
    <section id="reviews" className="mt-10 scroll-mt-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="book-display text-2xl font-bold">Customer reviews</h2>
          <p className="mt-1 text-sm text-[var(--book-muted)]">
            Real feedback from guests who booked with us.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setFormOpen((v) => !v)}
          className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white"
        >
          {formOpen ? 'Close' : 'Add a review'}
        </button>
      </div>

      {notice ? (
        <p className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
          {notice}
        </p>
      ) : null}
      {error ? (
        <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
          {error}
        </p>
      ) : null}

      {formOpen ? (
        <form
          onSubmit={(e) => void onSubmit(e)}
          className="mt-4 grid gap-3 rounded-2xl border border-[var(--book-line)] bg-white p-4"
        >
          <label className="grid gap-1 text-sm">
            <span className="font-medium text-[var(--book-ink)]">Your name</span>
            <input
              required
              minLength={2}
              value={authorName}
              onChange={(e) => setAuthorName(e.target.value)}
              className="rounded-lg border border-[var(--book-line)] px-3 py-2"
            />
          </label>
          <label className="grid gap-1 text-sm">
            <span className="font-medium text-[var(--book-ink)]">Rating</span>
            <select
              value={rating}
              onChange={(e) => setRating(Number(e.target.value))}
              className="rounded-lg border border-[var(--book-line)] px-3 py-2"
            >
              {[5, 4, 3, 2, 1].map((n) => (
                <option key={n} value={n}>
                  {n} star{n === 1 ? '' : 's'}
                </option>
              ))}
            </select>
          </label>
          <label className="grid gap-1 text-sm">
            <span className="font-medium text-[var(--book-ink)]">Your review</span>
            <textarea
              required
              minLength={8}
              rows={3}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              className="rounded-lg border border-[var(--book-line)] px-3 py-2"
              placeholder="What did you love about your visit?"
            />
          </label>
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center justify-center rounded-md bg-[var(--book-moss)] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
          >
            {saving ? 'Submitting…' : 'Publish review'}
          </button>
        </form>
      ) : null}

      <div className="mt-5 overflow-hidden rounded-2xl border border-[var(--book-line)] bg-white">
        {loading && reviews.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-[var(--book-muted)]">Loading reviews…</p>
        ) : reviews.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-[var(--book-muted)]">
            Be the first to leave a review.
          </p>
        ) : (
          <div className="review-marquee py-4">
            <div
              className={`review-marquee-track ${reviews.length > 1 ? 'is-animated' : ''}`}
            >
              {loop.map((review, idx) => (
                <article
                  key={`${review.id}-${idx}`}
                  className="mx-2 w-[280px] shrink-0 rounded-xl border border-[var(--book-line)] bg-[var(--book-wash)] p-4"
                >
                  <Stars rating={review.rating} />
                  <p className="mt-2 text-sm leading-relaxed text-[var(--book-ink)]">{review.body}</p>
                  <p className="mt-3 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--book-muted)]">
                    {review.author_name}
                  </p>
                </article>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
