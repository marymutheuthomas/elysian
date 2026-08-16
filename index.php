<?php
// index.php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_msg = '';

// Helper to ensure student has a valid program_id or fallback
function ensureStudentProgram($pdo, &$student) {
    if (!$student) return;
    $p_id = $student['selected_program_id'] ?? '';
    $has_valid = false;
    if (!empty($p_id)) {
        $chk = $pdo->prepare("SELECT `id` FROM `programs` WHERE `id` = ?");
        $chk->execute([$p_id]);
        if ($chk->fetch()) {
            $has_valid = true;
        }
    }
    if (!$has_valid) {
        $stmt_def = $pdo->query("SELECT `id` FROM `programs` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1");
        $def = $stmt_def->fetch();
        if (!$def) {
            $stmt_def = $pdo->query("SELECT `id` FROM `programs` ORDER BY `id` ASC LIMIT 1");
            $def = $stmt_def->fetch();
        }
        if ($def) {
            $pdo->prepare("UPDATE `students` SET `selected_program_id` = ? WHERE `permanent_id` = ?")
                ->execute([$def['id'], $student['permanent_id']]);
            $student['selected_program_id'] = $def['id'];
        } else {
            $pdo->prepare("UPDATE `students` SET `status` = 'program_selection', `selected_program_id` = NULL WHERE `permanent_id` = ?")
                ->execute([$student['permanent_id']]);
            $student['status'] = 'program_selection';
            $student['selected_program_id'] = null;
        }
    }
}

// If student is already logged in, redirect them based on their status
if (isset($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch();
    if ($student) {
        ensureStudentProgram($pdo, $student);
        if ($student['status'] === 'program_selection') {
            header("Location: /programs.php");
            exit;
        } elseif ($student['status'] === 'payment_pending') {
            header("Location: /payment.php");
            exit;
        } elseif ($student['status'] === 'active') {
            header("Location: /tunnel.php");
            exit;
        } elseif ($student['status'] === 'completed') {
            header("Location: /completed.php");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uin = isset($_POST['uin']) ? trim(strtoupper($_POST['uin'])) : '';

    if (empty($uin)) {
        $error_msg = 'Please enter your Student Code.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
        $stmt->execute([$uin]);
        $student = $stmt->fetch();

        if ($student) {
            $_SESSION['student_id'] = $student['permanent_id'];
            ensureStudentProgram($pdo, $student);
            
            if ($student['status'] === 'program_selection') {
                header("Location: /programs.php");
                exit;
            } elseif ($student['status'] === 'payment_pending') {
                header("Location: /payment.php");
                exit;
            } elseif ($student['status'] === 'active') {
                header("Location: /tunnel.php");
                exit;
            } elseif ($student['status'] === 'completed') {
                header("Location: /completed.php");
                exit;
            }
        } else {
            $error_msg = "We couldn't find that Student Code. Please register or check your spelling.";
        }
    }
}

// ── All redirects done. Safe to output HTML now ──
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex flex-col items-center justify-center relative w-full">
  <!-- Decorative Blur Elements -->
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl -z-10 animate-pulse"></div>
  <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl -z-10"></div>

  <div class="w-full max-w-md px-2 sm:px-0">
    <!-- Card -->
    <div class="elysian-card p-6 sm:p-8 bg-white/80 glass shadow-xl rounded-3xl">
      <div class="flex justify-center mb-4">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full border border-emerald-200">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Session Active
        </span>
      </div>

      <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 text-center mb-1">Ready to Level Up?</h1>
      <p class="text-xs text-slate-400 text-center mb-6">
        Enter your Access Code to jump back into your session.
      </p>

      <?php if (!empty($error_msg)): ?>
        <div class="mb-5 p-3.5 bg-red-50 border border-red-100 rounded-xl text-xs font-medium text-red-600 flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <?php echo htmlspecialchars($error_msg); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php" class="space-y-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[#3F00FF] uppercase tracking-wider">Your Access Code</label>
          <input
            type="text"
            name="uin"
            placeholder="e.g., ES-BOLD-EAGLE-42"
            class="elysian-input text-center font-mono tracking-widest uppercase focus:outline-none focus:ring-2 focus:ring-[#3F00FF] focus:border-[#3F00FF]"
            required
          />
        </div>

        <button type="submit" class="group w-full inline-flex items-center justify-center gap-2 mt-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
          Jump Back In 🚀
          <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </form>

      <div class="mt-4 text-center text-xs text-slate-400">
        Lost your code? <a href="/support.php" class="text-[#3F00FF] font-semibold hover:underline">Get help</a>
      </div>

      <div class="mt-4 pt-5 border-t border-slate-100 text-center text-xs">
        <span class="text-slate-400">New here? </span>
        <a href="/register.php" class="text-[#3F00FF] font-bold hover:underline">
          Get your Access Pass →
        </a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
