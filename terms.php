<?php
// terms.php — Terms of Service
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-12 w-full">
  <div class="elysian-card p-8 md:p-10">
    <h1 class="text-2xl font-bold text-main font-display mb-1">Terms of Service</h1>
    <p class="text-xs text-muted mb-6">Last updated: <?php echo date('F j, Y'); ?></p>

    <div class="mb-8 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 text-amber-800 dark:text-amber-300 text-xs font-semibold leading-relaxed">
      ⚠️ Draft placeholder. This page describes how the platform actually works today. Items marked
      <strong>[TO BE DEFINED]</strong> are business/legal decisions that haven't been made yet and
      should be filled in — ideally with legal review — before this is published as binding.
    </div>

    <div class="space-y-6">

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">1. Acceptance of Terms</h2>
        <p class="text-sm text-main leading-relaxed">
          By registering for and using Elysian Success, you agree to these terms.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">2. The Service</h2>
        <p class="text-sm text-main leading-relaxed">
          Elysian Success is a coaching and self-assessment platform. You work through a guided
          assessment to receive a strategic personality profile and a set of suggested goals, and you
          may communicate with an assigned mentor before and after completing the program.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">3. Your Account & Student Code</h2>
        <p class="text-sm text-main leading-relaxed">
          Your Student Code is your only credential for accessing your account — there is no password.
          Keep it private. If it's lost, contact <a href="/support.php" class="text-[#3F00FF] font-semibold hover:underline">Support</a>
          for help recovering access.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">4. Payments</h2>
        <p class="text-sm text-main leading-relaxed">
          Program fees are shown at the time you select a program. Payment is verified manually using
          the transaction reference you submit. Refund policy: <strong>[TO BE DEFINED]</strong>.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">5. Acceptable Use</h2>
        <p class="text-sm text-main leading-relaxed">
          Use the platform honestly and don't attempt to access another student's account or data.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">6. Your Content & Our Output</h2>
        <p class="text-sm text-main leading-relaxed">
          Your answers, reflections, and goals belong to you. Ownership and usage rights over the
          generated profile/report: <strong>[TO BE DEFINED]</strong>.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">7. Not Professional Advice</h2>
        <p class="text-sm text-main leading-relaxed">
          The strategic profile, goals, and mentor conversations are coaching in nature and are not a
          substitute for professional medical, psychological, legal, or financial advice.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">8. Termination</h2>
        <p class="text-sm text-main leading-relaxed">
          We may suspend or terminate access for misuse of the platform. Terms for voluntary
          cancellation: <strong>[TO BE DEFINED]</strong>.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">9. Limitation of Liability</h2>
        <p class="text-sm text-main leading-relaxed">
          <strong>[TO BE DEFINED]</strong> — standard liability limitations should be drafted with
          legal counsel for your jurisdiction.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">10. Governing Law</h2>
        <p class="text-sm text-main leading-relaxed">
          <strong>[TO BE DEFINED]</strong>.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">11. Changes to These Terms</h2>
        <p class="text-sm text-main leading-relaxed">
          If these terms change, we'll update the "last updated" date above.
        </p>
      </section>

      <section class="pt-2 border-t border-subtle">
        <h2 class="text-base font-bold text-main font-display mb-2">Questions?</h2>
        <p class="text-sm text-main leading-relaxed">
          Reach out via <a href="/support.php" class="text-[#3F00FF] font-semibold hover:underline">Support</a>.
        </p>
      </section>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
