'use client';

import { useCallback, useEffect, useState } from 'react';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { EmptyState, ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { SalonReview } from '@/lib/review-types';
import {
  deleteAdminReview,
  fetchAdminReviews,
  updateAdminReview,
} from '@/services/review.service';

export default function BookingReviewsAdminPage() {
  const [reviews, setReviews] = useState<SalonReview[]>([]);
  const [editing, setEditing] = useState<SalonReview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    fetchAdminReviews()
      .then(setReviews)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load reviews'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function saveEdit(event: React.FormEvent) {
    event.preventDefault();
    if (!editing) return;
    setSaving(true);
    setError(null);
    try {
      await updateAdminReview(editing.id, {
        author_name: editing.author_name,
        rating: editing.rating,
        body: editing.body,
        is_published: editing.is_published,
      });
      setEditing(null);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <AdminBookingShell title="Reviews">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title="Customer reviews">
      {error ? <ErrorAlert message={error} /> : null}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="All reviews">
          {reviews.length === 0 ? <EmptyState message="No reviews yet." /> : null}
          <ul className="divide-y divide-zinc-100">
            {reviews.map((review) => (
              <li key={review.id} className="flex items-start justify-between gap-3 py-3 text-sm">
                <div>
                  <p className="font-medium">
                    {review.author_name}{' '}
                    <span className="text-amber-600">{'★'.repeat(review.rating)}</span>
                    {!review.is_published ? (
                      <span className="ml-2 text-xs text-zinc-400">Hidden</span>
                    ) : null}
                  </p>
                  <p className="mt-1 text-zinc-600">{review.body}</p>
                </div>
                <div className="flex shrink-0 gap-2">
                  <Button type="button" variant="secondary" onClick={() => setEditing(review)}>
                    Edit
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={async () => {
                      await deleteAdminReview(review.id);
                      load();
                    }}
                  >
                    Delete
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        </Card>

        {editing ? (
          <Card title="Edit review">
            <form onSubmit={(e) => void saveEdit(e)} className="grid gap-3">
              <Field label="Author">
                <input
                  className={inputClass}
                  value={editing.author_name}
                  onChange={(e) => setEditing({ ...editing, author_name: e.target.value })}
                  required
                />
              </Field>
              <Field label="Rating">
                <select
                  className={inputClass}
                  value={editing.rating}
                  onChange={(e) =>
                    setEditing({ ...editing, rating: Number(e.target.value) })
                  }
                >
                  {[5, 4, 3, 2, 1].map((n) => (
                    <option key={n} value={n}>
                      {n}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Body">
                <textarea
                  className={inputClass}
                  rows={4}
                  value={editing.body}
                  onChange={(e) => setEditing({ ...editing, body: e.target.value })}
                  required
                />
              </Field>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={editing.is_published}
                  onChange={(e) =>
                    setEditing({ ...editing, is_published: e.target.checked })
                  }
                />
                Published on booking page
              </label>
              <div className="flex gap-2">
                <Button type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save'}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setEditing(null)}>
                  Cancel
                </Button>
              </div>
            </form>
          </Card>
        ) : null}
      </div>
    </AdminBookingShell>
  );
}
