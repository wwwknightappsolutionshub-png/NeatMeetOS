import type { Metadata } from 'next';
import { Suspense } from 'react';
import { SalonGrowthAssessmentFlow } from '@/components/marketing/SalonGrowthAssessmentFlow';

export const metadata: Metadata = {
  title: 'Free Salon Growth Assessment — NeatMeet OS',
  description:
    'Discover how well your salon is retaining customers — and where you may be leaving repeat revenue behind. Free indicative assessment in 2–3 minutes.',
};

export default function AssessmentPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-[#f3f1ec] text-sm text-stone-500">
          Loading assessment…
        </div>
      }
    >
      <SalonGrowthAssessmentFlow />
    </Suspense>
  );
}
