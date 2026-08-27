<?php
// verify_email.php — confirms a student's email address via the token sent
// at registration. Verification is tracked only, not enforced: students can
// already use the app before clicking this link, so this just updates
// email_verified and (for convenience) logs the browser in as that student.
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$result = 'invalid'; // 'invalid' | 'already' | 'success'
$student_name = '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT `permanent_id`, `name`, `email_verified` FROM `students` WHERE `email_verify_token` = ?");
    $stmt->execute([$token]);
    $student = $stmt->fetch();

    if ($student) {
        $student_name = $student['name'];
        $pdo->prepare("UPDATE `students` SET `email_verified` = 1, `email_verify_token` = NULL WHERE `permanent_id` = ?")
            ->execute([$student['permanent_id']]);
        $_SESSION['student_id'] = $student['permanent_id'];
        $result = 'success';
    } else {
        // Token not found — either it never existed, or it was already used
        // (cleared on success above, so a second click of the same link
        // lands here too).
        $result = 'invalid';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex flex-col items-center justify-center relative w-full">
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl -z-10 animate-pulse"></div>
  <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl -z-10"></div>

  <div class="w-full max-w-md px-2 sm:px-0">
    <div class="elysian-card p-4 sm:p-6 lg:p-8 bg-white/80 glass shadow-xl rounded-3xl overflow-hidden transition-all duration-300 text-center">

      <?php if ($result === 'success'): ?>
        <div class="w-14 h-14 bg-emerald-50 border border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-slate-800 mb-1">Email Verified<?php echo $student_name ? ', ' . htmlspecialchars($student_name) . '!' : '!'; ?></h1>
        <p class="text-xs text-slate-400 mb-6">Thanks for confirming your email address.</p>
        <a href="/programs.php" class="group w-full inline-flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
          Continue →
        </a>
      <?php else: ?>
        <div class="w-14 h-14 bg-amber-50 border border-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-slate-800 mb-1">Link Invalid or Already Used</h1>
        <p class="text-xs text-slate-400 mb-6">
          This verification link isn't valid anymore — you may have already verified this email, or the link expired.
          Your account works either way; verification is optional.
        </p>
        <a href="/index.php" class="group w-full inline-flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
          Go to Login →
        </a>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
