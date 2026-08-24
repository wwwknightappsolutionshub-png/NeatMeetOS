export default function PlatformTermsPage() {
  return (
    <main className="mx-auto min-h-screen max-w-2xl px-4 py-12 text-zinc-800 sm:px-6">
      <p className="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">NeatMeet OS</p>
      <h1 className="mt-2 text-3xl font-bold tracking-tight">Terms &amp; Conditions</h1>
      <p className="mt-3 text-sm text-zinc-600">
        These platform terms apply when you join a salon&apos;s membership family through NeatMeet OS.
        Your salon may also have its own house rules; ask them if you need a copy.
      </p>

      <section className="mt-8 space-y-4 text-sm leading-relaxed text-zinc-700">
        <div>
          <h2 className="font-semibold text-zinc-900">1. Membership list</h2>
          <p className="mt-1">
            By joining, you ask the salon to store your preferred name, WhatsApp number, email, and
            optional special dates so they can recognise you, message you about visits, and offer
            membership or loyalty benefits.
          </p>
        </div>
        <div>
          <h2 className="font-semibold text-zinc-900">2. WhatsApp login codes</h2>
          <p className="mt-1">
            The membership app sends one-time login codes to the WhatsApp number you provide. Keep
            that number secure. Do not share OTP codes with anyone.
          </p>
        </div>
        <div>
          <h2 className="font-semibold text-zinc-900">3. Check-in and presence</h2>
          <p className="mt-1">
            When you clock in or out in the membership app, the salon can see that you are on site.
            Use check-in only when you are physically visiting.
          </p>
        </div>
        <div>
          <h2 className="font-semibold text-zinc-900">4. Marketing and contact</h2>
          <p className="mt-1">
            Joining allows the salon to contact you about appointments and membership offers. You can
            ask the salon to update or remove your details at any time.
          </p>
        </div>
        <div>
          <h2 className="font-semibold text-zinc-900">5. Platform role</h2>
          <p className="mt-1">
            NeatMeet OS provides software for salons. Your contract for services is with the salon you
            visit, not with NeatMeet OS personally, except for these platform terms covering how the
            membership tools work.
          </p>
        </div>
      </section>

      <p className="mt-10 text-xs text-zinc-500">Last updated: 24 August 2026</p>
    </main>
  );
}
