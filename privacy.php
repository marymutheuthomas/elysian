<?php
// privacy.php — Privacy Policy
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-12 w-full">
  <div class="elysian-card p-8 md:p-10">
    <h1 class="text-2xl font-bold text-main font-display mb-1">Privacy Policy</h1>
    <p class="text-xs text-muted mb-6">Last updated: <?php echo date('F j, Y'); ?></p>

    <div class="mb-8 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 text-amber-800 dark:text-amber-300 text-xs font-semibold leading-relaxed">
      ⚠️ Draft placeholder. This page plainly describes what this application actually collects and
      does with your data today. It has not been reviewed by a lawyer and should not be treated as a
      final, binding privacy policy until it has been.
    </div>

    <div class="space-y-6">

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">1. Information We Collect</h2>
        <ul class="list-disc pl-5 space-y-1.5 text-sm text-main leading-relaxed">
          <li><strong>Account details:</strong> your name and email address, provided when you register.</li>
          <li><strong>Payment reference:</strong> the transaction/reference ID you submit to verify payment. We do not collect or store card numbers or banking credentials directly.</li>
          <li><strong>Assessment data:</strong> your answers, reflections, goals, and the personality/strategic profile generated from them.</li>
          <li><strong>Messages:</strong> any chat messages you exchange with your mentor inside the platform.</li>
        </ul>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">2. How We Use Your Information</h2>
        <p class="text-sm text-main leading-relaxed">
          We use this information to run the assessment, generate your strategic profile and goals,
          verify your payment, and let your mentor support you before and after you complete the program.
          We do not use your data for advertising, and we do not sell it to third parties.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">3. How Long We Keep It</h2>
        <p class="text-sm text-main leading-relaxed">
          Your answers are kept even if a mentor later removes the related course content from the
          program — this is so your own record of what you wrote stays intact and reviewable.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">4. Who Can See Your Information</h2>
        <p class="text-sm text-main leading-relaxed">
          Your assigned mentor can view your assessment answers, profile, and messages through the
          internal mentor dashboard, in order to support you. We do not currently use third-party
          analytics or advertising trackers on this platform.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">5. Cookies & Sessions</h2>
        <p class="text-sm text-main leading-relaxed">
          We use a session cookie solely to keep you logged in while you use the site. It is not used
          for tracking or advertising, and it expires when your session ends.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">6. Your Choices</h2>
        <p class="text-sm text-main leading-relaxed">
          To ask what data we hold about you, or to request corrections or deletion, contact us via
          the <a href="/support.php" class="text-[#3F00FF] font-semibold hover:underline">Support</a> page.
          Self-service account deletion is not currently available in the product.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">7. Security</h2>
        <p class="text-sm text-main leading-relaxed">
          We take reasonable measures to protect your data, but no online system can be guaranteed
          100% secure.
        </p>
      </section>

      <section>
        <h2 class="text-base font-bold text-main font-display mb-2">8. Changes to This Policy</h2>
        <p class="text-sm text-main leading-relaxed">
          If this policy changes, we'll update the "last updated" date above.
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
