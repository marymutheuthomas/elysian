<?php
// tunnel.php — Elysian Success Mentee Workstation (4-Tier Architecture)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/profiles.php';
require_once __DIR__ . '/includes/evaluation_engine.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth guard ────────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    header("Location: /index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

$stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: /logout.php");
    exit;
}

// Redirect by status
if ($student['status'] === 'program_selection') {
    header("Location: /programs.php");
    exit;
} elseif ($student['status'] === 'payment_pending') {
    header("Location: /payment.php");
    exit;
}

$program_id = $student['selected_program_id'] ?? '';

// Check if program exists
$program = null;
if (!empty($program_id)) {
    $stmt_prog = $pdo->prepare("SELECT * FROM `programs` WHERE `id` = ?");
    $stmt_prog->execute([$program_id]);
    $program = $stmt_prog->fetch();
}

// Fallback to first available active program if missing/invalid
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
            $c['_pillar_note']  = $p['congratulatory_note'] ?? '';
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

// Reactivate if mentor added new content
if ($student['status'] === 'completed' && $active_index < count($visibleComponents)) {
    $pdo->prepare("UPDATE `students` SET `status` = 'active' WHERE `permanent_id` = ?")->execute([$student_id]);
    $student['status'] = 'active';
}

// ── Redirect to completion if done, or enter read-only review ─
// The first time a student finishes (status wasn't already 'completed'),
// mark them done and send them to the summary page as before. If they're
// already completed and land here again (e.g. "Review Your Coursework"
// from completed.php), don't bounce them away — let them browse read-only
// instead. $isReview repurposes $active_index as a display-only position;
// it is never written back to the DB (all mutating handlers below refuse
// to run while $isReview is true), so this is safe.
$isReview = false;
if ($student['status'] === 'completed' || ($active_index >= count($visibleComponents) && count($visibleComponents) > 0)) {
    if ($student['status'] !== 'completed') {
        $pdo->prepare("UPDATE `students` SET `status` = 'completed' WHERE `permanent_id` = ?")->execute([$student_id]);
        header("Location: /completed.php");
        exit;
    }
    $isReview = true;
    $active_index = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $active_index = max(0, min($active_index, count($visibleComponents) - 1));
}

$currentComp = $visibleComponents[$active_index] ?? null;

// Calculate Pillar numbers
$current_pillar_num = 1;
$total_pillars = count($pillars);
if ($currentComp) {
    foreach ($pillars as $p_idx => $pil) {
        if ($pil['id'] === $currentComp['_pillar_id']) {
            $current_pillar_num = $p_idx + 1;
            break;
        }
    }
}

// ── Pillar-complete intercept ─────────────────────────────────
$show_pillar_complete = false;
$completed_pillar     = null;
$next_pillar_title    = null;

if (isset($_GET['pillar_complete'])) {
    $pil_id = $_GET['pillar_complete'];
    foreach ($pillars as $pil) {
        if ($pil['id'] === $pil_id) { $completed_pillar = $pil; break; }
    }
    if ($completed_pillar) {
        $found = false;
        foreach ($pillars as $pil) {
            if ($found) { $next_pillar_title = $pil['title']; break; }
            if ($pil['id'] === $pil_id) $found = true;
        }
        $show_pillar_complete = true;
    }
}

// ── AJAX: Get reveal card HTML ──────────────────────────────
if (isset($_GET['get_reveal'])) {
    $reveal_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', strtoupper($_GET['code'] ?? '')), 0, 4));
    $mp = getProfileByCode($pdo, $reveal_code);
    header('Content-Type: application/json');
    if ($mp) {
        $p_s  = $mp['strengths'] ?? [];
        $p_g  = $mp['growth_areas'] ?? $mp['weaknesses'] ?? [];
        $p_gl = $mp['smart_goals'] ?? $mp['suggested_goals'] ?? [];
        $html  = '<div>';
        $html .= '<div class="flex items-center justify-between mb-4">';
        $html .= '<span class="text-[10px] font-bold uppercase tracking-widest text-amber-500 dark:text-amber-400 font-mono">Your Profile Revealed</span>';
        $html .= '<span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">' . htmlspecialchars($reveal_code) . '</span>';
        $html .= '</div>';
        $html .= '<h3 class="text-xl font-bold text-main font-display mb-4">' . htmlspecialchars($mp['title']) . '</h3>';
        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">';
        $html .= '<div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">';
        $html .= '<span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest block mb-1.5">Strengths</span>';
        foreach ($p_s as $s) { $html .= '<div class="text-xs text-main flex items-center gap-1.5 mb-1"><span class="text-emerald-500">✓</span>' . htmlspecialchars($s) . '</div>'; }
        $html .= '</div>';
        $html .= '<div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20">';
        $html .= '<span class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest block mb-1.5">Growth Areas</span>';
        foreach ($p_g as $g) { $html .= '<div class="text-xs text-main flex items-center gap-1.5 mb-1"><span class="text-indigo-500">&rarr;</span>' . htmlspecialchars($g) . '</div>'; }
        $html .= '</div></div>';
        if ($p_gl) {
            $html .= '<div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20">';
            $html .= '<span class="text-[9px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest block mb-2">Prescribed SMART Goals</span>';
            $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">';
            foreach ($p_gl as $gi => $gl) {
                $html .= '<div class="text-xs text-main flex items-start gap-2">';
                $html .= '<span class="w-4 h-4 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[9px] font-bold flex items-center justify-center flex-shrink-0 mt-0.5">' . ($gi+1) . '</span>';
                $html .= htmlspecialchars($gl) . '</div>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';
        echo json_encode(['html' => $html]);
    } else {
        echo json_encode(['html' => '<p class="text-muted text-xs p-4">Profile found for code: ' . htmlspecialchars($reveal_code) . '</p>']);
    }
    exit;
}

// ── AJAX: Auto-save draft (no advance; blocked while reviewing) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['autosave'])) {
    if ($isReview) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'read_only']);
        exit;
    }
    $block_id   = $_POST['block_id'] ?? '';
    $answer_val = $_POST['answer'] ?? '';
    if ($block_id) {
        $answers[$block_id] = $answer_val;
        $pdo->prepare("UPDATE `students` SET `answers` = ? WHERE `permanent_id` = ?")
            ->execute([json_encode($answers), $student_id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'saved']);
    exit;
}

// ── GET: Sidebar jump-to (go back to a prior pillar) ─────────
if (isset($_GET['jump_to'])) {
    $jump_idx = (int)$_GET['jump_to'];
    if ($isReview) {
        // Review mode: navigate via the display-only `view` param, never write to the DB.
        if ($jump_idx >= 0 && $jump_idx < count($visibleComponents)) {
            header("Location: /tunnel.php?view=" . $jump_idx);
            exit;
        }
    } elseif ($jump_idx >= 0 && $jump_idx < $active_index) {
        $pdo->prepare("UPDATE `students` SET `active_block_index` = ? WHERE `permanent_id` = ?")
            ->execute([$jump_idx, $student_id]);
        header("Location: /tunnel.php");
        exit;
    }
}

// ── POST: Go back one step ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['go_back']) && !$isReview) {
    $new_index = max(0, $active_index - 1);
    $pdo->prepare("UPDATE `students` SET `active_block_index` = ? WHERE `permanent_id` = ?")
        ->execute([$new_index, $student_id]);
    header("Location: /tunnel.php");
    exit;
}

// ── POST: Submit answer & advance (blocked while reviewing) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_block']) && $currentComp && !$isReview) {

    $comp_id    = $currentComp['id'];
    $answer_val = $_POST['answer'] ?? '';

    // --- Decoupled Raw Scores Collection ---
    $raw_scores = !empty($student['raw_scores']) ? (json_decode($student['raw_scores'], true) ?: []) : [];
    if (!is_array($raw_scores)) $raw_scores = [];

    // --- composite_block: collect element answers dictionary if present ---
    if (isset($_POST['composite_ans']) && is_array($_POST['composite_ans'])) {
        $answers[$comp_id] = $_POST['composite_ans'];
        $answer_val = 'composite_submitted';

        // Collect raw scores from composite sub-elements (like trait_matrix)
        $cs_elements = !empty($currentComp['content_schema']) ? (is_array($currentComp['content_schema']) ? $currentComp['content_schema'] : json_decode($currentComp['content_schema'], true)) : [];
        if (is_array($cs_elements)) {
            foreach ($cs_elements as $elem) {
                $eid = $elem['id'] ?? '';
                if (($elem['type'] ?? '') === 'trait_matrix' && isset($_POST['composite_ans'][$eid])) {
                    $selected_trait = strtoupper(trim($_POST['composite_ans'][$eid]));
                    if (!empty($selected_trait)) {
                        $raw_scores[] = [
                            'step_id' => $comp_id,
                            'element_id' => $eid,
                            'trait_id' => $selected_trait,
                            'points' => 1
                        ];
                    }
                }
            }
        }
    } elseif ($currentComp['type'] === 'scoring_block') {
        $sq_answers = $_POST['sq'] ?? []; // Array of selected codes
        $code_string = implode('', array_map('strtoupper', array_slice($sq_answers, 0, 4)));
        $answers[$comp_id] = [
            'codes'    => $code_string,
            'sq'       => $sq_answers,
            'revealed' => $code_string,
        ];
        $answer_val = $code_string;

        foreach ($sq_answers as $code) {
            $code_clean = strtoupper(trim($code));
            if (!empty($code_clean)) {
                $raw_scores[] = [
                    'step_id' => $comp_id,
                    'trait_id' => $code_clean,
                    'points' => 1
                ];
            }
        }
    } else {
        $answers[$comp_id] = $answer_val;

        if ($currentComp['type'] === 'scoring' && !empty($currentComp['options'])) {
            $opts = is_array($currentComp['options']) ? $currentComp['options'] : json_decode($currentComp['options'], true);
            if (is_array($opts)) {
                foreach ($opts as $opt) {
                    if (($opt['value'] ?? '') === $answer_val && !empty($opt['hidden_code'])) {
                        $raw_scores[] = [
                            'step_id' => $comp_id,
                            'trait_id' => strtoupper(trim($opt['hidden_code'])),
                            'points' => 1
                        ];
                    }
                }
            }
        }
    }

    // Evaluate scores using scheme-agnostic engine
    $program_scheme_id = $program['scheme_id'] ?? 'mbti_16_types';
    $evalResult = evaluateAssessment($pdo, $program_scheme_id, $raw_scores);

    $profile_code = $student['profile_code'];
    $archetype_id = $student['archetype_id'] ?? '';

    if (!empty($evalResult['code'])) {
        $profile_code = $evalResult['code'];
    }
    if (!empty($evalResult['archetype_id'])) {
        $archetype_id = $evalResult['archetype_id'];
    }

    $status = $student['status'];
    $tempVisible = getVisibleComponents($allComponents, $answers, $findComponent);
    $next_index = $active_index + 1;

    $prev_pillar = $currentComp['_pillar_id'];
    $next_comp   = $tempVisible[$next_index] ?? null;
    $crossed_pillar = ($next_comp && $next_comp['_pillar_id'] !== $prev_pillar);

    if ($next_index >= count($tempVisible)) {
        $status = 'completed';
    }

    $pdo->prepare(
        "UPDATE `students` SET `answers` = ?, `profile_code` = ?, `raw_scores` = ?, `archetype_id` = ?, `active_block_index` = ?, `status` = ? WHERE `permanent_id` = ?"
    )->execute([json_encode($answers), $profile_code, json_encode($raw_scores), $archetype_id, $next_index, $status, $student_id]);

    if ($status === 'completed') {
        header("Location: /completed.php");
        exit;
    }

    if ($crossed_pillar) {
        header("Location: /tunnel.php?pillar_complete=" . urlencode($prev_pillar));
        exit;
    }

    header("Location: /tunnel.php");
    exit;
}

// ── POST: Send Chat ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_chat'])) {
    $chat_content = isset($_POST['chat_content']) ? trim($_POST['chat_content']) : '';
    if (!empty($chat_content)) {
        $msg_id = 'MSG-' . time() . '-' . rand(10, 99);
        $pdo->prepare("INSERT INTO `messages` (`id`, `thread_id`, `sender`, `sender_label`, `content`) VALUES (?, ?, 'student', ?, ?)")
            ->execute([$msg_id, $student_id, $student['name'], $chat_content]);
    }
    if (isset($_GET['ajax'])) { echo json_encode(['status' => 'success']); exit; }
    header("Location: /tunnel.php");
    exit;
}

// ── AJAX: Fetch chat ──────────────────────────────────────────
if (isset($_GET['fetch_chat'])) {
    header('Content-Type: application/json');
    $stmt_chat = $pdo->prepare("SELECT * FROM `messages` WHERE `thread_id` = ? ORDER BY `timestamp` ASC");
    $stmt_chat->execute([$student_id]);
    echo json_encode($stmt_chat->fetchAll());
    exit;
}

// ── Fetch chat messages ───────────────────────────────────────
$stmt_chat = $pdo->prepare("SELECT * FROM `messages` WHERE `thread_id` = ? ORDER BY `timestamp` ASC");
$stmt_chat->execute([$student_id]);
$chat_messages = $stmt_chat->fetchAll();

// ── Compute per-pillar completion stats ───────────────────────
$pillar_stats = [];
foreach ($pillars as $pil) {
    $total = 0; $done = 0;
    foreach ($pil['blocks'] as $blk) {
        foreach ($blk['components'] as $c) {
            if ($c['type'] === 'result_reveal') continue;
            $total++;
            if (isset($answers[$c['id']]) && $answers[$c['id']] !== '') $done++;
        }
    }
    $pillar_stats[$pil['id']] = ['total' => $total, 'done' => $done];
}

// ── Output HTML ───────────────────────────────────────────────
require_once __DIR__ . '/includes/header.php';
?>

<!-- Phase 2 Custom Component Styles -->
<style>
  /* Distraction-free styling */
  #app-footer { display: none !important; }

  /* Option card styling */
  .ely-option-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border-subtle);
    border-radius: 12px;
    background: var(--surface-card);
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .ely-option-card:hover {
    border-color: var(--brand-primary);
    background: rgba(79, 70, 229, 0.04);
  }
  .ely-option-card.active {
    border: 2px solid var(--brand-primary) !important;
    background: #F4F3FF !important;
  }
  html.dark .ely-option-card.active {
    background: rgba(79, 70, 229, 0.18) !important;
    border-color: #6366F1 !important;
  }

  /* Glassmorphic reveal overlay */
  .ely-reveal-overlay {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    transition: max-height 0.4s ease, opacity 0.3s ease;
  }
  html.dark .ely-reveal-overlay {
    background: rgba(15, 23, 42, 0.92);
    border-color: var(--border-subtle);
  }

  /* Mentor chat drawer — hidden by default so students can focus on the
     course; slides in from the right when the floating toggle is clicked. */
  .tunnel-chat-drawer {
    background: var(--surface-card);
    border-radius: 1.25rem 0 0 1.25rem;
    position: fixed;
    top: 0;
    right: 0;
    height: 100vh;
    width: 360px;
    max-width: 90vw;
    z-index: 60;
    box-shadow: -12px 0 32px rgba(15, 23, 42, 0.18);
    transform: translateX(100%);
    visibility: hidden;
    transition: transform 0.3s ease, visibility 0.3s ease;
  }
  .tunnel-chat-drawer.drawer-open { transform: translateX(0); visibility: visible; }
  .tunnel-chat-backdrop {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.35); z-index: 55;
    opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
  }
  .tunnel-chat-backdrop.backdrop-open { opacity: 1; pointer-events: auto; }
</style>

<?php if ($show_pillar_complete && $completed_pillar): ?>
<!-- ══════════════════════════════════════════════════════════════
     FULL-SCREEN PILLAR COMPLETION MODAL
══════════════════════════════════════════════════════════════ -->
<div class="min-h-[85vh] flex items-center justify-center w-full py-12 px-4">
  <div class="max-w-xl w-full mx-auto">
    
    <div class="ely-card p-8 md:p-10 text-center relative overflow-hidden">
      <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600"></div>

      <!-- Celebration Header -->
      <div class="relative mb-6">
        <div class="w-20 h-20 mx-auto rounded-full bg-indigo-500/10 flex items-center justify-center text-4xl mb-4 animate-bounce">
          🎉
        </div>
        <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider">
          Pillar Completed!
        </span>
      </div>

      <h1 class="text-2xl md:text-3xl font-extrabold text-main font-display mb-4 leading-tight">
        <?php echo htmlspecialchars($completed_pillar['title']); ?>
      </h1>

      <!-- Mentor Encouragement Note Block -->
      <div class="p-5 bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl mb-8 text-left">
        <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2">
          <span>💬 Mentor Encouragement</span>
        </div>
        <p class="text-sm text-main leading-relaxed font-medium">
          <?php echo nl2br(htmlspecialchars($completed_pillar['congratulatory_note'] ?: '🌟 Congratulations on completing this pillar! Take a moment to reflect on your progress before continuing.')); ?>
        </p>
      </div>

      <!-- High-contrast Action CTA -->
      <?php if ($next_pillar_title): ?>
        <p class="text-xs text-muted mb-2 font-medium">Up next in your program:</p>
        <p class="text-sm font-bold text-main mb-6"><?php echo htmlspecialchars($next_pillar_title); ?></p>
        <a href="/tunnel.php" class="elysian-btn elysian-btn-brand px-8 py-3.5 text-sm font-bold shadow-lg inline-flex items-center gap-2">
          Begin Next Chapter →
        </a>
      <?php else: ?>
        <a href="/tunnel.php" class="elysian-btn elysian-btn-brand px-8 py-3.5 text-sm font-bold shadow-lg inline-flex items-center gap-2">
          Begin Next Chapter →
        </a>
      <?php endif; ?>
    </div>

    <!-- Pillar Progress Summary -->
    <div class="mt-6 grid grid-cols-<?php echo min(count($pillars), 4); ?> gap-2.5">
      <?php foreach ($pillars as $pil):
        $stats = $pillar_stats[$pil['id']] ?? ['total' => 0, 'done' => 0];
        $is_done = ($pil['id'] === $completed_pillar['id']);
        $pct = $stats['total'] > 0 ? round(($stats['done'] / $stats['total']) * 100) : 0;
      ?>
        <div class="p-3 rounded-xl border text-center text-xs <?php echo $is_done ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-900' : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800'; ?>">
          <div class="font-bold text-[10px] <?php echo $is_done ? 'text-indigo-600 dark:text-indigo-400' : 'text-muted'; ?> mb-1 uppercase tracking-wide truncate">
            <?php echo htmlspecialchars(substr($pil['title'], 0, 20)); ?>
          </div>
          <?php if ($is_done): ?>
            <span class="text-emerald-500 font-bold text-sm">✓</span>
          <?php else: ?>
            <div class="w-full bg-slate-200 dark:bg-slate-700 h-1 rounded-full mt-1">
              <div class="bg-indigo-500 h-full rounded-full" style="width:<?php echo $pct; ?>%"></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php else: ?>

<?php if ($isReview): ?>
<div class="max-w-7xl mx-auto px-4 pt-4">
  <div class="mb-2 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex flex-col sm:flex-row items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
      <span>📖</span>
      <span>Reviewing your completed coursework — read-only, nothing you view here changes your saved answers.</span>
    </div>
    <a href="/completed.php" class="elysian-btn elysian-btn-ghost text-xs font-bold px-3 py-1.5 whitespace-nowrap">
      ← Return to Summary
    </a>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     STICKY TOP SHELL & PROGRESS BAR
══════════════════════════════════════════════════════════════ -->
<div class="sticky top-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-subtle mb-6">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
    <div class="min-w-0">
      <span class="text-[10px] font-bold tracking-widest text-indigo-600 dark:text-indigo-400 uppercase block truncate">
        Pillar <?php echo $current_pillar_num; ?> of <?php echo $total_pillars; ?>
      </span>
      <h2 class="text-sm md:text-base font-bold text-main font-display truncate">
        <?php echo htmlspecialchars($currentComp['_pillar_title'] ?? $program['title']); ?>
      </h2>
    </div>
    <div class="flex items-center gap-3 text-xs font-semibold text-muted flex-shrink-0">
      <span class="bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full">Step <?php echo $active_index + 1; ?> of <?php echo count($visibleComponents); ?></span>
    </div>
  </div>
  <!-- Animated CSS Progress Bar -->
  <div class="w-full bg-slate-200 dark:bg-slate-800 h-[6px]">
    <div class="h-full bg-[var(--brand-primary)] transition-all duration-300 ease-out"
         style="width: <?php echo count($visibleComponents) > 0 ? round((($active_index + 1) / count($visibleComponents)) * 100) : 0; ?>%"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MAIN WORKSTATION LAYOUT
══════════════════════════════════════════════════════════════ -->
<div class="max-w-7xl mx-auto px-4 pb-24 w-full">

  <div class="flex flex-col lg:flex-row gap-6">


    <!-- 1. Left Pillar Navigation Sidebar -->
    <aside class="w-full lg:w-64 flex-shrink-0">
      <div class="ely-card p-4 sticky top-20">
        <h3 class="text-xs font-bold text-muted uppercase tracking-widest mb-1 truncate">
          <?php echo htmlspecialchars($program['title']); ?>
        </h3>
        <p class="text-[10px] text-muted mb-4 font-mono">Step <?php echo $active_index + 1; ?> of <?php echo count($visibleComponents); ?></p>
        
        <nav class="space-y-1.5">
          <?php foreach ($pillars as $p_idx => $pil):
            $stats      = $pillar_stats[$pil['id']] ?? ['total' => 0, 'done' => 0];
            $is_active  = ($currentComp && $currentComp['_pillar_id'] === $pil['id']);
            $is_done    = ($stats['done'] >= $stats['total'] && $stats['total'] > 0);
            $is_started = ($stats['done'] > 0);
            $pillar_first_idx = null;
            foreach ($visibleComponents as $vi => $vc) {
              if ($vc['_pillar_id'] === $pil['id']) { $pillar_first_idx = $vi; break; }
            }
            $can_jump = $isReview
                ? ($pillar_first_idx !== null && !$is_active)
                : ($pillar_first_idx !== null && $pillar_first_idx < $active_index && !$is_active);
          ?>
            <div class="flex items-start gap-2.5 px-3 py-2 rounded-xl text-xs font-medium transition-all duration-150 <?php echo $is_active ? 'bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold' : 'text-main hover:bg-slate-100 dark:hover:bg-slate-800 border border-transparent'; ?>">
              <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5 <?php echo $is_active ? 'bg-indigo-600 text-white' : ($is_done ? 'bg-emerald-500 text-white' : ($is_started ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-200 dark:bg-slate-700 text-muted')); ?>">
                <?php if ($is_done): ?>✓<?php else: echo $p_idx + 1; endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <?php if ($can_jump): ?>
                  <a href="/tunnel.php?<?php echo $isReview ? 'view' : 'jump_to'; ?>=<?php echo $pillar_first_idx; ?>" class="block hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate">
                    <?php echo htmlspecialchars($pil['title']); ?>
                  </a>
                <?php else: ?>
                  <span class="truncate block"><?php echo htmlspecialchars($pil['title']); ?></span>
                <?php endif; ?>
                <?php if ($stats['total'] > 0): ?>
                  <div class="w-full bg-slate-200 dark:bg-slate-700 h-1 rounded-full mt-1.5 overflow-hidden">
                    <div class="<?php echo $is_done ? 'bg-emerald-500' : 'bg-indigo-500'; ?> h-full rounded-full transition-all duration-500"
                         style="width:<?php echo round(($stats['done'] / $stats['total']) * 100); ?>%"></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </nav>
      </div>
    </aside>

    <!-- 2. Central Active Block Panel -->
    <main class="flex-1 min-w-0">
      <?php if ($currentComp): ?>
        <div class="max-w-2xl mx-auto">

          <?php if ($currentComp['_block_title'] && $currentComp['_block_title'] !== 'Main Assessment Block'): ?>
            <div class="flex items-center gap-2 mb-4">
              <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
              <span class="text-[10px] font-bold text-muted uppercase tracking-widest px-3">
                <?php echo htmlspecialchars($currentComp['_block_title']); ?>
              </span>
              <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
            </div>
          <?php endif; ?>

          <?php
          $cfg_data = !empty($currentComp['config']) ? json_decode($currentComp['config'], true) : (!empty($currentComp['options']) ? json_decode($currentComp['options'], true) : []);
          if (!is_array($cfg_data)) $cfg_data = [];

          $block_style = $cfg_data['style'] ?? 'standard';
          $heading_lvl = $cfg_data['heading_level'] ?? 'h3';

          $card_wrapper_cls = match($block_style) {
              'callout' => 'bg-sky-50/70 dark:bg-sky-950/30 border-l-4 border-l-sky-500 border border-sky-100 dark:border-sky-900/40 rounded-2xl p-6 md:p-8 shadow-sm',
              'warning' => 'bg-amber-50/70 dark:bg-amber-950/30 border-l-4 border-l-amber-500 border border-amber-100 dark:border-amber-900/40 rounded-2xl p-6 md:p-8 shadow-sm',
              'action'  => 'bg-emerald-50/70 dark:bg-emerald-950/30 border-2 border-emerald-500 rounded-2xl p-6 md:p-8 shadow-md',
              'minimal' => 'bg-transparent border-0 p-2 md:p-4',
              default   => 'ely-card p-6 md:p-8'
          };
          ?>
          <div class="<?php echo $card_wrapper_cls; ?>">
            <form method="POST" action="tunnel.php" id="main-form" enctype="multipart/form-data">
              <input type="hidden" name="submit_block" value="1">
              <?php if ($isReview): ?><fieldset disabled style="border:none; padding:0; margin:0;"><?php endif; ?>

              <?php
              $content_schema_raw = $currentComp['content_schema'] ?? '';
              $content_schema = (!empty($content_schema_raw)) ? json_decode($content_schema_raw, true) : null;

              if ($content_schema && is_array($content_schema) && count($content_schema) > 0):
                // ══ COMPOSITE BLOCK RENDERER ═════════════════════════════════════
                $curr_ans = $answers[$currentComp['id']] ?? '';
                $curr_comp_ans = is_array($curr_ans) ? $curr_ans : [];
                ?>
                <div class="space-y-5 mb-6">
                  <?php foreach ($content_schema as $elem_idx => $elem):
                    $elem_type = $elem['type'] ?? 'paragraph';
                    $elem_id   = $elem['id'] ?? ('sub_' . $elem_idx);
                    $sub_ans   = $curr_comp_ans[$elem_id] ?? '';
                  ?>
                    <?php if ($elem_type === 'heading'):
                      $tag = $elem['level'] ?? 'h3';
                      $htext = $elem['text'] ?? '';
                    ?>
                      <?php if ($tag === 'h1'): ?>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-main font-display leading-tight tracking-tight"><?php echo htmlspecialchars($htext); ?></h1>
                      <?php elseif ($tag === 'h2'): ?>
                        <h2 class="text-2xl md:text-3xl font-bold text-main font-display leading-snug tracking-tight"><?php echo htmlspecialchars($htext); ?></h2>
                      <?php elseif ($tag === 'h4'): ?>
                        <h4 class="text-lg md:text-xl font-semibold text-main font-display"><?php echo htmlspecialchars($htext); ?></h4>
                      <?php else: ?>
                        <h3 class="text-xl md:text-2xl font-bold text-main font-display leading-snug"><?php echo htmlspecialchars($htext); ?></h3>
                      <?php endif; ?>

                    <?php elseif ($elem_type === 'paragraph'): ?>
                      <p class="text-base text-main leading-relaxed font-normal"><?php echo nl2br(htmlspecialchars($elem['text'] ?? '')); ?></p>

                    <?php elseif ($elem_type === 'callout_box'):
                      $v_cls = match($elem['variant'] ?? 'insight') {
                        'warning' => 'bg-amber-500/10 border-l-4 border-l-amber-500 border border-amber-500/20 text-amber-950 dark:text-amber-200',
                        'action'  => 'bg-emerald-500/10 border-2 border-emerald-500 text-emerald-950 dark:text-emerald-200',
                        default   => 'bg-indigo-500/10 border-l-4 border-l-indigo-500 border border-indigo-500/20 text-indigo-950 dark:text-indigo-200'
                      };
                    ?>
                      <div class="p-4 rounded-xl text-sm font-medium <?php echo $v_cls; ?>">
                        <?php echo nl2br(htmlspecialchars($elem['text'] ?? '')); ?>
                      </div>

                    <?php elseif ($elem_type === 'input_short_answer'): ?>
                      <div class="space-y-1.5">
                        <?php if (!empty($elem['label'])): ?>
                          <label class="block text-xs font-bold text-main uppercase tracking-wider"><?php echo htmlspecialchars($elem['label']); ?><?php echo ($elem['required'] ?? true) ? ' <span class="text-red-500">*</span>' : ''; ?></label>
                        <?php endif; ?>
                        <input type="text" name="composite_ans[<?php echo $elem_id; ?>]" class="ely-input" value="<?php echo htmlspecialchars($sub_ans); ?>" placeholder="<?php echo htmlspecialchars($elem['placeholder'] ?? 'Type answer...'); ?>" <?php echo ($elem['required'] ?? true) ? 'required' : ''; ?>>
                      </div>

                    <?php elseif ($elem_type === 'input_free_text' || $elem_type === 'goal_statement'): ?>
                      <div class="space-y-1.5">
                        <?php if (!empty($elem['label'])): ?>
                          <label class="block text-xs font-bold text-main uppercase tracking-wider"><?php echo htmlspecialchars($elem['label']); ?><?php echo ($elem['required'] ?? true) ? ' <span class="text-red-500">*</span>' : ''; ?></label>
                        <?php endif; ?>
                        <textarea name="composite_ans[<?php echo $elem_id; ?>]" rows="4" class="ely-input" placeholder="<?php echo htmlspecialchars($elem['placeholder'] ?? 'Type reflection...'); ?>" <?php echo ($elem['required'] ?? true) ? 'required' : ''; ?>><?php echo htmlspecialchars($sub_ans); ?></textarea>
                      </div>

                    <?php elseif ($elem_type === 'input_dropdown'):
                      $dropdown_opts_raw = trim($elem['options'] ?? '');
                      if ($dropdown_opts_raw === '') $dropdown_opts_raw = 'Option A, Option B';
                      $opts_arr = array_filter(array_map('trim', explode(',', $dropdown_opts_raw)));
                    ?>
                      <div class="space-y-1.5">
                        <?php if (!empty($elem['label'])): ?>
                          <label class="block text-xs font-bold text-main uppercase tracking-wider"><?php echo htmlspecialchars($elem['label']); ?><?php echo ($elem['required'] ?? true) ? ' <span class="text-red-500">*</span>' : ''; ?></label>
                        <?php endif; ?>
                        <select name="composite_ans[<?php echo $elem_id; ?>]" class="ely-input cursor-pointer" <?php echo ($elem['required'] ?? true) ? 'required' : ''; ?>>
                          <option value="">Select option...</option>
                          <?php foreach ($opts_arr as $opt_val): ?>
                            <option value="<?php echo htmlspecialchars($opt_val); ?>" <?php echo ($sub_ans === $opt_val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt_val); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                    <?php elseif ($elem_type === 'rating_scale'): ?>
                      <div class="space-y-2 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-subtle">
                        <label class="block text-xs font-bold text-main uppercase tracking-wider"><?php echo htmlspecialchars($elem['label'] ?? 'Rating Scale'); ?></label>
                        <div class="flex justify-between text-[10px] font-bold text-muted">
                          <span>1 (<?php echo htmlspecialchars($elem['low_label'] ?? 'Low'); ?>)</span>
                          <span>5 (<?php echo htmlspecialchars($elem['high_label'] ?? 'High'); ?>)</span>
                        </div>
                        <div class="grid grid-cols-5 gap-2">
                          <?php for ($r=1; $r<=5; $r++): ?>
                            <label class="cursor-pointer">
                              <input type="radio" name="composite_ans[<?php echo $elem_id; ?>]" value="<?php echo $r; ?>" <?php echo ((string)$sub_ans === (string)$r) ? 'checked' : ''; ?> class="sr-only peer">
                              <div class="p-2.5 rounded-lg border border-subtle bg-white dark:bg-slate-800 text-center text-xs font-bold text-main peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 transition-all"><?php echo $r; ?></div>
                            </label>
                          <?php endfor; ?>
                        </div>
                      </div>

                    <?php elseif ($elem_type === 'resource_link'): ?>
                      <div class="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 rounded-xl flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                          <span class="text-xl">🔗</span>
                          <span class="text-xs font-bold text-main"><?php echo htmlspecialchars($elem['label'] ?? 'Resource Card'); ?></span>
                        </div>
                        <a href="<?php echo htmlspecialchars($elem['url'] ?? '#'); ?>" target="_blank" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors">
                          <?php echo htmlspecialchars($elem['button_text'] ?? 'Download PDF'); ?>
                        </a>
                      </div>

                    <?php elseif ($elem_type === 'trait_matrix'): ?>
                      <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl space-y-3">
                        <p class="text-xs font-bold text-main uppercase tracking-wider">🎯 <?php echo htmlspecialchars($elem['question'] ?? 'Trait Scoring Question'); ?></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                          <label class="p-3 bg-white dark:bg-slate-900 border border-subtle rounded-xl cursor-pointer flex items-center justify-between hover:border-amber-500">
                            <div class="flex items-center gap-2">
                              <input type="radio" name="composite_ans[<?php echo $elem_id; ?>]" value="<?php echo htmlspecialchars($elem['opt_a_trait'] ?? 'E'); ?>" <?php echo ($sub_ans === ($elem['opt_a_trait'] ?? 'E')) ? 'checked' : ''; ?> class="w-4 h-4 text-amber-600">
                              <span class="text-xs font-semibold text-main"><?php echo htmlspecialchars($elem['opt_a_label'] ?? 'Option A'); ?></span>
                            </div>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400"><?php echo htmlspecialchars($elem['opt_a_trait'] ?? 'E'); ?></span>
                          </label>
                          <label class="p-3 bg-white dark:bg-slate-900 border border-subtle rounded-xl cursor-pointer flex items-center justify-between hover:border-amber-500">
                            <div class="flex items-center gap-2">
                              <input type="radio" name="composite_ans[<?php echo $elem_id; ?>]" value="<?php echo htmlspecialchars($elem['opt_b_trait'] ?? 'I'); ?>" <?php echo ($sub_ans === ($elem['opt_b_trait'] ?? 'I')) ? 'checked' : ''; ?> class="w-4 h-4 text-amber-600">
                              <span class="text-xs font-semibold text-main"><?php echo htmlspecialchars($elem['opt_b_label'] ?? 'Option B'); ?></span>
                            </div>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400"><?php echo htmlspecialchars($elem['opt_b_trait'] ?? 'I'); ?></span>
                          </label>
                        </div>
                      </div>

                    <?php elseif ($elem_type === 'result_reveal'):
                      $profileCode   = strtoupper(trim($student['profile_code'] ?? ''));
                      $studentScores = !empty($student['raw_scores']) ? (json_decode($student['raw_scores'], true) ?: []) : [];
                      $evalRes       = evaluateAssessment($pdo, $program['scheme_id'] ?? 'mbti_16_types', $studentScores);
                      
                      $matchedProfile = null;
                      if (!empty($evalRes['archetype'])) {
                          $matchedProfile = [
                              'title' => $evalRes['archetype']['title'] ?? ($profileCode . ' Profile'),
                              'tagline' => $evalRes['archetype']['description'] ?? '',
                              'strengths' => $evalRes['archetype']['strengths'] ?? [],
                              'growth_areas' => $evalRes['archetype']['growth_areas'] ?? [],
                              'smart_goals' => $evalRes['archetype']['smart_goals'] ?? []
                          ];
                      }
                      if (!$matchedProfile && !empty($profileCode)) {
                          $matchedProfile = getProfileByCode($pdo, $profileCode);
                      }
                      if ($matchedProfile) {
                          echo renderResultRevealCard($matchedProfile, $profileCode);
                      } else {
                      ?>
                        <div class="p-6 rounded-2xl bg-slate-900 text-white shadow-xl space-y-3 text-center">
                          <div class="text-2xl">🔮</div>
                          <h4 class="text-xs font-extrabold uppercase tracking-widest text-indigo-400 font-mono">Profile Reveal Report</h4>
                          <h3 class="text-xl font-bold font-display"><?php echo htmlspecialchars($elem['title'] ?? 'Strategic Profile Outcome'); ?></h3>
                        </div>
                      <?php } ?>

                    <?php elseif ($elem_type === 'file_upload'):
                      $ftypes = $elem['file_types'] ?? '.pdf,.docx,.png,.jpg';
                    ?>
                      <div class="p-4 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-900/50 text-center">
                        <input type="file" id="file-sub-<?php echo $elem_id; ?>" class="hidden" accept="<?php echo htmlspecialchars($ftypes); ?>" onchange="document.getElementById('fn-sub-<?php echo $elem_id; ?>').textContent = this.files[0] ? ('Selected: ' + this.files[0].name) : ''; document.getElementById('fa-sub-<?php echo $elem_id; ?>').value = this.files[0] ? this.files[0].name : 'file_submitted';">
                        <label for="file-sub-<?php echo $elem_id; ?>" class="cursor-pointer flex flex-col items-center gap-1.5">
                          <span class="text-xl">📁</span>
                          <span class="text-xs font-bold text-main"><?php echo htmlspecialchars($elem['label'] ?? 'Upload File'); ?></span>
                          <span class="text-[10px] text-muted">Accepted: <?php echo htmlspecialchars($ftypes); ?></span>
                          <span id="fn-sub-<?php echo $elem_id; ?>" class="text-xs font-mono font-bold text-emerald-600"></span>
                        </label>
                        <input type="hidden" name="composite_ans[<?php echo $elem_id; ?>]" id="fa-sub-<?php echo $elem_id; ?>" value="<?php echo htmlspecialchars($sub_ans ?: 'file_attached'); ?>">
                      </div>

                    <?php elseif ($elem_type === 'video_embed'):
                      $vurl = trim($elem['url'] ?? '');
                    ?>
                      <div class="w-full aspect-video bg-slate-900 rounded-2xl overflow-hidden flex items-center justify-center relative shadow-lg my-2">
                        <?php if (!empty($vurl) && (strpos($vurl, 'http') === 0 || strpos($vurl, '<iframe') !== false)): ?>
                          <?php if (strpos($vurl, '<iframe') !== false): ?>
                            <?php echo $vurl; ?>
                          <?php else: ?>
                            <iframe class="w-full h-full" src="<?php echo htmlspecialchars($vurl); ?>" frameborder="0" allowfullscreen></iframe>
                          <?php endif; ?>
                        <?php else: ?>
                          <div class="text-center p-6 text-white font-mono text-xs">
                            <div class="text-2xl mb-1">▶</div>
                            Video Presentation Container
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <!-- Dummy answer field so composite block submit satisfies progress check -->
                <input type="hidden" name="answer" value="composite_submitted">

              <?php else: ?>
                <!-- Universal Hybrid Block Header: Heading Title & Body Explanation -->
                <?php if (!in_array($currentComp['type'], ['h1', 'h2', 'h3', 'h4'])): ?>
                  <div class="mb-5">
                    <?php if (!empty($currentComp['question'])): ?>
                      <?php if ($heading_lvl === 'h1'): ?>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-main font-display mb-2.5 leading-tight tracking-tight">
                          <?php echo htmlspecialchars($currentComp['question']); ?>
                        </h1>
                      <?php elseif ($heading_lvl === 'h2'): ?>
                        <h2 class="text-2xl md:text-3xl font-bold text-main font-display mb-2.5 leading-snug tracking-tight">
                          <?php echo htmlspecialchars($currentComp['question']); ?>
                        </h2>
                      <?php elseif ($heading_lvl === 'h4'): ?>
                        <h4 class="text-lg md:text-xl font-semibold text-main font-display mb-2">
                          <?php echo htmlspecialchars($currentComp['question']); ?>
                        </h4>
                      <?php else: ?>
                        <h3 class="text-xl md:text-2xl font-bold text-main font-display mb-2.5 leading-snug">
                          <?php echo htmlspecialchars($currentComp['question']); ?>
                        </h3>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($currentComp['placeholder']) && !in_array($currentComp['type'], ['video_embed'])): ?>
                      <p class="text-base text-main leading-relaxed font-normal">
                        <?php echo nl2br(htmlspecialchars($currentComp['placeholder'])); ?>
                      </p>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="mb-6">
                  <?php
                  $curr_ans = $answers[$currentComp['id']] ?? '';


                switch ($currentComp['type']) {

                  // ── Pure Content / Non-Interactive Blocks ──────────────────
                  case 'content_only':
                  case 'content_block':
                  case 'callout_box':
                  case 'paragraph':
                    ?>
                    <input type="hidden" name="answer" value="read">
                    <?php
                    break;

                  case 'video_embed':
                    $vid_url = trim($cfg_data['video_url'] ?? $currentComp['placeholder'] ?? '');
                    ?>
                    <div class="py-2 text-left">
                      <div class="w-full aspect-video bg-slate-900 rounded-2xl overflow-hidden flex items-center justify-center relative shadow-lg">
                        <?php if (!empty($vid_url) && (strpos($vid_url, 'http') === 0 || strpos($vid_url, '<iframe') !== false)): ?>
                          <?php if (strpos($vid_url, '<iframe') !== false): ?>
                            <?php echo $vid_url; ?>
                          <?php else: ?>
                            <iframe class="w-full h-full" src="<?php echo htmlspecialchars($vid_url); ?>" frameborder="0" allowfullscreen></iframe>
                          <?php endif; ?>
                        <?php else: ?>
                          <div class="flex flex-col items-center gap-3 text-slate-400 p-6 text-center">
                            <div class="w-14 h-14 rounded-full bg-white/10 flex items-center justify-center text-2xl text-white">▶</div>
                            <span class="text-sm font-semibold text-white">Media Presentation Container</span>
                            <span class="text-xs font-mono opacity-60"><?php echo htmlspecialchars($vid_url ?: 'Video URL or Embed Code'); ?></span>
                          </div>
                        <?php endif; ?>
                      </div>
                      <input type="hidden" name="answer" value="watched">
                    </div>
                    <?php
                    break;

                  case 'resource_link':
                    $r_url  = $cfg_data['resource_url'] ?? '#';
                    $r_btn  = $cfg_data['resource_button'] ?? 'Download Resource';
                    ?>
                    <div class="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/50 rounded-2xl flex items-center justify-between gap-4">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg">📄</div>
                        <div>
                          <span class="text-sm font-bold text-main block">Resource File / Guide</span>
                          <span class="text-xs text-muted font-mono"><?php echo htmlspecialchars($r_url); ?></span>
                        </div>
                      </div>
                      <a href="<?php echo htmlspecialchars($r_url); ?>" target="_blank" class="elysian-btn elysian-btn-brand px-4 py-2 text-xs font-bold shadow-sm">
                        <?php echo htmlspecialchars($r_btn); ?>
                      </a>
                    </div>
                    <input type="hidden" name="answer" value="downloaded">
                    <?php
                    break;

                  // ── Typography ──────────────────────────────────────────
                  case 'h1':
                    echo '<h1 class="text-3xl md:text-4xl font-extrabold text-main font-display mb-3 leading-tight tracking-tight">' . nl2br(htmlspecialchars($currentComp['question'])) . '</h1><input type="hidden" name="answer" value="read">';
                    break;
                  case 'h2':
                    echo '<h2 class="text-2xl md:text-3xl font-bold text-main font-display mb-3 leading-snug tracking-tight">' . nl2br(htmlspecialchars($currentComp['question'])) . '</h2><input type="hidden" name="answer" value="read">';
                    break;
                  case 'h3':
                    echo '<h3 class="text-xl md:text-2xl font-bold text-main font-display mb-2">' . nl2br(htmlspecialchars($currentComp['question'])) . '</h3><input type="hidden" name="answer" value="read">';
                    break;
                  case 'h4':
                    echo '<h4 class="text-lg md:text-xl font-semibold text-main font-display mb-2">' . nl2br(htmlspecialchars($currentComp['question'])) . '</h4><input type="hidden" name="answer" value="read">';
                    break;

                  // ── Interactive Text Inputs ─────────────────────────────
                  case 'free_text':
                    ?>
                    <textarea name="answer" rows="5" class="ely-input" placeholder="Type your response here..." <?php echo $currentComp['required'] ? 'required' : ''; ?>><?php echo htmlspecialchars(is_array($curr_ans) ? '' : $curr_ans); ?></textarea>
                    <?php
                    break;

                  case 'short_answer':
                    ?>
                    <input type="text" name="answer" class="ely-input" value="<?php echo htmlspecialchars(is_array($curr_ans) ? '' : $curr_ans); ?>" placeholder="Type your answer here..." <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                    <?php
                    break;

                  case 'goal':
                    ?>
                    <textarea name="answer" rows="4" class="ely-input" placeholder="Write your goal here..." <?php echo $currentComp['required'] ? 'required' : ''; ?>><?php echo htmlspecialchars(is_array($curr_ans) ? '' : $curr_ans); ?></textarea>
                    <p class="mt-2 text-[10px] font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                      <span class="w-3.5 h-3.5 rounded-full bg-amber-500/20 border border-amber-500/40 inline-flex items-center justify-center text-[8px]">📌</span>
                      This response will appear in your Goals Worksheet at the end of your program.
                    </p>
                    <?php
                    break;

                  case 'number_input':
                    $num_min  = $cfg_data['num_min'] ?? '';
                    $num_max  = $cfg_data['num_max'] ?? '';
                    $num_unit = $cfg_data['num_unit'] ?? '';
                    ?>
                    <input type="number" name="answer" class="ely-input" value="<?php echo htmlspecialchars(is_array($curr_ans) ? '' : $curr_ans); ?>" <?php if ($num_min !== '') echo "min=\"$num_min\""; ?> <?php if ($num_max !== '') echo "max=\"$num_max\""; ?> placeholder="Enter numeric value <?php echo $num_unit ? '(' . htmlspecialchars($num_unit) . ')' : ''; ?>..." <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                    <?php
                    break;

                  case 'date_picker':
                    ?>
                    <input type="date" name="answer" class="ely-input cursor-pointer" value="<?php echo htmlspecialchars(is_array($curr_ans) ? '' : $curr_ans); ?>" <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                    <?php
                    break;

                  // ── Selection Controls ───────────────────────────────────
                  case 'dropdown':
                    $opts = !empty($currentComp['options']) ? (is_array($currentComp['options']) ? $currentComp['options'] : json_decode($currentComp['options'], true)) : [];
                    ?>
                    <select name="answer" class="ely-input cursor-pointer" <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                      <option value="" disabled <?php echo empty($curr_ans) ? 'selected' : ''; ?>>-- Select an option --</option>
                      <?php foreach ($opts as $opt): 
                        $val = $opt['value'] ?? $opt['label'];
                      ?>
                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($curr_ans === $val) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($opt['label']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                  case 'checklist':
                    $opts = !empty($currentComp['options']) ? (is_array($currentComp['options']) ? $currentComp['options'] : json_decode($currentComp['options'], true)) : [];
                    $curr_arr = is_array($curr_ans) ? $curr_ans : (json_decode($curr_ans, true) ?: []);
                    ?>
                    <div class="space-y-2.5">
                      <?php foreach ($opts as $opt): 
                        $val = $opt['value'] ?? $opt['label'];
                      ?>
                        <label class="flex items-center gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-surface cursor-pointer hover:bg-slate-50 transition-colors">
                          <input type="checkbox" name="answer[]" value="<?php echo htmlspecialchars($val); ?>" <?php echo in_array($val, $curr_arr) ? 'checked' : ''; ?> class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                          <span class="text-sm font-medium text-main"><?php echo htmlspecialchars($opt['label']); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <?php
                    break;

                  case 'radio_buttons':
                    $opts = !empty($currentComp['options']) ? (is_array($currentComp['options']) ? $currentComp['options'] : json_decode($currentComp['options'], true)) : [];
                    ?>
                    <div class="space-y-2.5">
                      <?php foreach ($opts as $opt): 
                        $val = $opt['value'] ?? $opt['label'];
                      ?>
                        <label class="flex items-center gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-surface cursor-pointer hover:bg-slate-50 transition-colors">
                          <input type="radio" name="answer" value="<?php echo htmlspecialchars($val); ?>" <?php echo ($curr_ans === $val) ? 'checked' : ''; ?> class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                          <span class="text-sm font-medium text-main"><?php echo htmlspecialchars($opt['label']); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <?php
                    break;

                  case 'file_upload':
                    $ftypes = $cfg_data['file_types'] ?? '.pdf,.docx,.png,.jpg';
                    $fmax = $cfg_data['file_max_size'] ?? '10MB';
                    ?>
                    <div class="p-6 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl text-center bg-slate-50/50 dark:bg-slate-900/50">
                      <input type="file" id="file-up-<?php echo $currentComp['id']; ?>" class="hidden" accept="<?php echo htmlspecialchars($ftypes); ?>" onchange="document.getElementById('fn-<?php echo $currentComp['id']; ?>').textContent = this.files[0] ? ('Selected: ' + this.files[0].name) : ''; document.getElementById('fa-<?php echo $currentComp['id']; ?>').value = this.files[0] ? this.files[0].name : 'file_submitted';">
                      <label for="file-up-<?php echo $currentComp['id']; ?>" class="cursor-pointer flex flex-col items-center gap-2">
                        <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-xl">📁</div>
                        <span class="text-sm font-bold text-main">Click to select a file to upload</span>
                        <span class="text-xs text-muted">Accepted: <?php echo htmlspecialchars($ftypes); ?> (Max <?php echo htmlspecialchars($fmax); ?>)</span>
                        <span id="fn-<?php echo $currentComp['id']; ?>" class="text-xs font-mono font-bold text-emerald-600 mt-1"></span>
                      </label>
                    </div>
                    <input type="hidden" name="answer" id="fa-<?php echo $currentComp['id']; ?>" value="<?php echo htmlspecialchars(is_array($curr_ans) ? '' : ($curr_ans ?: 'file_attached')); ?>">
                    <?php
                    break;

                  case 'rating_scale':
                    $rmax = (int)($cfg_data['rating_max'] ?? 5);
                    if ($rmax < 2) $rmax = 5;
                    $rlow = $cfg_data['rating_low'] ?? 'Low';
                    $rhigh = $cfg_data['rating_high'] ?? 'High';
                    ?>
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 rounded-2xl">
                      <div class="flex justify-between text-xs text-muted font-mono font-bold mb-3">
                        <span>1 (<?php echo htmlspecialchars($rlow); ?>)</span>
                        <span><?php echo $rmax; ?> (<?php echo htmlspecialchars($rhigh); ?>)</span>
                      </div>
                      <div class="grid grid-cols-5 gap-2.5">
                        <?php for ($r=1; $r<=$rmax; $r++): ?>
                          <label class="cursor-pointer">
                            <input type="radio" name="answer" value="<?php echo $r; ?>" <?php echo ((string)$curr_ans === (string)$r) ? 'checked' : ''; ?> class="peer sr-only" <?php echo $currentComp['required'] ? 'required' : ''; ?>>
                            <div class="py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-surface text-center font-bold text-main text-xs peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500 transition-all shadow-sm">
                              <?php echo $r; ?>
                            </div>
                          </label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <?php
                    break;

                  // ── Card-Based Option Selectors (Branching / Single Scoring) ──
                  case 'branching':
                  case 'scoring':
                    echo '<div class="space-y-3">';
                    if ($currentComp['options']) {
                      $opts = is_array($currentComp['options']) ? $currentComp['options'] : json_decode($currentComp['options'], true);
                      foreach ($opts as $opt) {
                        $chk = ($curr_ans === $opt['value']) ? 'checked' : '';
                        $isSel = $chk ? 'active' : '';
                        $dotBg = $chk ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300';
                        $dotHidden = $chk ? '' : 'hidden';
                        echo "
                        <label class=\"ely-option-card $isSel\">
                          <div class=\"flex items-center gap-3.5\">
                            <div class=\"ely-dot w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 $dotBg\">
                              <div class=\"ely-dot-inner w-2 h-2 rounded-full bg-white $dotHidden\"></div>
                            </div>
                            <span class=\"text-sm font-semibold text-main\">" . htmlspecialchars($opt['label']) . "</span>
                          </div>
                          <input type=\"radio\" name=\"answer\" value=\"" . htmlspecialchars($opt['value']) . "\" $chk class=\"sr-only\" required onchange=\"elysianRadioSelect(this)\">
                        </label>";
                      }
                    }
                    echo '</div>';
                    break;

                  // ── Card-Based Option Selectors (Scoring Block) ─────────
                  case 'scoring_block':
                    $sb_config    = $currentComp['options'] ?: [];
                    $curr_sb_ans  = is_array($curr_ans) ? $curr_ans : [];
                    $saved_codes  = $curr_sb_ans['sq'] ?? [];
                    $saved_reveal = $curr_sb_ans['revealed'] ?? '';
                    ?>
                    <div id="scoring-block-container" class="space-y-6">
                      <?php foreach ($sb_config as $q_idx => $question):
                        $saved_code = $saved_codes[$q_idx] ?? '';
                      ?>
                        <div class="scoring-question-wrap" id="sq-wrap-<?php echo $q_idx; ?>">
                          <p class="text-sm font-bold text-main mb-3">
                            <span class="text-indigo-600 dark:text-indigo-400 font-black mr-1.5"><?php echo $q_idx + 1; ?>.</span>
                            <?php echo htmlspecialchars($question['question'] ?? ''); ?>
                          </p>
                          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($question['options'] as $opt_key => $opt):
                              $code = $opt['code'] ?? '';
                              $is_selected = ($saved_code === $code);
                            ?>
                              <label class="sq-option-card ely-option-card <?php echo $is_selected ? 'active' : ''; ?>"
                                     data-q="<?php echo $q_idx; ?>" data-code="<?php echo htmlspecialchars($code); ?>">
                                <div class="sq-dot ely-dot w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 <?php echo $is_selected ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300'; ?>">
                                  <div class="w-1.5 h-1.5 rounded-full bg-white <?php echo $is_selected ? '' : 'hidden'; ?>"></div>
                                </div>
                                <div class="flex-1">
                                  <span class="text-xs font-semibold text-main"><?php echo htmlspecialchars($opt['label'] ?? ''); ?></span>
                                </div>
                                <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex-shrink-0"><?php echo htmlspecialchars($code); ?></span>
                                <input type="radio" name="sq[<?php echo $q_idx; ?>]"
                                       value="<?php echo htmlspecialchars($code); ?>"
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       class="sr-only sq-radio"
                                       data-q="<?php echo $q_idx; ?>">
                              </label>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>

                      <!-- Progress tracker -->
                      <div id="sq-progress-bar" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-subtle rounded-xl flex items-center gap-3">
                        <div class="flex gap-1.5 flex-1">
                          <?php for ($pi = 0; $pi < 4; $pi++):
                            $filled = isset($saved_codes[$pi]) && $saved_codes[$pi] !== '';
                          ?>
                            <div id="sq-pip-<?php echo $pi; ?>" class="h-1.5 flex-1 rounded-full transition-all duration-300 <?php echo $filled ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'; ?>"></div>
                          <?php endfor; ?>
                        </div>
                        <span id="sq-progress-text" class="text-[10px] font-bold text-muted font-mono whitespace-nowrap">
                          <?php echo count($saved_codes); ?> / 4
                        </span>
                      </div>

                      <!-- Inline Glassmorphic Result Reveal Overlay -->
                      <?php
                      $reveal_rules = $currentComp['reveal_rules'] ?? [];
                      if (!$reveal_rules) {
                          $raw_opts = $currentComp['options'];
                          if (is_string($raw_opts)) {
                              $decoded = json_decode($raw_opts, true);
                              if (isset($decoded['reveal_rules'])) {
                                  $reveal_rules = $decoded['reveal_rules'];
                              }
                          }
                      }
                      $reveal_json = json_encode($reveal_rules ?: []);
                      $all_filled = (count($saved_codes) === 4 && $saved_reveal !== '');
                      ?>
                      <div id="sb-reveal-panel" class="ely-reveal-overlay p-6 transition-all duration-500 <?php echo $all_filled ? '' : 'hidden'; ?>">
                        <div id="sb-reveal-inner">
                          <?php if ($all_filled): ?>
                            <?php
                            $revealCode = strtoupper($saved_reveal);
                            $matchedProfile = getProfileByCode($pdo, $revealCode);
                            if ($matchedProfile):
                              $p_s = $matchedProfile['strengths'] ?? [];
                              $p_g = $matchedProfile['growth_areas'] ?? $matchedProfile['weaknesses'] ?? [];
                              $p_gl= $matchedProfile['smart_goals'] ?? $matchedProfile['suggested_goals'] ?? [];
                            ?>
                              <div class="flex items-center justify-between mb-4">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-amber-500 dark:text-amber-400 font-mono">Your Profile Revealed</span>
                                <span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                                  <?php echo htmlspecialchars($revealCode); ?>
                                </span>
                              </div>
                              <h3 class="text-xl font-bold text-main font-display mb-4"><?php echo htmlspecialchars($matchedProfile['title']); ?></h3>
                              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                                  <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest block mb-1.5">Strengths</span>
                                  <?php foreach ($p_s as $s): ?>
                                    <div class="text-xs text-main flex items-center gap-1.5 mb-1"><span class="text-emerald-500">✓</span><?php echo htmlspecialchars($s); ?></div>
                                  <?php endforeach; ?>
                                </div>
                                <div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
                                  <span class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest block mb-1.5">Growth Areas</span>
                                  <?php foreach ($p_g as $g): ?>
                                    <div class="text-xs text-main flex items-center gap-1.5 mb-1"><span class="text-indigo-500">→</span><?php echo htmlspecialchars($g); ?></div>
                                  <?php endforeach; ?>
                                </div>
                              </div>
                              <?php if ($p_gl): ?>
                                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20">
                                  <span class="text-[9px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest block mb-2">Prescribed SMART Goals</span>
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <?php foreach ($p_gl as $gi => $gl): ?>
                                      <div class="text-xs text-main flex items-start gap-2">
                                        <span class="w-4 h-4 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[9px] font-bold flex items-center justify-center flex-shrink-0 mt-0.5"><?php echo $gi+1; ?></span>
                                        <?php echo htmlspecialchars($gl); ?>
                                      </div>
                                    <?php endforeach; ?>
                                  </div>
                                </div>
                              <?php endif; ?>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <script>
                    window._sbRevealRules = <?php echo $reveal_json; ?>;
                    window._sbTotalQuestions = <?php echo count($sb_config); ?>;
                    window._sbSavedCodes = <?php echo json_encode($saved_codes); ?>;
                    </script>
                    <?php
                    break;

                  // ── Result Reveal ───────────────────────────────────────
                  case 'result_reveal':
                    $profileCode   = strtoupper(trim($student['profile_code']));
                    $studentScores = !empty($student['raw_scores']) ? (json_decode($student['raw_scores'], true) ?: []) : [];
                    $evalRes       = evaluateAssessment($pdo, $program['scheme_id'] ?? 'mbti_16_types', $studentScores);
                    
                    $matchedProfile = null;
                    if (!empty($evalRes['archetype'])) {
                        $matchedProfile = [
                            'title' => $evalRes['archetype']['title'] ?? ($profileCode . ' Profile'),
                            'tagline' => $evalRes['archetype']['description'] ?? '',
                            'strengths' => $evalRes['archetype']['strengths'] ?? [],
                            'growth_areas' => $evalRes['archetype']['growth_areas'] ?? [],
                            'smart_goals' => $evalRes['archetype']['smart_goals'] ?? []
                        ];
                    }
                    if (!$matchedProfile && !empty($profileCode)) {
                        $matchedProfile = getProfileByCode($pdo, $profileCode);
                    }
                    if (!$matchedProfile): ?>
                      <div class="text-center py-10 flex flex-col items-center gap-4 bg-slate-900/90 text-white rounded-2xl border border-slate-800 p-8 shadow-xl">
                        <div class="w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                        <div>
                          <h4 class="text-base font-bold font-display text-white">Compiling Strategic Profile</h4>
                          <p class="text-slate-400 text-xs mt-1">Four-letter code: <span class="font-mono text-indigo-400 font-bold"><?php echo htmlspecialchars($profileCode ?: 'Pending...'); ?></span></p>
                        </div>
                      </div>
                    <?php else:
                      $p_goals = $matchedProfile['smart_goals'] ?? $matchedProfile['suggested_goals'] ?? [];
                    ?>
                      <div class="space-y-6">
                        <?php echo renderResultRevealCard($matchedProfile, $profileCode); ?>
                        <div class="flex flex-col gap-2 pt-2">
                          <label class="elysian-label text-main font-semibold">
                            Custom Strategic Roadmap (Review & customize your finalized goals below):
                          </label>
                          <textarea name="answer" rows="5" class="ely-input font-sans text-xs leading-relaxed" required
                            placeholder="Review and customize your 4 strategic SMART goals..."><?php echo htmlspecialchars(
                            is_array($curr_ans) ? '' : ($curr_ans ?: implode("\n", array_map(function($g, $i) { return ($i+1).". ".$g; }, $p_goals, array_keys($p_goals))))
                          ); ?></textarea>
                        </div>
                      </div>
                    <?php endif;
                    break;

                }
                ?>
              </div>
              <?php endif; ?>

              <?php if ($isReview): ?></fieldset><?php endif; ?>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center text-muted py-12">All tasks complete. Finalizing strategy...</div>
      <?php endif; ?>
    </main>

    <!-- Floating toggle for the mentor chat drawer — hidden by default so
         students can focus on the course content; pops open on demand. -->
    <button type="button" onclick="toggleTunnelChat()" id="tunnel-chat-toggle-btn" style="padding:0;"
            class="fixed bottom-24 right-6 z-[56] elysian-btn elysian-btn-brand shadow-lg rounded-full w-14 h-14 flex items-center justify-center text-lg" aria-label="Chat with your mentor">
      💬
      <span id="tunnel-chat-count"
            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center <?php echo count($chat_messages) === 0 ? 'hidden' : ''; ?>">
        <?php echo count($chat_messages); ?>
      </span>
    </button>

    <div id="tunnel-chat-backdrop" class="tunnel-chat-backdrop" onclick="toggleTunnelChat()"></div>

    <!-- 3. Right Chat Support Drawer (hidden until opened) -->
    <aside id="tunnel-chat-drawer" class="tunnel-chat-drawer">
      <div class="h-full flex flex-col">
        <div class="p-4 bg-slate-900 text-white flex items-center justify-between gap-2.5 border-b border-slate-800 flex-shrink-0">
          <div class="flex items-center gap-2.5">
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
            <div>
              <h4 class="text-xs font-bold font-display uppercase tracking-wider">Elysian Advisor Chat</h4>
              <p class="text-[9px] text-slate-400 font-medium">Direct line to your program mentor</p>
            </div>
          </div>
          <button type="button" onclick="toggleTunnelChat()" class="text-slate-400 hover:text-white transition-colors flex-shrink-0" aria-label="Close chat">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div id="chat-messages-container" class="flex-1 p-4 overflow-y-auto flex flex-col gap-3 bg-slate-50/50 dark:bg-slate-900/50">
          <?php if (count($chat_messages) === 0): ?>
            <div class="text-center py-12 text-muted text-[11px] italic">No messages yet. Send a message to contact your mentor.</div>
          <?php else: ?>
            <?php foreach ($chat_messages as $msg): ?>
              <?php $is_student = ($msg['sender'] === 'student'); ?>
              <div class="<?php echo $is_student ? 'chat-bubble-student' : 'chat-bubble-mentor'; ?>">
                <div class="text-[8px] opacity-60 font-bold uppercase tracking-wider mb-0.5"><?php echo htmlspecialchars($msg['sender_label']); ?></div>
                <div class="leading-relaxed"><?php echo htmlspecialchars($msg['content']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <form id="chat-form" method="POST" action="tunnel.php" class="p-3 border-t border-subtle flex gap-2 bg-surface flex-shrink-0">
          <input type="hidden" name="send_chat" value="1">
          <input type="text" id="chat-input" name="chat_content"
                 placeholder="Ask a question..."
                 class="ely-input text-xs"
                 required autocomplete="off">
          <button type="submit" class="elysian-btn elysian-btn-brand p-2 rounded-xl flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
          </button>
        </form>
      </div>
    </aside>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     STICKY BOTTOM ACTION & NAVIGATION BAR
══════════════════════════════════════════════════════════════ -->
<div class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-subtle py-3 px-4 shadow-lg">
  <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">

  <?php if ($isReview): ?>
    <!-- Review mode: browse only, no save/advance -->
    <?php if ($active_index > 0): ?>
      <a href="/tunnel.php?view=<?php echo $active_index - 1; ?>" class="elysian-btn elysian-btn-ghost text-xs font-bold px-4 py-2.5 rounded-xl">
        ← Previous
      </a>
    <?php else: ?>
      <button type="button" disabled class="elysian-btn elysian-btn-ghost text-xs font-bold px-4 py-2.5 rounded-xl opacity-40 cursor-not-allowed">
        ← Previous
      </button>
    <?php endif; ?>

    <a href="/completed.php" class="text-[11px] font-semibold text-muted hover:text-main transition-colors">
      Reviewing (read-only) — Return to Summary
    </a>

    <?php if ($active_index < count($visibleComponents) - 1): ?>
      <a href="/tunnel.php?view=<?php echo $active_index + 1; ?>" class="elysian-btn elysian-btn-brand px-6 py-2.5 text-xs font-bold shadow-md flex items-center gap-2">
        Next →
      </a>
    <?php else: ?>
      <a href="/completed.php" class="elysian-btn elysian-btn-brand px-6 py-2.5 text-xs font-bold shadow-md flex items-center gap-2">
        Done Reviewing →
      </a>
    <?php endif; ?>

  <?php else: ?>
    <!-- ← Back button -->
    <?php if ($active_index > 0): ?>
      <button type="submit" name="go_back" value="1" form="back-form" class="elysian-btn elysian-btn-ghost text-xs font-bold px-4 py-2.5 rounded-xl">
        ← Back
      </button>
    <?php else: ?>
      <button type="button" disabled class="elysian-btn elysian-btn-ghost text-xs font-bold px-4 py-2.5 rounded-xl opacity-40 cursor-not-allowed">
        ← Back
      </button>
    <?php endif; ?>

    <!-- Auto-save draft status indicator -->
    <div id="ely-save-status" class="ely-badge-status text-[11px] font-semibold text-muted flex items-center gap-1.5 font-mono">
      <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
      <span>Draft Saved</span>
    </div>

    <!-- Save & Advance → Primary CTA -->
    <?php if ($currentComp['type'] === 'scoring_block'): ?>
      <button type="submit" form="main-form" id="sb-submit-btn"
        class="elysian-btn elysian-btn-brand px-6 py-2.5 text-xs font-bold shadow-md flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
        Complete Assessment →
      </button>
    <?php elseif (in_array($currentComp['type'], ['content_only', 'content_block', 'video_embed', 'callout_box', 'resource_link', 'h1', 'h2', 'h3', 'h4', 'paragraph', 'result_reveal', 'composite'])): ?>
      <button type="submit" form="main-form" class="elysian-btn elysian-btn-brand px-6 py-2.5 text-xs font-bold shadow-md flex items-center gap-2">
        Continue →
      </button>
    <?php else: ?>
      <button type="submit" form="main-form" class="elysian-btn elysian-btn-brand px-6 py-2.5 text-xs font-bold shadow-md flex items-center gap-2">
        Save & Advance →
      </button>
    <?php endif; ?>
  <?php endif; ?>

  </div>
</div>

<?php endif; ?>

<!-- Hidden Back form -->
<form id="back-form" method="POST" action="tunnel.php" style="display:none;">
  <input type="hidden" name="go_back" value="1">
</form>

<script>
// ── Radio Card Selection JS ──────────────────────────────────
function elysianRadioSelect(radio) {
  const form = radio.closest('form');
  form.querySelectorAll('.ely-option-card').forEach(card => {
    card.classList.remove('active');
    const dot = card.querySelector('.ely-dot');
    const inner = card.querySelector('.ely-dot-inner');
    if (dot) {
      dot.classList.remove('border-indigo-600', 'bg-indigo-600');
      dot.classList.add('border-slate-300');
    }
    if (inner) inner.classList.add('hidden');
  });
  const label = radio.closest('.ely-option-card');
  if (label) {
    label.classList.add('active');
    const dot = label.querySelector('.ely-dot');
    const inner = label.querySelector('.ely-dot-inner');
    if (dot) {
      dot.classList.remove('border-slate-300');
      dot.classList.add('border-indigo-600', 'bg-indigo-600');
    }
    if (inner) inner.classList.remove('hidden');
  }
}

// ── Auto-save Draft Handling JS ──────────────────────────────
(function() {
  let autosaveTimeout = null;
  function triggerAutosave() {
    const statusEl = document.getElementById('ely-save-status');
    if (statusEl) {
      statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400 animate-ping inline-block"></span> <span>Saving...</span>';
    }
    clearTimeout(autosaveTimeout);
    autosaveTimeout = setTimeout(() => {
      const form = document.getElementById('main-form');
      if (!form) return;
      const compId = "<?php echo $currentComp['id'] ?? ''; ?>";
      const answerInput = form.querySelector('[name="answer"]');
      if (!compId || !answerInput) return;

      const fd = new FormData();
      fd.append('autosave', '1');
      fd.append('block_id', compId);
      fd.append('answer', answerInput.value);

      fetch('tunnel.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'saved' && statusEl) {
            statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> <span>Draft Saved</span>';
          }
        })
        .catch(() => {
          if (statusEl) {
            statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> <span>Unsaved</span>';
          }
        });
    }, 600);
  }

  document.querySelectorAll('#main-form input, #main-form textarea, #main-form select').forEach(el => {
    el.addEventListener('input', triggerAutosave);
    el.addEventListener('change', triggerAutosave);
  });
})();

// ── Scoring Block & Reveal Overlay JS ────────────────────────
(function() {
  const totalQ = window._sbTotalQuestions || 4;
  const savedCodes = window._sbSavedCodes || [];
  let selectedCodes = [...savedCodes];

  function updatePips() {
    for (let i = 0; i < 4; i++) {
      const pip = document.getElementById('sq-pip-' + i);
      if (!pip) continue;
      const filled = selectedCodes[i] && selectedCodes[i] !== '';
      pip.classList.toggle('bg-indigo-600', filled);
      pip.classList.toggle('bg-slate-200', !filled);
    }
    const txt = document.getElementById('sq-progress-text');
    const filled = selectedCodes.filter(c => c && c !== '').length;
    if (txt) txt.textContent = filled + ' / ' + totalQ;

    const submitBtn = document.getElementById('sb-submit-btn');
    if (filled === totalQ) {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50','cursor-not-allowed');
      }
      showReveal();
    } else {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50','cursor-not-allowed');
      }
    }
  }

  function showReveal() {
    const code = selectedCodes.slice(0, 4).join('').toUpperCase();
    const panel = document.getElementById('sb-reveal-panel');
    const inner = document.getElementById('sb-reveal-inner');
    if (!panel || !inner) return;

    if (panel.classList.contains('hidden')) {
      panel.classList.remove('hidden');
      fetch('/tunnel.php?get_reveal=1&code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
          if (data.html) {
            inner.innerHTML = data.html;
          }
        })
        .catch(() => {});
    }
  }

  document.querySelectorAll('.sq-option-card').forEach(card => {
    card.addEventListener('click', function() {
      const q     = parseInt(this.dataset.q);
      const code  = this.dataset.code;
      const radio = this.querySelector('.sq-radio');
      if (radio) radio.checked = true;
      selectedCodes[q] = code;

      const wrap = document.getElementById('sq-wrap-' + q);
      if (wrap) {
        wrap.querySelectorAll('.sq-option-card').forEach(c => {
          const isThis = (c === this);
          c.classList.toggle('active', isThis);
          const dot = c.querySelector('.sq-dot');
          if (dot) {
            dot.classList.toggle('border-indigo-600', isThis);
            dot.classList.toggle('bg-indigo-600', isThis);
            dot.classList.toggle('border-slate-300', !isThis);
          }
          const innerDot = c.querySelector('.sq-dot div');
          if (innerDot) innerDot.classList.toggle('hidden', !isThis);
        });
      }
      updatePips();
    });
  });

  updatePips();
})();

// ── Chat Polling & Auto-scroll JS ────────────────────────────
function scrollChatToBottom() {
  const c = document.getElementById('chat-messages-container');
  if (c) c.scrollTop = c.scrollHeight;
}
function pollMessages() {
  fetch('tunnel.php?fetch_chat=1')
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('chat-messages-container');
      if (!c) return;

      const countEl = document.getElementById('tunnel-chat-count');
      if (countEl) {
        countEl.textContent = data.length;
        countEl.classList.toggle('hidden', data.length === 0);
      }

      if (data.length === 0) {
        c.innerHTML = '<div class="text-center py-12 text-muted text-[11px] italic">No messages yet. Send a message to contact your mentor.</div>';
        return;
      }
      let html = '';
      data.forEach(msg => {
        const isStudent = (msg.sender === 'student');
        const bubble = isStudent ? 'chat-bubble-student' : 'chat-bubble-mentor';
        html += `<div class="${bubble}"><div class="text-[8px] opacity-60 font-bold uppercase tracking-wider mb-0.5">${escapeHtml(msg.sender_label)}</div><div class="leading-relaxed">${escapeHtml(msg.content)}</div></div>`;
      });
      if (c.innerHTML !== html) { c.innerHTML = html; scrollChatToBottom(); }
    })
    .catch(e => console.error('Chat poll failed', e));
}
function escapeHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str)));
  return d.innerHTML;
}
document.getElementById('chat-form')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const input = document.getElementById('chat-input');
  const val = input.value.trim();
  if (!val) return;
  const fd = new FormData();
  fd.append('send_chat', '1');
  fd.append('chat_content', val);
  input.value = '';
  fetch('tunnel.php?ajax=1', { method: 'POST', body: fd })
    .then(r => r.json()).then(() => pollMessages())
    .catch(e => console.error('Chat send failed', e));
});
setInterval(pollMessages, 5000);

// Chat drawer is hidden by default so students can focus on the course;
// opens on demand via the floating 💬 toggle button.
function toggleTunnelChat() {
  const drawer = document.getElementById('tunnel-chat-drawer');
  const backdrop = document.getElementById('tunnel-chat-backdrop');
  if (!drawer || !backdrop) return;
  const opening = !drawer.classList.contains('drawer-open');
  drawer.classList.toggle('drawer-open');
  backdrop.classList.toggle('backdrop-open');
  if (opening) scrollChatToBottom();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
