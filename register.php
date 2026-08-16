<?php
// register.php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_msg = '';
$generated_uin = null;

// Student Code Generator — student's actual name + a random 4-digit PIN,
// e.g. "JORDAN-4821". Personal and easy to remember, no format to decode.
function generateStudentCode(PDO $pdo, string $name): string {
    $slug = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
    if ($slug === '') {
        $slug = 'STUDENT';
    }
    $slug = substr($slug, 0, 12);

    $checkStmt = $pdo->prepare("SELECT 1 FROM `students` WHERE `permanent_id` = ?");
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $code = "{$slug}-{$pin}";
        $checkStmt->execute([$code]);
        if (!$checkStmt->fetchColumn()) {
            return $code;
        }
    }

    // Fallback in the vanishingly unlikely case all 10,000 PINs collide.
    return $slug . '-' . substr((string) time(), -4);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($name) || empty($email)) {
        $error_msg = 'Please fill out both Name and Email fields.';
    } else {
        try {
            $uin = generateStudentCode($pdo, $name);
            $stmt = $pdo->prepare("INSERT INTO `students` (`permanent_id`, `name`, `email`, `status`, `answers`) VALUES (?, ?, ?, 'program_selection', '{}')");
            $stmt->execute([$uin, $name, $email]);

            // Save UIN to session and display the registration success screen
            $_SESSION['student_id'] = $uin;
            $generated_uin = $uin;
        } catch (PDOException $e) {
            $error_msg = 'Failed to register student. Email or ID might already exist.';
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
    <div class="elysian-card p-4 sm:p-6 lg:p-8 bg-white/80 glass shadow-xl rounded-3xl overflow-hidden transition-all duration-300">

      <?php if (!$generated_uin): ?>
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-800 text-center mb-1 leading-tight">Unlock Your Growth Journey ✨</h1>
        <p class="text-xs sm:text-sm lg:text-base text-slate-400 text-center mb-6 leading-relaxed">
          Join Elysian today to claim your personal Access Pass and start leveling up.
        </p>

        <?php if (!empty($error_msg)): ?>
          <div class="mb-4 p-3 bg-red-50 text-red-600 text-xs rounded-xl border border-red-100">
            <?php echo htmlspecialchars($error_msg); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-4">
          <div class="flex flex-col gap-1.5">
            <label class="elysian-label">Full Name</label>
            <input
              type="text"
              name="name"
              placeholder="Enter your full name"
              class="elysian-input focus:outline-none focus:ring-2 focus:ring-[#3F00FF] focus:border-[#3F00FF]"
              required
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="elysian-label">Email Address</label>
            <input
              type="email"
              name="email"
              placeholder="name@example.com"
              class="elysian-input focus:outline-none focus:ring-2 focus:ring-[#3F00FF] focus:border-[#3F00FF]"
              required
            />
          </div>

          <button type="submit" class="group w-full inline-flex items-center justify-center gap-2 mt-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
            Get My Pass & Start 🚀
            <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs">
          <span class="text-slate-400">Have a code already? </span>
          <a href="/index.php" class="text-[#3F00FF] font-bold hover:underline">
            Jump back in →
          </a>
        </div>
      <?php else: ?>
        <div class="animate-fade-in text-center">
          <div class="w-14 h-14 bg-emerald-50 border border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>

          <h1 class="text-xl font-bold text-slate-800 mb-1">Registration Complete</h1>
          <p class="text-xs text-slate-400 mb-6">
            Your Student Code is ready. Save it — you'll need it to log in again.
          </p>

          <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-6 relative">
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
              Your Student Code
            </span>
            <span id="uin-text" class="text-base font-mono font-bold text-slate-800 tracking-wider select-all">
              <?php echo htmlspecialchars($generated_uin); ?>
            </span>

            <button
              onclick="copyUIN()"
              class="absolute right-3 top-1/2 -translate-y-1/2 p-2 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-600 transition-colors"
              title="Copy Student Code"
            >
              <span id="copy-status">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-2 4h.01M9 16h5m-5-4h5"
                  />
                </svg>
              </span>
            </button>
          </div>

          <a
            href="/programs.php"
            class="group w-full inline-flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-white bg-[#3F00FF] hover:bg-[#2E00B3] shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
          >
            Proceed to Path Selection
            <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function copyUIN() {
  const uin = document.getElementById('uin-text').innerText;
  navigator.clipboard.writeText(uin).then(() => {
    const status = document.getElementById('copy-status');
    status.innerHTML = '<span class="text-[10px] font-bold text-emerald-600">Copied!</span>';
    setTimeout(() => {
      status.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-2 4h.01M9 16h5m-5-4h5" />
        </svg>
      `;
    }, 2000);
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
