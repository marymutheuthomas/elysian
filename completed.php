<?php
// completed.php — Completion Engine & Export Center (Bento Grid Theme)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/profiles.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth and status guard
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

// Redirect if not complete
if ($student['status'] !== 'completed') {
    if ($student['status'] === 'active') {
        header("Location: /tunnel.php");
        exit;
    } elseif ($student['status'] === 'payment_pending') {
        header("Location: /payment.php");
        exit;
    } else {
        header("Location: /programs.php");
        exit;
    }
}

$program_id = $student['selected_program_id'] ?? '';

// Fetch program details
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

// ── Fetch pillars (ordered) ───────────────────────────────────
$stmt_pil = $pdo->prepare(
    "SELECT * FROM `pillars` WHERE `program_id` = ? ORDER BY `sort_order` ASC, `id` ASC"
);
$stmt_pil->execute([$program_id]);
$pillars = $stmt_pil->fetchAll();

// ── Fetch blocks + components per pillar ─────────────────────
foreach ($pillars as &$pillar) {
    $stmt_blk = $pdo->prepare(
        "SELECT * FROM `blocks` WHERE `pillar_id` = ? ORDER BY `sort_order` ASC, `id` ASC"
    );
    $stmt_blk->execute([$pillar['id']]);
    $pillar['blocks'] = $stmt_blk->fetchAll();

    foreach ($pillar['blocks'] as &$blk) {
        $stmt_comp = $pdo->prepare(
            "SELECT * FROM `components`
             WHERE `block_id` = ? OR (`pillar_id` = ? AND (`block_id` IS NULL OR `block_id` = ''))
             ORDER BY `sort_order` ASC, `id` ASC"
        );
        $stmt_comp->execute([$blk['id'], $pillar['id']]);
        $comps = $stmt_comp->fetchAll();
        foreach ($comps as &$c) {
            $c['showIf']  = $c['show_if'] ? json_decode($c['show_if'],  true) : null;
            $c['options'] = $c['options']  ? json_decode($c['options'],  true) : null;
        }
        unset($c);
        $blk['components'] = $comps;
    }
    unset($blk);
}
unset($pillar);

// ── Build a flat ordered list of "steps" ─────────────────────
$allComponents = [];
foreach ($pillars as $p) {
    foreach ($p['blocks'] as $b) {
        foreach ($b['components'] as $c) {
            $c['_pillar_id']    = $p['id'];
            $c['_pillar_title'] = $p['title'];
            $c['_block_id']     = $b['id'];
            $c['_block_title']  = $b['title'];
            $allComponents[] = $c;
        }
    }
}

// ── Conditional logic evaluator ───────────────────────────────
function evaluateShowIf($rule, $answers) {
    if (!$rule) return true;
    $blockId = $rule['blockId'];
    if (!isset($answers[$blockId]) || $answers[$blockId] === null || $answers[$blockId] === '') return false;
    $val      = $answers[$blockId];
    $operator = $rule['operator'];
    $ruleVal  = $rule['value'];
    switch ($operator) {
        case 'equals':     return $val == $ruleVal;
        case 'not_equals': return $val != $ruleVal;
        case 'contains':
            if (is_array($val)) return in_array($ruleVal, $val);
            if (is_string($val)) return strpos($val, $ruleVal) !== false;
            return false;
        default: return true;
    }
}

$findComponent = function($id) use ($allComponents) {
    foreach ($allComponents as $c) { if ($c['id'] === $id) return $c; }
    return null;
};

// ── Filter visible components ─────────────────────────────────
$answers = json_decode($student['answers'], true) ?: [];

function getVisibleComponents($allComponents, $answers, $findComponent) {
    $visible = [];
    foreach ($allComponents as $comp) {
        $show = true;
        $rule = $comp['showIf'];
        while ($rule) {
            if (!evaluateShowIf($rule, $answers)) { $show = false; break; }
            $parent = $findComponent($rule['blockId']);
            $rule   = $parent ? $parent['showIf'] : null;
        }
        if ($show) $visible[] = $comp;
    }
    return $visible;
}

$visibleComponents = getVisibleComponents($allComponents, $answers, $findComponent);
$active_index      = (int)$student['active_block_index'];

// If mentor added new content/blocks after completion, auto-reactivate student and return to tunnel
if ($active_index < count($visibleComponents)) {
    $pdo->prepare("UPDATE `students` SET `status` = 'active' WHERE `permanent_id` = ?")->execute([$student_id]);
    header("Location: /tunnel.php");
    exit;
}

$profileCode = strtoupper($student['profile_code']);
$matchedProfile = isset($master_profiles[$profileCode]) ? $master_profiles[$profileCode] : null;

// ── Separate components by type (Goal vs Reflections) ────────
$goalComponents = [];
$reflectionComponents = [];
foreach ($visibleComponents as $c) {
    if ($c['type'] === 'goal') {
        $goalComponents[] = $c;
    } else {
        $reflectionComponents[] = $c;
    }
}

// ── Handle Mentor Engagement Prompt ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_engagement_prompt'])) {
    $msg = "I have completed my strategy assessment and am ready for a review!";
    $msg_id = 'msg_' . substr(md5(uniqid(rand(), true)), 0, 10);
    $stmt_msg = $pdo->prepare("INSERT INTO `messages` (`id`, `thread_id`, `sender_name`, `content`) VALUES (?, ?, ?, ?)");
    $stmt_msg->execute([$msg_id, $student_id, $student['name'], $msg]);
    header("Location: /completed.php?prompt_sent=1");
    exit;
}

// ── All redirects done. Output HTML ───────────────────────────
require_once __DIR__ . '/includes/header.php';
?>

<!-- Phase 4 Bento Grid Styles -->
<style>
  .bento-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 16px;
  }
  .bento-tile {
    background: var(--surface-card);
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .bento-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -2px rgba(79, 70, 229, 0.10);
  }

  /* Print Optimization */
  @media print {
    .no-print { display: none !important; }
    .bento-grid { display: block !important; }
    .bento-tile {
      border: 1px solid #CBD5E1 !important;
      box-shadow: none !important;
      transform: none !important;
      margin-bottom: 20px !important;
      background: #FFFFFF !important;
      page-break-inside: avoid;
    }
    body { background: #FFFFFF !important; color: #000000 !important; }
  }
</style>

<!-- ══════════════════════════════════════════════════════════════
     SCREEN LAYOUT: BENTO GRID CONTAINER
══════════════════════════════════════════════════════════════ -->
<div class="max-w-6xl w-full mx-auto px-4 py-8 no-print">

  <!-- Top Bar & Coursework Alert -->
  <?php 
  $active_index = (int)$student['active_block_index'];
  $has_new_coursework = ($active_index < count($visibleComponents) && count($visibleComponents) > 0);
  ?>

  <?php if ($has_new_coursework): ?>
    <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
      <div>
        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest block font-mono">New Coursework Available</span>
        <h4 class="text-sm font-bold text-main mt-0.5">Your mentor has added new assessment blocks to this program</h4>
        <p class="text-xs text-muted mt-0.5">Continue seamless execution in your current pillar without starting over.</p>
      </div>
      <a href="/tunnel.php" class="elysian-btn elysian-btn-brand py-2.5 px-5 text-xs font-bold whitespace-nowrap shadow-md">
        Resume Coursework &rarr;
      </a>
    </div>
  <?php endif; ?>

  <!-- Bento Grid Viewport -->
  <div class="bento-grid">

    <!-- ── Tile 1: Hero Outcome (Span 8) ───────────────────────── -->
    <div class="bento-tile col-span-12 lg:col-span-8 p-6 md:p-8 flex flex-col justify-between relative overflow-hidden">
      <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600"></div>

      <div>
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">
            🎉 Diagnostic Complete
          </span>
          <span class="text-xs font-mono font-bold text-muted">
            Completed <?php echo date('F j, Y'); ?>
          </span>
        </div>

        <h1 class="text-2xl md:text-3xl font-extrabold text-main font-display mb-2 leading-tight">
          <?php echo htmlspecialchars($program['title']); ?>
        </h1>
        <p class="text-xs md:text-sm text-muted leading-relaxed max-w-xl">
          Congratulations, <strong><?php echo htmlspecialchars($student['name']); ?></strong>! You have successfully completed all diagnostic pillars and compiled your strategic roadmap.
        </p>
      </div>

      <?php if ($matchedProfile): ?>
        <div class="mt-6 pt-4 border-t border-subtle flex items-center justify-between flex-wrap gap-3">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-muted block">Strategic Archetype</span>
            <span class="text-base font-bold text-main font-display"><?php echo htmlspecialchars($matchedProfile['title']); ?></span>
          </div>
          <span class="px-3.5 py-1.5 rounded-full text-xs font-mono font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
            <?php echo htmlspecialchars($profileCode); ?> Profile
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Tile 2: Quick Summary Metrics (Span 4) ──────────────── -->
    <div class="bento-tile col-span-12 lg:col-span-4 p-6 flex flex-col justify-between bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-900">
      <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest block mb-3 font-mono">
        Diagnostic Summary
      </span>
      <div class="space-y-3 flex-1 flex flex-col justify-center">
        <div class="flex items-center justify-between p-3 rounded-xl bg-surface border border-subtle">
          <span class="text-xs font-semibold text-main flex items-center gap-2">
            <span>🏛️</span> Pillars Completed
          </span>
          <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400 font-mono"><?php echo count($pillars); ?></span>
        </div>
        <div class="flex items-center justify-between p-3 rounded-xl bg-surface border border-subtle">
          <span class="text-xs font-semibold text-main flex items-center gap-2">
            <span>📌</span> SMART Goals Mapped
          </span>
          <span class="text-lg font-bold text-amber-600 dark:text-amber-400 font-mono"><?php echo count($goalComponents); ?></span>
        </div>
        <div class="flex items-center justify-between p-3 rounded-xl bg-surface border border-subtle">
          <span class="text-xs font-semibold text-main flex items-center gap-2">
            <span>📝</span> Reflections Written
          </span>
          <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 font-mono"><?php echo count($reflectionComponents); ?></span>
        </div>
      </div>
    </div>

    <!-- ── Profile Details Card (if matched) ───────────────────── -->
    <?php if ($matchedProfile): ?>
      <div class="bento-tile col-span-12 p-6 md:p-8">
        <span class="text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">
          Archetype Breakdown
        </span>
        <h2 class="text-xl font-bold text-main mb-4 font-display">
          <?php echo htmlspecialchars($matchedProfile['title']); ?>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
            <h4 class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-2.5">Core Strengths</h4>
            <ul class="text-xs text-main space-y-1.5">
              <?php foreach ($matchedProfile['strengths'] as $st): ?>
                <li class="flex gap-2 items-start">
                  <span class="text-emerald-500 font-bold">✓</span>
                  <span><?php echo htmlspecialchars($st); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
            <h4 class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-2.5">Growth Focus Areas</h4>
            <ul class="text-xs text-main space-y-1.5">
              <?php foreach ($matchedProfile['weaknesses'] as $wk): ?>
                <li class="flex gap-2 items-start">
                  <span class="text-indigo-500 font-bold">→</span>
                  <span><?php echo htmlspecialchars($wk); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <div class="bg-slate-50 dark:bg-slate-800/50 border border-subtle rounded-xl p-4">
          <h4 class="text-[10px] font-bold text-muted uppercase tracking-widest mb-2.5">
            Prescribed Strategic Actions
          </h4>
          <ul class="text-xs text-main space-y-2">
            <?php foreach ($matchedProfile['suggested_goals'] as $gl): ?>
              <li class="flex gap-2 items-start">
                <span class="text-amber-500 font-bold">•</span>
                <span><?php echo htmlspecialchars($gl); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- ── Tile 3: Tabbed Summary Engine (Span 12) ──────────────── -->
    <div class="bento-tile col-span-12 overflow-hidden flex flex-col">
      <!-- Tab Navigation Header -->
      <div class="flex border-b border-subtle bg-slate-50/50 dark:bg-slate-900/50">
        <button onclick="switchTab('goals')" id="tab-btn-goals" class="flex-1 py-4 text-xs font-bold uppercase tracking-widest text-indigo-600 border-b-2 border-indigo-600 transition-colors">
          🎯 Mapped Goals Matrix
        </button>
        <button onclick="switchTab('reflections')" id="tab-btn-reflections" class="flex-1 py-4 text-xs font-bold uppercase tracking-widest text-muted border-b-2 border-transparent hover:text-main transition-colors">
          📝 Reflections Journal Log
        </button>
      </div>

      <!-- Goals Tab Content -->
      <div id="tab-content-goals" class="p-6 space-y-4 max-h-96 overflow-y-auto custom-scrollbar block">
        <?php 
        $has_goals = false;
        foreach ($goalComponents as $block) {
            $answer = isset($answers[$block['id']]) ? $answers[$block['id']] : '';
            if ($answer === '') continue;
            $has_goals = true;
            ?>
            <div class="border-b border-subtle pb-3.5 last:border-b-0">
              <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">
                <?php echo htmlspecialchars($block['question']); ?>
              </span>
              <span class="text-base font-bold text-indigo-600 dark:text-indigo-400 whitespace-pre-wrap leading-relaxed">
                <?php echo htmlspecialchars($answer); ?>
              </span>
            </div>
            <?php
        }
        if (!$has_goals) {
            echo '<div class="text-muted text-xs italic text-center py-6">No goals defined yet.</div>';
        }
        ?>
      </div>

      <!-- Reflections Tab Content -->
      <div id="tab-content-reflections" class="p-6 space-y-4 max-h-96 overflow-y-auto custom-scrollbar hidden">
        <?php 
        $has_reflections = false;
        foreach ($reflectionComponents as $block) {
            $answer = isset($answers[$block['id']]) ? $answers[$block['id']] : '';
            if ($answer === '') continue;
            $has_reflections = true;
            ?>
            <div class="border-b border-subtle pb-3.5 last:border-b-0">
              <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">
                <?php echo htmlspecialchars($block['question']); ?>
              </span>
              <span class="text-xs text-main whitespace-pre-wrap leading-relaxed">
                <?php echo htmlspecialchars(is_array($answer) ? implode(', ', $answer) : $answer); ?>
              </span>
            </div>
            <?php
        }
        if (!$has_reflections) {
            echo '<div class="text-muted text-xs italic text-center py-6">No diagnostic responses logged.</div>';
        }
        ?>
      </div>
    </div>

    <!-- ── Tile 4: PDF Export Hub (Span 6) ─────────────────────── -->
    <div class="bento-tile col-span-12 md:col-span-6 p-6 flex flex-col justify-between">
      <div>
        <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-widest mb-2">
          <span>📄 PDF Export Hub</span>
        </div>
        <h3 class="text-base font-bold text-main font-display mb-1">Download Strategic Worksheets</h3>
        <p class="text-xs text-muted mb-4 leading-relaxed">Export your complete diagnostic portfolio or print official strategy documents.</p>
      </div>

      <div class="flex flex-wrap gap-2.5 pt-2 border-t border-subtle">
        <button onclick="window.print()" class="elysian-btn elysian-btn-brand py-2.5 px-4 text-xs font-bold flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Export Strategy PDF
        </button>
        <button onclick="window.print()" class="elysian-btn elysian-btn-ghost py-2.5 px-4 text-xs font-bold flex items-center gap-2">
          🖨️ Print Portfolio
        </button>
      </div>
    </div>

    <!-- ── Tile 5: Mentor Alignment CTA (Span 6) ────────────────── -->
    <div class="bento-tile col-span-12 md:col-span-6 p-6 flex flex-col justify-between" id="mentor-prompt-card">
      <?php if (isset($_GET['prompt_sent'])): ?>
        <div class="flex flex-col justify-between h-full">
          <div>
            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest block mb-2 font-mono">Mentor Notified ✓</span>
            <h3 class="text-base font-bold text-main font-display">Review Request Submitted</h3>
            <p class="text-xs text-muted mt-1 leading-relaxed">Your mentor has received your completion alert and will review your strategy shortly.</p>
          </div>
          <a href="/tunnel.php" class="elysian-btn elysian-btn-brand py-2.5 px-4 text-xs font-bold inline-flex items-center gap-2 self-start mt-4">
            Open Mentor Chat →
          </a>
        </div>
      <?php else: ?>
        <div>
          <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest block mb-2 font-mono">Mentor Engagement</span>
          <h3 class="text-base font-bold text-main font-display mb-1">Ready to align on your vision?</h3>
          <p class="text-xs text-muted mb-4 leading-relaxed">Connect with your mentor to review your completed diagnostic findings and establish next steps.</p>
        </div>
        <div class="flex items-center gap-3 pt-2 border-t border-subtle">
          <form method="POST" action="/completed.php" class="m-0 flex-1">
            <input type="hidden" name="send_engagement_prompt" value="1">
            <button type="submit" class="elysian-btn elysian-btn-brand w-full py-2.5 px-4 text-xs font-bold">
              Yes, Connect with Mentor
            </button>
          </form>
          <button onclick="document.getElementById('mentor-prompt-card').style.display='none'" class="elysian-btn elysian-btn-ghost py-2.5 px-3 text-xs font-bold">
            Continue Solo
          </button>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /.bento-grid -->

</div>

<script>
function switchTab(tabId) {
  document.getElementById('tab-btn-goals').classList.remove('text-indigo-600', 'border-indigo-600');
  document.getElementById('tab-btn-goals').classList.add('text-muted', 'border-transparent');
  document.getElementById('tab-btn-reflections').classList.remove('text-indigo-600', 'border-indigo-600');
  document.getElementById('tab-btn-reflections').classList.add('text-muted', 'border-transparent');
  
  const activeBtn = document.getElementById('tab-btn-' + tabId);
  activeBtn.classList.remove('text-muted', 'border-transparent');
  activeBtn.classList.add('text-indigo-600', 'border-indigo-600');
  
  document.getElementById('tab-content-goals').classList.add('hidden');
  document.getElementById('tab-content-goals').classList.remove('block');
  document.getElementById('tab-content-reflections').classList.add('hidden');
  document.getElementById('tab-content-reflections').classList.remove('block');
  
  const activeContent = document.getElementById('tab-content-' + tabId);
  activeContent.classList.remove('hidden');
  activeContent.classList.add('block');
}
</script>

<!-- ══════════════════════════════════════════════════════════════
     PRINT LAYOUT (@media print - Visible on Print/PDF Export Only)
══════════════════════════════════════════════════════════════ -->
<div class="hidden print:block w-full text-black">
  <div class="print-header flex justify-between items-center border-b-2 border-indigo-600 pb-3 mb-6">
    <div>
      <h1 class="text-2xl font-bold tracking-tight uppercase text-indigo-700">
        ELYSIAN SUCCESS DIAGNOSTIC
      </h1>
      <p class="text-xs text-gray-500 font-mono">
        OFFICIAL STRATEGY REPORT • UIN: <?php echo htmlspecialchars($student['permanent_id']); ?>
      </p>
    </div>
    <div class="text-right text-xs text-gray-500">
      <p class="font-bold"><?php echo htmlspecialchars($student['name']); ?></p>
      <p><?php echo htmlspecialchars($student['email']); ?></p>
      <p>Generated: <?php echo date('Y-m-d'); ?></p>
    </div>
  </div>

  <div class="print-section bg-gray-50 p-4 border border-gray-200 rounded-lg mb-6">
    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700 mb-2">Program Assessment</h2>
    <div class="grid grid-cols-2 gap-4 text-xs">
      <div>
        <span class="text-gray-500 font-semibold block">Accelerator Path</span>
        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($program['title']); ?></span>
      </div>
      <div>
        <span class="text-gray-500 font-semibold block">Duration</span>
        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($program['duration']); ?></span>
      </div>
    </div>
  </div>

  <?php if ($matchedProfile): ?>
    <div class="print-section mb-6">
      <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-300 pb-1.5 mb-3">
        Strategic Profile: <?php echo htmlspecialchars($matchedProfile['title']); ?> (<?php echo htmlspecialchars($profileCode); ?>)
      </h2>

      <div class="grid grid-cols-2 gap-6 mb-4 text-xs">
        <div>
          <span class="text-gray-500 font-bold block mb-1 uppercase tracking-wide">Core Strengths</span>
          <ul class="space-y-1">
            <?php foreach ($matchedProfile['strengths'] as $st): ?>
              <li class="flex gap-1.5 items-start">
                <span>✓</span> <span><?php echo htmlspecialchars($st); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <span class="text-gray-500 font-bold block mb-1 uppercase tracking-wide">Growth Focus Areas</span>
          <ul class="space-y-1">
            <?php foreach ($matchedProfile['weaknesses'] as $wk): ?>
              <li class="flex gap-1.5 items-start">
                <span>→</span> <span><?php echo htmlspecialchars($wk); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="print-section mb-6" id="print-goals">
    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-300 pb-1.5 mb-4">
      Mapped Goals Matrix
    </h2>
    <div class="space-y-3.5">
      <?php 
      foreach ($goalComponents as $block) {
          $answer = isset($answers[$block['id']]) ? $answers[$block['id']] : '';
          if ($answer === '') continue;
          ?>
          <div class="border-b border-gray-200 pb-2 mb-2">
            <span class="text-[10px] font-bold text-gray-500 uppercase block tracking-wider mb-1">
              <?php echo htmlspecialchars($block['question']); ?>
            </span>
            <span class="text-base text-gray-900 font-bold whitespace-pre-wrap leading-relaxed block">
              <?php echo htmlspecialchars($answer); ?>
            </span>
          </div>
          <?php
      }
      if (empty($goalComponents)) {
          echo '<div class="text-xs text-gray-400">No mapped goals present.</div>';
      }
      ?>
    </div>
  </div>

  <div class="print-section mb-6" id="print-reflections">
    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-700 border-b border-gray-300 pb-1.5 mb-4">
      Diagnostic Response Log (Reflections)
    </h2>
    <div class="space-y-3.5">
      <?php 
      foreach ($reflectionComponents as $block) {
          $answer = isset($answers[$block['id']]) ? $answers[$block['id']] : '';
          if ($answer === '') continue;
          ?>
          <div class="border-b border-gray-200 pb-2 mb-2">
            <span class="text-[10px] font-bold text-gray-500 uppercase block tracking-wider mb-1">
              <?php echo htmlspecialchars($block['question']); ?>
            </span>
            <span class="text-xs text-gray-900 font-medium whitespace-pre-wrap leading-relaxed block">
              <?php echo htmlspecialchars(is_array($answer) ? implode(', ', $answer) : $answer); ?>
            </span>
          </div>
          <?php
      }
      if (empty($reflectionComponents)) {
          echo '<div class="text-xs text-gray-400">No reflections present.</div>';
      }
      ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
