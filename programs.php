<?php
// programs.php
require_once __DIR__ . '/config/db.php';

// Auth guard — must run BEFORE header.php outputs any HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['student_id'])) {
    header("Location: /index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch current student details
$stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: /logout.php");
    exit;
}

// If student is already active or completed, check program validity before redirecting
if ($student['status'] === 'active' || $student['status'] === 'completed') {
    $p_id = $student['selected_program_id'] ?? '';
    $has_prog = false;
    if (!empty($p_id)) {
        $chk_p = $pdo->prepare("SELECT `id` FROM `programs` WHERE `id` = ?");
        $chk_p->execute([$p_id]);
        if ($chk_p->fetch()) {
            $has_prog = true;
        }
    }
    if (!$has_prog) {
        // Try auto-resolution
        $stmt_def = $pdo->query("SELECT `id` FROM `programs` WHERE `is_active` = 1 ORDER BY `id` ASC LIMIT 1");
        $def = $stmt_def->fetch();
        if ($def) {
            $pdo->prepare("UPDATE `students` SET `selected_program_id` = ? WHERE `permanent_id` = ?")
                ->execute([$def['id'], $student_id]);
            $student['selected_program_id'] = $def['id'];
            $has_prog = true;
        }
    }
    if ($has_prog) {
        $dest = ($student['status'] === 'completed') ? '/completed.php' : '/tunnel.php';
        header("Location: " . $dest);
        exit;
    }
}

// Handle program selection POST — must happen before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = isset($_POST['program_id']) ? trim($_POST['program_id']) : '';

    if (!empty($program_id)) {
        // Verify program exists and is active
        $chk = $pdo->prepare("SELECT `id` FROM `programs` WHERE `id` = ? AND `is_active` = 1");
        $chk->execute([$program_id]);
        if ($chk->fetch()) {
            // Update student status and selected program
            $update = $pdo->prepare("UPDATE `students` SET `selected_program_id` = ?, `status` = 'payment_pending' WHERE `permanent_id` = ?");
            $update->execute([$program_id, $student_id]);

            header("Location: /payment.php");
            exit;
        }
    }
}

// Fetch all active programs
$stmt_prog = $pdo->query("SELECT * FROM `programs` WHERE `is_active` = 1");
$programs = $stmt_prog->fetchAll();

// Convert programs array to a JSON format for dynamic JS preview
$programs_json = json_encode($programs);

// ── All redirects done. Safe to output HTML now ──
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[85vh] py-8 px-4 relative w-full flex flex-col items-center">
  <!-- Decorative blurs -->
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse"></div>

  <!-- Header Bar -->
  <div class="max-w-4xl w-full flex items-center justify-between mb-12 border-b border-slate-200/50 pb-6">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center shadow-lg">
        <span class="text-white font-extrabold text-base">E</span>
      </div>
      <div>
        <h2 class="font-extrabold text-base tracking-wide uppercase text-slate-800">
          Elysian Accelerator
        </h2>
        <p class="text-[10px] text-slate-400 font-semibold font-mono uppercase">
          UIN: <?php echo htmlspecialchars($student['permanent_id']); ?>
        </p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg">
        Student: <?php echo htmlspecialchars($student['name']); ?>
      </span>
      <a
        href="/logout.php"
        class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100/50 hover:bg-slate-100 rounded-xl transition-all border border-slate-200/40"
      >
        Logout
      </a>
    </div>
  </div>

  <!-- Main content -->
  <div class="max-w-2xl w-full">
    <div class="text-center mb-10">
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/40 mb-3">Diagnostic Selection</span>
      <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight mb-2">
        Select Your Transformation Path
      </h1>
      <p class="text-sm text-slate-400 max-w-lg mx-auto leading-relaxed">
        Choose from our specialized program tracks. Select a program from the dropdown below to preview details and proceed.
      </p>
    </div>

    <?php if (count($programs) === 0): ?>
      <div class="text-center py-12 text-slate-400 text-sm elysian-card bg-white p-8">
        No active programs available at the moment. Please contact support.
      </div>
    <?php else: ?>
      <form method="POST" action="programs.php" class="space-y-6">
        <!-- Dropdown UI Selection -->
        <div class="elysian-card p-6 bg-white border border-slate-100 shadow-md">
          <label class="elysian-label block mb-2">Choose your accelerator track:</label>
          <div class="relative">
            <select
              id="program-select"
              name="program_id"
              class="elysian-input w-full appearance-none pr-10 cursor-pointer"
              required
              onchange="updatePreview()"
            >
              <option value="" disabled selected>-- Select a program track --</option>
              <?php foreach ($programs as $prog): ?>
                <option value="<?php echo htmlspecialchars($prog['id']); ?>" <?php echo $student['selected_program_id'] === $prog['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($prog['code'] . ' - ' . $prog['title'] . ' (' . $prog['duration'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
          <p class="text-slate-500 text-xs text-center mt-4 font-medium">
            All mentorship programs are currently KES 1,440 / 12 USD
          </p>
        </div>

        <!-- Smart Preview & CTA -->
        <div id="preview-container" class="hidden animate-slide-up">
          <div class="elysian-card p-6 md:p-8 bg-white border border-slate-100 shadow-lg space-y-6">
            <!-- Header -->
            <div>
              <span class="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-200/20 px-2.5 py-1 rounded-full uppercase tracking-wider">
                Program Preview
              </span>
              <h2 id="preview-title" class="text-2xl font-bold text-slate-800 mt-3">
                --
              </h2>
              <p class="text-xs text-slate-400 mt-1 font-semibold font-mono uppercase">
                Code: <span id="preview-code">--</span> | Duration: <span id="preview-duration">--</span>
              </p>
            </div>

            <!-- Description -->
            <div>
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                Overview
              </h3>
              <p id="preview-desc" class="text-sm text-slate-600 leading-relaxed">
                --
              </p>
            </div>

            <!-- Key Focus Areas (Outcomes) -->
            <div>
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">
                Key Focus Areas
              </h3>
              <div id="preview-outcomes" class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <!-- Dynamically populated -->
              </div>
            </div>

            <!-- CTA button (Proceed to Payment) -->
            <div class="pt-4 border-t border-slate-50 flex items-center justify-end">
              <button
                type="submit"
                class="elysian-btn elysian-btn-gold px-8 py-3 text-sm font-bold shadow-md shadow-amber-500/10 flex items-center gap-2"
              >
                Proceed to Payment
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
// Load programs data into JavaScript
const programsData = <?php echo $programs_json; ?>;

function updatePreview() {
  const select = document.getElementById('program-select');
  const selectedId = select.value;
  const container = document.getElementById('preview-container');
  
  if (!selectedId) {
    container.classList.add('hidden');
    return;
  }
  
  const prog = programsData.find(p => p.id === selectedId);
  if (!prog) return;
  
  // Set basic content
  document.getElementById('preview-title').innerText = prog.title;
  document.getElementById('preview-code').innerText = prog.code;
  document.getElementById('preview-duration').innerText = prog.duration;
  document.getElementById('preview-desc').innerText = prog.description;
  
  // Populate outcomes list
  const outcomesDiv = document.getElementById('preview-outcomes');
  outcomesDiv.innerHTML = '';
  
  let outcomes = [];
  try {
    if (prog.outcomes) {
      outcomes = JSON.parse(prog.outcomes);
    }
  } catch(e) {
    outcomes = [];
  }
  
  if (Array.isArray(outcomes) && outcomes.length > 0) {
    outcomes.forEach(outcome => {
      const item = document.createElement('div');
      item.className = 'flex gap-2 items-start text-xs text-slate-600 leading-relaxed bg-slate-50/50 p-2.5 border border-slate-100 rounded-xl';
      item.innerHTML = `
        <svg class="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>${escapeHtml(outcome)}</span>
      `;
      outcomesDiv.appendChild(item);
    });
  } else {
    outcomesDiv.innerHTML = '<div class="text-slate-400 text-xs italic col-span-2">Comprehensive syllabus provided upon program initiation.</div>';
  }
  
  // Show preview with a simple smooth fade-in
  container.classList.remove('hidden');
}

function escapeHtml(text) {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Trigger initial preview load if a program is already pre-selected
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('program-select').value) {
    updatePreview();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
