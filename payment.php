<?php
// payment.php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth guard
if (!isset($_SESSION['student_id'])) {
    header("Location: /index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student details
$stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: /logout.php");
    exit;
}

// Redirect if already verified or completed
if ($student['status'] === 'active') {
    header("Location: /tunnel.php");
    exit;
} elseif ($student['status'] === 'completed') {
    header("Location: /completed.php");
    exit;
}

$program_id = $student['selected_program_id'] ?? '';

// Ensure program is selected or fallback to default
$program = null;
if (!empty($program_id)) {
    $stmt_prog = $pdo->prepare("SELECT * FROM `programs` WHERE `id` = ?");
    $stmt_prog->execute([$program_id]);
    $program = $stmt_prog->fetch();
}

if (!$program) {
    $stmt_def = $pdo->query("SELECT * FROM `programs` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1");
    $program = $stmt_def->fetch();
    if (!$program) {
        $stmt_def = $pdo->query("SELECT * FROM `programs` ORDER BY `id` ASC LIMIT 1");
        $program = $stmt_def->fetch();
    }
    if ($program) {
        $program_id = $program['id'];
        $pdo->prepare("UPDATE `students` SET `selected_program_id` = ? WHERE `permanent_id` = ?")
            ->execute([$program_id, $student_id]);
    } else {
        $pdo->prepare("UPDATE `students` SET `status` = 'program_selection', `selected_program_id` = NULL WHERE `permanent_id` = ?")
            ->execute([$student_id]);
        header("Location: /programs.php");
        exit;
    }
}

// Check for existing payment record
$stmt_pay = $pdo->prepare("SELECT * FROM `payments` WHERE `student_permanent_id` = ? AND `program_id` = ? ORDER BY `submitted_at` DESC LIMIT 1");
$stmt_pay->execute([$student_id, $program_id]);
$payment = $stmt_pay->fetch();

$error_msg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$payment) {
    $ttid = isset($_POST['ttid']) ? trim($_POST['ttid']) : '';

    if (empty($ttid)) {
        $error_msg = 'Please enter your Transaction ID (TTID).';
    } else {
        try {
            $pdo->beginTransaction();

            $payment_id = 'PAY-' . time();
            $amount = $program['fee'];

            // Insert payment record
            $ins_pay = $pdo->prepare("INSERT INTO `payments` (`id`, `student_permanent_id`, `program_id`, `ttid`, `amount`, `status`) VALUES (?, ?, ?, ?, ?, 'pending')");
            $ins_pay->execute([$payment_id, $student_id, $program_id, $ttid, $amount]);

            // Update student record
            $upd_student = $pdo->prepare("UPDATE `students` SET `ttid` = ?, `status` = 'payment_pending' WHERE `permanent_id` = ?");
            $upd_student->execute([$ttid, $student_id]);

            $pdo->commit();

            // Refresh page to show pending status
            header("Location: /payment.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = 'Failed to submit verification. Please try again.';
        }
    }
}

// ── All redirects done. Safe to output HTML now ──
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex flex-col items-center justify-center relative w-full">
  <!-- Decorative blurs -->
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse"></div>

  <div class="w-full max-w-md">
    <!-- Card -->
    <div class="elysian-card p-8 bg-white/80 glass shadow-xl rounded-3xl">
      
      <?php if (!$payment): ?>
        <h1 class="text-2xl font-bold text-slate-800 text-center mb-1">Unlock Assessment</h1>
        <p class="text-xs text-slate-400 text-center mb-6">
          Enter your Transaction ID (TTID) to unlock validation.
        </p>

        <?php if (!empty($error_msg)): ?>
          <div class="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-600">
            <?php echo htmlspecialchars($error_msg); ?>
          </div>
        <?php endif; ?>

        <!-- Diagnostic Path Info -->
        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-6 space-y-3 font-medium">
          <div class="flex justify-between items-center text-xs">
            <span class="text-slate-400">Permanent ID (UIN)</span>
            <span class="font-mono text-slate-700 font-bold select-all">
              <?php echo htmlspecialchars($student['permanent_id']); ?>
            </span>
          </div>
          <div class="border-t border-slate-200/50 pt-2.5 flex justify-between items-center text-xs">
            <span class="text-slate-400">Selected Program</span>
            <span class="text-slate-700 font-bold max-w-[200px] truncate text-right">
              <?php echo htmlspecialchars($program['title']); ?>
            </span>
          </div>
          <div class="border-t border-slate-200/50 pt-2.5 flex justify-between items-center text-xs">
            <span class="text-slate-400">Strategic Fee</span>
            <span class="text-slate-800 font-extrabold text-sm">
              $<?php echo number_format($program['fee'], 2); ?>
            </span>
          </div>
        </div>

        <form method="POST" action="payment.php" class="space-y-4">
          <div class="flex flex-col gap-1.5">
            <label class="elysian-label">Transaction ID (TTID)</label>
            <input
              type="text"
              name="ttid"
              placeholder="Enter wire/payment reference number"
              class="elysian-input text-center font-mono tracking-wide focus:outline-none focus:ring-2 focus:ring-[#3F00FF] focus:border-[#3F00FF]"
              required
            />
          </div>

          <button type="submit" class="group w-full inline-flex items-center justify-center gap-2 mt-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
            Submit Verification
          </button>
        </form>
      <?php else: ?>
        <div class="text-center py-4 animate-fade-in">
          <div class="w-16 h-16 bg-amber-50 border border-amber-100 rounded-full flex items-center justify-center mx-auto mb-5 animate-pulse">
            <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>

          <h1 class="text-xl font-bold text-slate-800 mb-2">Verification In Progress</h1>
          <p class="text-xs text-slate-400 leading-relaxed mb-6">
            Your transaction (TTID: <span class="font-mono font-bold text-slate-600"><?php echo htmlspecialchars($payment['ttid']); ?></span>) is pending review by the Elysian team.
          </p>

          <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-left text-xs space-y-2 text-slate-600 mb-6">
            <p>• Verification typically takes less than 2 hours during active sessions.</p>
            <p>• Mentors can verify your payment instantly from the Mentor Dashboard.</p>
            <p>• Once verified, your status will update automatically and unlock your path.</p>
          </div>

          <div class="flex flex-col gap-2.5">
            <a
              href="/payment.php"
              class="w-full elysian-btn bg-slate-900 hover:bg-slate-800 text-white py-3 text-xs font-semibold flex items-center justify-center"
            >
              Refresh Verification Status
            </a>
            <a
              href="/logout.php"
              class="w-full elysian-btn elysian-btn-ghost py-3 text-xs font-semibold flex items-center justify-center"
            >
              Logout
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
