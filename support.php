<?php
// support.php — Help & Contact
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-12 w-full">
  <div class="elysian-card p-8 md:p-10">
    <h1 class="text-2xl font-bold text-main font-display mb-1">Support</h1>
    <p class="text-sm text-muted mb-8">We're here to help — find quick answers below, or reach out directly.</p>

    <div class="space-y-8">

      <section>
        <h2 class="text-base font-bold text-main font-display mb-3">Frequently Asked Questions</h2>
        <div class="space-y-4">

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-subtle">
            <p class="text-sm font-bold text-main mb-1">I lost my Student Code — how do I log back in?</p>
            <p class="text-sm text-muted leading-relaxed">Your Student Code is the only way to access your account, and there's no automatic recovery.
              Email us at the address below with your full name and the email you registered with, and we'll help verify your identity.</p>
          </div>

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-subtle">
            <p class="text-sm font-bold text-main mb-1">I submitted my payment reference — how long does verification take?</p>
            <p class="text-sm text-muted leading-relaxed">Payment references are verified manually. This is typically quick, but if it's been more
              than a couple of business days, contact us and we'll look into it.</p>
          </div>

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-subtle">
            <p class="text-sm font-bold text-main mb-1">How does the assessment work?</p>
            <p class="text-sm text-muted leading-relaxed">Once your payment is verified, you'll work through a series of pillars made up of
              questions, reflections, and short exercises. Your answers shape a personalized strategic profile and a set of SMART goals at the end.</p>
          </div>

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-subtle">
            <p class="text-sm font-bold text-main mb-1">I'm already logged in — can I message my mentor directly?</p>
            <p class="text-sm text-muted leading-relaxed">Yes — while working through the assessment or after completing it, use the chat icon
              on your dashboard to message your mentor directly.</p>
          </div>

          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-subtle">
            <p class="text-sm font-bold text-main mb-1">Something on the site looks broken — what do I do?</p>
            <p class="text-sm text-muted leading-relaxed">Email us a short description of what happened, which page you were on, and (if possible)
              a screenshot. That helps us fix it quickly.</p>
          </div>

        </div>
      </section>

      <section class="pt-2 border-t border-subtle">
        <h2 class="text-base font-bold text-main font-display mb-2">Still need help?</h2>
        <p class="text-sm text-muted leading-relaxed mb-4">
          Reach our support team directly — we typically respond within one business day.
        </p>
        <a href="mailto:support@elysiansuccess.com" class="inline-flex items-center gap-2 elysian-btn elysian-btn-brand px-5 py-2.5 text-xs font-bold">
          ✉️ support@elysiansuccess.com
        </a>
        <!-- TODO: replace with your real, monitored support inbox before launch -->
      </section>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
