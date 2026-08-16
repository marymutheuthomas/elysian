<?php
// mentor/index.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/profiles.php';
require_once __DIR__ . '/../includes/evaluation_engine.php';
require_once __DIR__ . '/../includes/component_type.php';
require_once __DIR__ . '/../includes/component_archive.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_msg = '';

// Handle Mentor Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($password === 'elysian2026') {
        $_SESSION['mentor_logged_in'] = true;
        header("Location: /mentor/index.php");
        exit;
    } else {
        $error_msg = 'Incorrect admin access password.';
    }
}

// Authorization guard
$is_logged_in = isset($_SESSION['mentor_logged_in']) && $_SESSION['mentor_logged_in'] === true;

if ($is_logged_in) {
    // Auto-migration: ensure config and content_schema columns exist on components table
    try { $pdo->exec("ALTER TABLE `components` ADD COLUMN `config` TEXT NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE `components` ADD COLUMN `content_schema` LONGTEXT NULL"); } catch (Throwable $t) {}

    // Auto-migration: trait evaluation tables and program/student schema columns
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `trait_schemes` (
            `scheme_id` VARCHAR(50) NOT NULL PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `scheme_type` VARCHAR(50) NOT NULL DEFAULT 'dominant_trait',
            `description` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS `traits` (
            `trait_id` VARCHAR(50) NOT NULL PRIMARY KEY,
            `scheme_id` VARCHAR(50) NOT NULL,
            `code` VARCHAR(50) NOT NULL,
            `label` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            CONSTRAINT `fk_traits_schemes` FOREIGN KEY (`scheme_id`) REFERENCES `trait_schemes` (`scheme_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `archetypes` (
            `archetype_id` VARCHAR(50) NOT NULL PRIMARY KEY,
            `scheme_id` VARCHAR(50) NOT NULL,
            `trigger_conditions_json` TEXT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `badge_url` VARCHAR(255) NULL,
            CONSTRAINT `fk_archetypes_schemes` FOREIGN KEY (`scheme_id`) REFERENCES `trait_schemes` (`scheme_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $t) {}

    // Auto-migration: archive table for content deleted out from under students' answers
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `component_archive` (
            `id` VARCHAR(50) NOT NULL PRIMARY KEY,
            `pillar_title` VARCHAR(255) NULL,
            `type` VARCHAR(50) NULL,
            `question` TEXT NULL,
            `options` TEXT NULL,
            `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $t) {}

    try { $pdo->exec("ALTER TABLE `programs` ADD COLUMN `scheme_id` VARCHAR(50) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE `students` ADD COLUMN `raw_scores` TEXT NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE `students` ADD COLUMN `archetype_id` VARCHAR(50) NULL"); } catch (Throwable $t) {}

    // Seed default MBTI scheme and traits if not present
    try {
        $pdo->exec("INSERT INTO `trait_schemes` (`scheme_id`, `name`, `scheme_type`, `description`) VALUES
            ('mbti_16_types', '16-Personality Archetype Scheme (MBTI)', 'dominant_trait', 'Evaluates Extraversion/Introversion, Sensing/Intuition, Thinking/Feeling, and Judging/Perceiving.')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");

        $traits_seed = [
            ['mbti_e', 'E', 'Extraversion', 'Focuses energy outward toward people and action.'],
            ['mbti_i', 'I', 'Introversion', 'Focuses energy inward toward ideas and reflection.'],
            ['mbti_s', 'S', 'Sensing', 'Processes concrete facts and practical details.'],
            ['mbti_n', 'N', 'Intuition', 'Processes abstract possibilities and strategic patterns.'],
            ['mbti_t', 'T', 'Thinking', 'Makes decisions using objective logic.'],
            ['mbti_f', 'F', 'Feeling', 'Makes decisions using personal values and empathy.'],
            ['mbti_j', 'J', 'Judging', 'Prefers structure, planning, and closure.'],
            ['mbti_p', 'P', 'Perceiving', 'Prefers flexibility, spontaneity, and open options.']
        ];
        $st_tr = $pdo->prepare("INSERT INTO `traits` (`trait_id`, `scheme_id`, `code`, `label`, `description`) VALUES (?, 'mbti_16_types', ?, ?, ?) ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)");
        foreach ($traits_seed as $ts) {
            $st_tr->execute($ts);
        }

        // Seed 16 Archetypes into archetypes table if empty
        $cnt_arch = (int)$pdo->query("SELECT COUNT(*) FROM `archetypes` WHERE `scheme_id` = 'mbti_16_types'")->fetchColumn();
        if ($cnt_arch === 0) {
            global $master_profiles;
            $st_arch = $pdo->prepare("INSERT INTO `archetypes` (`archetype_id`, `scheme_id`, `trigger_conditions_json`, `title`, `description`) VALUES (?, 'mbti_16_types', ?, ?, ?)");
            foreach ($master_profiles as $m_code => $m_data) {
                $cond = json_encode(['code' => $m_code]);
                $st_arch->execute(['mbti_' . strtolower($m_code), $cond, $m_data['title'], "Strategic profile for " . $m_data['title']]);
            }
        }

        // Ensure default program has scheme_id set
        $pdo->exec("UPDATE `programs` SET `scheme_id` = 'mbti_16_types' WHERE `scheme_id` IS NULL OR `scheme_id` = ''");
    } catch (Throwable $t) {}

    // Auto-cleanup: remove unassigned/orphaned components that lack a valid block or pillar
    // (archive first — this runs on every page load and would otherwise silently
    // destroy question context for any student who already answered one of these)
    try {
        $pdo->exec("INSERT INTO `component_archive` (`id`, `pillar_title`, `type`, `question`, `options`)
            SELECT c.id, p.title, c.type, c.question, c.options
            FROM `components` c LEFT JOIN `pillars` p ON c.pillar_id = p.id
            WHERE c.block_id IS NULL OR c.block_id = '' OR c.pillar_id NOT IN (SELECT `id` FROM `pillars`)
            ON DUPLICATE KEY UPDATE pillar_title=VALUES(pillar_title), type=VALUES(type), question=VALUES(question), options=VALUES(options), deleted_at=CURRENT_TIMESTAMP");
        $pdo->exec("DELETE FROM `components` WHERE `block_id` IS NULL OR `block_id` = '' OR `pillar_id` NOT IN (SELECT `id` FROM `pillars`)");
    } catch (Throwable $t) {}

    // ─── ACTION HANDLERS ──────────────────────────────────────────────────────
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // Action: Approve Payment
    if ($action === 'approve_payment') {
        $pay_id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!empty($pay_id)) {
            $stmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
            $stmt->execute([$pay_id]);
            $payment = $stmt->fetch();
            if ($payment) {
                try {
                    $pdo->beginTransaction();
                    
                    // Verify payment
                    $upd_pay = $pdo->prepare("UPDATE `payments` SET `status` = 'verified', `verified_at` = NOW() WHERE `id` = ?");
                    $upd_pay->execute([$pay_id]);
                    
                    // Set student status to active
                    $upd_stud = $pdo->prepare("UPDATE `students` SET `status` = 'active' WHERE `permanent_id` = ?");
                    $upd_stud->execute([$payment['student_permanent_id']]);
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                }
            }
        }
        header("Location: /mentor/index.php?tab=payments");
        exit;
    }

    // Action: Reject Payment
    if ($action === 'reject_payment') {
        $pay_id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!empty($pay_id)) {
            $upd = $pdo->prepare("UPDATE `payments` SET `status` = 'rejected' WHERE `id` = ?");
            $upd->execute([$pay_id]);
        }
        header("Location: /mentor/index.php?tab=payments");
        exit;
    }

    // Action: Toggle Program Active Status
    if ($action === 'toggle_program') {
        $prog_id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!empty($prog_id)) {
            $stmt = $pdo->prepare("SELECT `is_active` FROM `programs` WHERE `id` = ?");
            $stmt->execute([$prog_id]);
            $prog = $stmt->fetch();
            if ($prog) {
                $new_status = $prog['is_active'] ? 0 : 1;
                $upd = $pdo->prepare("UPDATE `programs` SET `is_active` = ? WHERE `id` = ?");
                $upd->execute([$new_status, $prog_id]);
            }
        }
        header("Location: /mentor/index.php?tab=programs");
        exit;
    }

    // Action: Delete Program
    if ($action === 'delete_program') {
        $prog_id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!empty($prog_id)) {
            // Snapshot component context before it's gone, so students' answers stay meaningful.
            $pdo->prepare("INSERT INTO `component_archive` (`id`, `pillar_title`, `type`, `question`, `options`)
                SELECT c.id, p.title, c.type, c.question, c.options
                FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id
                WHERE p.program_id = ?
                ON DUPLICATE KEY UPDATE pillar_title=VALUES(pillar_title), type=VALUES(type), question=VALUES(question), options=VALUES(options), deleted_at=CURRENT_TIMESTAMP")
                ->execute([$prog_id]);
            $pdo->prepare("DELETE FROM `components` WHERE `pillar_id` IN (SELECT `id` FROM `pillars` WHERE `program_id` = ?)")->execute([$prog_id]);
            $pdo->prepare("DELETE FROM `blocks` WHERE `pillar_id` IN (SELECT `id` FROM `pillars` WHERE `program_id` = ?)")->execute([$prog_id]);
            $pdo->prepare("DELETE FROM `pillars` WHERE `program_id` = ?")->execute([$prog_id]);
            $pdo->prepare("DELETE FROM `programs` WHERE `id` = ?")->execute([$prog_id]);
        }
        header("Location: /mentor/index.php?tab=programs");
        exit;
    }

    // Action: Save Program (Add or Edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_program'])) {
        $prog_id = isset($_POST['program_id']) ? trim($_POST['program_id']) : '';
        $code = isset($_POST['code']) ? trim($_POST['code']) : '';
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0.0;
        $duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
        $outcomes_text = isset($_POST['outcomes']) ? trim($_POST['outcomes']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $scheme_id = isset($_POST['scheme_id']) ? trim($_POST['scheme_id']) : 'mbti_16_types';

        // Parse outcomes from newlines
        $outcomes_arr = array_filter(array_map('trim', explode("\n", $outcomes_text)));
        $outcomes_json = json_encode(array_values($outcomes_arr));

        if (!empty($prog_id)) {
            // Edit existing program
            $stmt = $pdo->prepare("UPDATE `programs` SET `code` = ?, `title` = ?, `description` = ?, `outcomes` = ?, `fee` = ?, `duration` = ?, `is_active` = ?, `scheme_id` = ? WHERE `id` = ?");
            $stmt->execute([$code, $title, $description, $outcomes_json, $fee, $duration, $is_active, $scheme_id, $prog_id]);
        } else {
            // Add new program
            $new_id = 'PROG-' . time();
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO `programs` (`id`, `code`, `title`, `description`, `outcomes`, `fee`, `duration`, `is_active`, `scheme_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$new_id, $code, $title, $description, $outcomes_json, $fee, $duration, $is_active, $scheme_id]);

                // Insert default pillar and default block to make it valid immediately
                $pill_id = 'PILL-' . time() . '-1';
                $stmt_pill = $pdo->prepare("INSERT INTO `pillars` (`id`, `program_id`, `title`, `description`) VALUES (?, ?, 'Pillar 1: Introduction Assessment', 'Define your preliminary parameters.')");
                $stmt_pill->execute([$pill_id, $new_id]);

                $blk_id = 'BLK-' . time() . '-1';
                $stmt_blk = $pdo->prepare("INSERT INTO `components` (`id`, `pillar_id`, `type`, `question`, `placeholder`, `required`) VALUES (?, ?, 'free_text', 'What is your primary goal for this program?', 'Describe your objectives...', 1)");
                $stmt_blk->execute([$blk_id, $pill_id]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        header("Location: /mentor/index.php?tab=programs");
        exit;
    }

    // Action: Save Pillar (Add or Edit) — with congratulatory_note
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pillar'])) {
        $p_program_id  = isset($_POST['p_program_id']) ? trim($_POST['p_program_id']) : '';
        $p_pillar_id   = isset($_POST['p_pillar_id']) ? trim($_POST['p_pillar_id']) : '';
        $p_title       = isset($_POST['p_title']) ? trim($_POST['p_title']) : '';
        $p_cong_note   = isset($_POST['p_congratulatory_note']) ? trim($_POST['p_congratulatory_note']) : '';
        $p_sort_order  = isset($_POST['p_sort_order']) ? (int)$_POST['p_sort_order'] : 0;

        if (!empty($p_pillar_id)) {
            // Edit
            $stmt = $pdo->prepare("UPDATE `pillars` SET `title` = ?, `congratulatory_note` = ?, `sort_order` = ? WHERE `id` = ?");
            $stmt->execute([$p_title, $p_cong_note, $p_sort_order, $p_pillar_id]);
        } else {
            // Add
            $new_pillar_id = 'PILL-' . time();
            // Get highest sort_order for this program
            $stmt_ord = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM `pillars` WHERE `program_id` = ?");
            $stmt_ord->execute([$p_program_id]);
            $auto_sort = (int)$stmt_ord->fetchColumn();
            $cong_default = '🌟 Congratulations! You have completed the "' . $p_title . '" pillar. Excellent work — keep the momentum going!';
            $stmt = $pdo->prepare("INSERT INTO `pillars` (`id`, `program_id`, `title`, `congratulatory_note`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$new_pillar_id, $p_program_id, $p_title, $p_cong_note ?: $cong_default, $auto_sort]);
            // Create a default block for the new pillar
            $def_blk_id = 'DEFBLK-' . $new_pillar_id;
            $pdo->prepare("INSERT INTO `blocks` (`id`, `pillar_id`, `title`, `sort_order`) VALUES (?, ?, 'Main Assessment Block', 1)")
                ->execute([$def_blk_id, $new_pillar_id]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($p_program_id));
        exit;
    }

    // Action: Save Named Block (the 3rd tier container)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_named_block'])) {
        $nb_pillar_id   = isset($_POST['nb_pillar_id']) ? trim($_POST['nb_pillar_id']) : '';
        $nb_block_id    = isset($_POST['nb_block_id']) ? trim($_POST['nb_block_id']) : '';
        $nb_title       = isset($_POST['nb_title']) ? trim($_POST['nb_title']) : 'Untitled Block';
        $nb_program_id  = isset($_POST['nb_program_id']) ? trim($_POST['nb_program_id']) : '';

        if (!empty($nb_block_id)) {
            // Edit existing named block
            $pdo->prepare("UPDATE `blocks` SET `title` = ? WHERE `id` = ?")->execute([$nb_title, $nb_block_id]);
        } else {
            // Create new named block
            $new_nb_id = 'BLK-' . time() . '-' . rand(10,99);
            $stmt_ord  = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM `blocks` WHERE `pillar_id` = ?");
            $stmt_ord->execute([$nb_pillar_id]);
            $auto_ord  = (int)$stmt_ord->fetchColumn();
            $pdo->prepare("INSERT INTO `blocks` (`id`, `pillar_id`, `title`, `sort_order`) VALUES (?, ?, ?, ?)")
                ->execute([$new_nb_id, $nb_pillar_id, $nb_title, $auto_ord]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($nb_program_id));
        exit;
    }

    // Action: Delete Named Block
    if ($action === 'delete_named_block') {
        $nb_id     = isset($_GET['id']) ? $_GET['id'] : '';
        $nb_prog   = isset($_GET['program_id']) ? $_GET['program_id'] : '';
        if (!empty($nb_id)) {
            // Snapshot component context before it's gone, so students' answers stay meaningful.
            $pdo->prepare("INSERT INTO `component_archive` (`id`, `pillar_title`, `type`, `question`, `options`)
                SELECT c.id, p.title, c.type, c.question, c.options
                FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id
                WHERE c.block_id = ?
                ON DUPLICATE KEY UPDATE pillar_title=VALUES(pillar_title), type=VALUES(type), question=VALUES(question), options=VALUES(options), deleted_at=CURRENT_TIMESTAMP")
                ->execute([$nb_id]);
            // Delete all components belonging to this block, then delete the block
            $pdo->prepare("DELETE FROM `components` WHERE `block_id` = ?")->execute([$nb_id]);
            $pdo->prepare("DELETE FROM `blocks` WHERE `id` = ?")->execute([$nb_id]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($nb_prog));
        exit;
    }

    // Action: AJAX Reorder Named Block
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_named_block'])) {
        $rbl_id  = $_POST['block_id'] ?? '';
        $rbl_dir = $_POST['direction'] ?? '';
        if ($rbl_id && in_array($rbl_dir, ['up','down'])) {
            $stmt = $pdo->prepare("SELECT `sort_order`, `pillar_id` FROM `blocks` WHERE `id` = ?");
            $stmt->execute([$rbl_id]);
            $rbl = $stmt->fetch();
            if ($rbl) {
                $cur_ord = (int)$rbl['sort_order'];
                $pil_id  = $rbl['pillar_id'];
                $target_ord = ($rbl_dir === 'up') ? $cur_ord - 1 : $cur_ord + 1;
                // Swap
                $pdo->prepare("UPDATE `blocks` SET `sort_order` = ? WHERE `pillar_id` = ? AND `sort_order` = ?")
                    ->execute([$cur_ord, $pil_id, $target_ord]);
                $pdo->prepare("UPDATE `blocks` SET `sort_order` = ? WHERE `id` = ?")
                    ->execute([$target_ord, $rbl_id]);
            }
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // Action: AJAX Batch Reorder Components (Batch optimization using MySQL 8.0 ON DUPLICATE KEY UPDATE)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_reorder_components'])) {
        header('Content-Type: application/json');
        $raw_json = $_POST['components_json'] ?? '';
        $items = json_decode($raw_json, true);

        if (is_array($items) && !empty($items)) {
            $placeholders = [];
            $params = [];
            foreach ($items as $item) {
                $comp_id  = trim($item['id'] ?? '');
                $block_id = !empty($item['block_id']) ? trim($item['block_id']) : null;
                $sort_ord = (int)($item['sort_order'] ?? 0);
                if (!empty($comp_id)) {
                    $placeholders[] = "(?, ?, ?)";
                    $params[] = $comp_id;
                    $params[] = $block_id;
                    $params[] = $sort_ord;
                }
            }

            if (!empty($placeholders)) {
                $sql = "INSERT INTO `components` (`id`, `block_id`, `sort_order`) VALUES " 
                     . implode(', ', $placeholders) 
                     . " ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`), `block_id` = VALUES(`block_id`)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            echo json_encode(['status' => 'success', 'updated_count' => count($placeholders)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or empty component payload']);
        }
        exit;
    }


    // Action: Delete Pillar
    if ($action === 'delete_pillar') {
        $p_id = isset($_GET['id']) ? $_GET['id'] : '';
        $p_prog_id = isset($_GET['program_id']) ? $_GET['program_id'] : '';
        if (!empty($p_id)) {
            // Snapshot component context before it's gone, so students' answers stay meaningful.
            $pdo->prepare("INSERT INTO `component_archive` (`id`, `pillar_title`, `type`, `question`, `options`)
                SELECT c.id, p.title, c.type, c.question, c.options
                FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id
                WHERE c.pillar_id = ?
                ON DUPLICATE KEY UPDATE pillar_title=VALUES(pillar_title), type=VALUES(type), question=VALUES(question), options=VALUES(options), deleted_at=CURRENT_TIMESTAMP")
                ->execute([$p_id]);
            $pdo->prepare("DELETE FROM `components` WHERE `pillar_id` = ?")->execute([$p_id]);
            $pdo->prepare("DELETE FROM `blocks` WHERE `pillar_id` = ?")->execute([$p_id]);
            $pdo->prepare("DELETE FROM `pillars` WHERE `id` = ?")->execute([$p_id]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($p_prog_id));
        exit;
    }

    // Action: Save Component — 4-tier aware (knows its named block + sort_order)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_block'])) {
        $b_program_id    = isset($_POST['b_program_id']) ? trim($_POST['b_program_id']) : '';
        $b_pillar_id     = isset($_POST['b_pillar_id']) ? trim($_POST['b_pillar_id']) : '';
        $b_named_blk_id  = isset($_POST['b_named_block_id']) ? trim($_POST['b_named_block_id']) : null;
        $b_block_id      = isset($_POST['b_block_id']) ? trim($_POST['b_block_id']) : '';  // component id

        // Fallback resolution for pillar_id if empty
        if (empty($b_pillar_id) && !empty($b_named_blk_id)) {
            $stmt_np = $pdo->prepare("SELECT `pillar_id` FROM `blocks` WHERE `id` = ?");
            $stmt_np->execute([$b_named_blk_id]);
            $b_pillar_id = $stmt_np->fetchColumn() ?: '';
        }
        if (empty($b_pillar_id) && !empty($b_program_id)) {
            $stmt_pp = $pdo->prepare("SELECT `id` FROM `pillars` WHERE `program_id` = ? ORDER BY `sort_order` ASC LIMIT 1");
            $stmt_pp->execute([$b_program_id]);
            $b_pillar_id = $stmt_pp->fetchColumn() ?: '';
        }
        $b_content_schema_raw = isset($_POST['b_content_schema']) ? trim($_POST['b_content_schema']) : '';
        // No explicit type selector is submitted by the composite block builder — the
        // real type is inferred from content_schema below, once it's been decoded.
        $b_type          = isset($_POST['b_type']) ? trim($_POST['b_type']) : '';
        $b_question      = isset($_POST['b_question']) ? trim($_POST['b_question']) : '';
        $b_placeholder   = isset($_POST['b_placeholder']) ? trim($_POST['b_placeholder']) : '';
        $b_required      = isset($_POST['b_required']) ? 1 : 0;
        $b_show_if       = isset($_POST['b_show_if']) ? trim($_POST['b_show_if']) : '';
        $b_options       = isset($_POST['b_options']) ? trim($_POST['b_options']) : '';

        // For scoring_block type: compile the 4-question structure into options JSON
        if ($b_type === 'scoring_block') {
            $sb_questions = isset($_POST['sb_questions']) ? $_POST['sb_questions'] : [];
            $sb_structured = [];
            foreach ($sb_questions as $qidx => $qdata) {
                $sb_structured[] = [
                    'question' => trim($qdata['title'] ?? ''),
                    'options'  => [
                        ['label' => trim($qdata['a_label'] ?? ''), 'code' => strtoupper(trim($qdata['a_code'] ?? ''))],
                        ['label' => trim($qdata['b_label'] ?? ''), 'code' => strtoupper(trim($qdata['b_code'] ?? ''))],
                    ],
                ];
            }
            $reveal_rules_raw = isset($_POST['reveal_rules']) ? $_POST['reveal_rules'] : [];
            $reveal_rules = [];
            foreach ($reveal_rules_raw as $rdata) {
                $r_code = strtoupper(trim($rdata['code'] ?? ''));
                if (!$r_code) continue;
                $reveal_rules[] = [
                    'code'       => $r_code,
                    'title'      => trim($rdata['title'] ?? ''),
                    'strengths'  => trim($rdata['strengths'] ?? ''),
                    'weaknesses' => trim($rdata['weaknesses'] ?? ''),
                    'goals'      => array_filter(array_map('trim', explode("\n", $rdata['goals'] ?? ''))),
                ];
            }
            $b_options = json_encode($sb_structured);
            // Store reveal_rules in placeholder field (reuse to avoid schema change) as JSON
            $b_placeholder = json_encode(['reveal_rules' => $reveal_rules]);
        }

        // Validate JSON fields
        $show_if_val = null;
        if (!empty($b_show_if)) {
            $dec = json_decode($b_show_if, true);
            if (json_last_error() === JSON_ERROR_NONE) $show_if_val = $b_show_if;
        }
        $options_val = null;
        if (!empty($b_options)) {
            $dec = json_decode($b_options, true);
            if (json_last_error() === JSON_ERROR_NONE) $options_val = $b_options;
        }

        $b_config = $_POST['b_config'] ?? '';
        $config_val = null;
        if (!empty($b_config)) {
            $dec = json_decode($b_config, true);
            if (json_last_error() === JSON_ERROR_NONE) $config_val = $b_config;
        }

        $content_schema_val = null;
        $decoded_schema_for_type = null;
        if (!empty($b_content_schema_raw)) {
            $dec_cs = json_decode($b_content_schema_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec_cs) && count($dec_cs) > 0) {
                $content_schema_val = $b_content_schema_raw;
                $decoded_schema_for_type = $dec_cs;
                if (empty($b_question)) {
                    foreach ($dec_cs as $elem) {
                        if (!empty($elem['text'])) { $b_question = $elem['text']; break; }
                        if (!empty($elem['label'])) { $b_question = $elem['label']; break; }
                        if (!empty($elem['question'])) { $b_question = $elem['question']; break; }
                        if (!empty($elem['title'])) { $b_question = $elem['title']; break; }
                    }
                    if (empty($b_question)) $b_question = 'Custom Component Block';
                }
            }
        }

        // Auto-infer `type` from the content_schema's primary element rather than
        // silently defaulting to a stale/incorrect legacy value.
        if (empty($b_type)) {
            $b_type = inferComponentType($decoded_schema_for_type);
        }

        if (!empty($b_block_id)) {
            // Edit existing component
            $stmt = $pdo->prepare("UPDATE `components` SET `type` = ?, `question` = ?, `placeholder` = ?, `required` = ?, `show_if` = ?, `options` = ?, `config` = ?, `content_schema` = ?, `block_id` = ? WHERE `id` = ?");
            $stmt->execute([$b_type, $b_question, $b_placeholder, $b_required, $show_if_val, $options_val, $config_val, $content_schema_val, $b_named_blk_id ?: null, $b_block_id]);
        } else {
            // Add new component
            $new_comp_id = 'COMP-' . time() . '-' . rand(10,99);
            $stmt_ord = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM `components` WHERE `pillar_id` = ?");
            $stmt_ord->execute([$b_pillar_id]);
            $auto_comp_ord = (int)$stmt_ord->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO `components` (`id`, `pillar_id`, `block_id`, `type`, `question`, `placeholder`, `required`, `show_if`, `options`, `config`, `content_schema`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$new_comp_id, $b_pillar_id, $b_named_blk_id ?: null, $b_type, $b_question, $b_placeholder, $b_required, $show_if_val, $options_val, $config_val, $content_schema_val, $auto_comp_ord]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($b_program_id));
        exit;
    }

    // Action: Delete Component / Block
    if ($action === 'delete_block') {
        $b_id = isset($_GET['id']) ? $_GET['id'] : '';
        $b_prog_id = isset($_GET['program_id']) ? $_GET['program_id'] : '';
        if (!empty($b_id)) {
            // Snapshot component context before it's gone, so students' answers stay meaningful.
            $pdo->prepare("INSERT INTO `component_archive` (`id`, `pillar_title`, `type`, `question`, `options`)
                SELECT c.id, p.title, c.type, c.question, c.options
                FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id
                WHERE c.id = ?
                ON DUPLICATE KEY UPDATE pillar_title=VALUES(pillar_title), type=VALUES(type), question=VALUES(question), options=VALUES(options), deleted_at=CURRENT_TIMESTAMP")
                ->execute([$b_id]);
            $stmt = $pdo->prepare("DELETE FROM `components` WHERE `id` = ?");
            $stmt->execute([$b_id]);
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($b_prog_id));
        exit;
    }

    // Action: Duplicate Component
    if ($action === 'duplicate_block') {
        $b_id      = isset($_GET['id']) ? $_GET['id'] : '';
        $b_prog_id = isset($_GET['program_id']) ? $_GET['program_id'] : '';
        if (!empty($b_id)) {
            $stmt = $pdo->prepare("SELECT * FROM `components` WHERE `id` = ?");
            $stmt->execute([$b_id]);
            $src = $stmt->fetch();
            if ($src) {
                $new_blk_id = 'COMP-' . time() . '-' . rand(10, 99);
                $ins = $pdo->prepare("INSERT INTO `components` (`id`, `pillar_id`, `block_id`, `type`, `question`, `placeholder`, `required`, `show_if`, `options`, `config`, `content_schema`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $new_blk_id,
                    $src['pillar_id'],
                    $src['block_id'],
                    $src['type'],
                    $src['question'] . ' (Copy)',
                    $src['placeholder'],
                    $src['required'],
                    $src['show_if'],
                    $src['options'],
                    $src['config'] ?? null,
                    $src['content_schema'] ?? null,
                    ($src['sort_order'] ?? 0) + 1,
                ]);
            }
        }
        header("Location: /mentor/index.php?tab=pillars&selected_program_id=" . urlencode($b_prog_id));
        exit;
    }

    // Action: Send Mentor Chat Message
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_mentor_chat'])) {
        $student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
        $chat_content = isset($_POST['chat_content']) ? trim($_POST['chat_content']) : '';
        
        if (!empty($student_id) && !empty($chat_content)) {
            $msg_id = 'MSG-' . time() . '-' . rand(10, 99);
            $stmt = $pdo->prepare("INSERT INTO `messages` (`id`, `thread_id`, `sender`, `sender_label`, `content`) VALUES (?, ?, 'mentor', 'Elysian Mentor', ?)");
            $stmt->execute([$msg_id, $student_id, $chat_content]);
        }

        if (isset($_GET['ajax'])) {
            echo json_encode(['status' => 'success']);
            exit;
        }

        header("Location: /mentor/index.php?tab=students&selected_student_id=" . urlencode($student_id));
        exit;
    }

    // Action: Save Student (Add or Edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student'])) {
        $stud_id           = isset($_POST['stud_id']) ? trim($_POST['stud_id']) : '';
        $stud_name         = isset($_POST['stud_name']) ? trim($_POST['stud_name']) : '';
        $stud_email        = isset($_POST['stud_email']) ? trim($_POST['stud_email']) : '';
        $stud_status       = isset($_POST['stud_status']) ? trim($_POST['stud_status']) : 'program_selection';
        $stud_prog_id      = isset($_POST['stud_prog_id']) ? trim($_POST['stud_prog_id']) : null;
        $stud_profile_code = isset($_POST['stud_profile_code']) ? trim($_POST['stud_profile_code']) : '';
        $stud_ttid         = isset($_POST['stud_ttid']) ? trim($_POST['stud_ttid']) : null;
        $stud_active_block = isset($_POST['stud_active_block']) ? (int)$_POST['stud_active_block'] : 0;
        $is_edit           = isset($_POST['is_edit']) ? (int)$_POST['is_edit'] : 0;

        if (empty($stud_id) || empty($stud_name) || empty($stud_email)) {
            $_SESSION['student_error'] = 'UIN Reference, Name, and Email are required fields.';
            header("Location: /mentor/index.php?tab=students" . ($is_edit ? "&edit_student_id=" . urlencode($stud_id) : "&add_student=1"));
            exit;
        }

        if ($is_edit) {
            // Update student
            $stmt = $pdo->prepare("UPDATE `students` SET `name` = ?, `email` = ?, `status` = ?, `selected_program_id` = ?, `active_block_index` = ?, `profile_code` = ?, `ttid` = ? WHERE `permanent_id` = ?");
            $stmt->execute([
                $stud_name,
                $stud_email,
                $stud_status,
                !empty($stud_prog_id) ? $stud_prog_id : null,
                $stud_active_block,
                $stud_profile_code,
                !empty($stud_ttid) ? $stud_ttid : null,
                $stud_id
            ]);
        } else {
            // Check if permanent_id already exists
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM `students` WHERE `permanent_id` = ?");
            $stmt_check->execute([$stud_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['student_error'] = 'UIN Reference already exists. Please choose a unique identifier.';
                header("Location: /mentor/index.php?tab=students&add_student=1");
                exit;
            }

            // Insert new student
            $stmt = $pdo->prepare("INSERT INTO `students` (`permanent_id`, `name`, `email`, `status`, `selected_program_id`, `active_block_index`, `profile_code`, `ttid`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $stud_id,
                $stud_name,
                $stud_email,
                $stud_status,
                !empty($stud_prog_id) ? $stud_prog_id : null,
                $stud_active_block,
                $stud_profile_code,
                !empty($stud_ttid) ? $stud_ttid : null
            ]);
        }

        header("Location: /mentor/index.php?tab=students&selected_student_id=" . urlencode($stud_id));
        exit;
    }

    // Action: Delete Student
    if ($action === 'delete_student') {
        $student_id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!empty($student_id)) {
            try {
                $pdo->beginTransaction();
                // Delete messages
                $stmt_msg = $pdo->prepare("DELETE FROM `messages` WHERE `thread_id` = ?");
                $stmt_msg->execute([$student_id]);
                // Delete payments (in case cascade is not fully active or to be safe)
                $stmt_pay = $pdo->prepare("DELETE FROM `payments` WHERE `student_permanent_id` = ?");
                $stmt_pay->execute([$student_id]);
                // Delete student
                $stmt_stud = $pdo->prepare("DELETE FROM `students` WHERE `permanent_id` = ?");
                $stmt_stud->execute([$student_id]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        header("Location: /mentor/index.php?tab=students");
        exit;
    }
}

// ─── RENDERING MAIN PORTAL HEADER ───────────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$is_logged_in): ?>
  <!-- MENTOR LOGIN SCREEN -->
  <div class="min-h-[80vh] flex flex-col items-center justify-center relative w-full">
    <!-- Decorative Blurs -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-amber-500/5 rounded-full blur-3xl -z-10 animate-pulse"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl -z-10"></div>

    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <span class="text-amber-500 font-extrabold text-3xl tracking-tight uppercase block font-display">
          ELYSIAN
        </span>
        <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500 mt-1 block">
          Mentor Administration Portal
        </span>
      </div>

      <!-- Card -->
      <div class="elysian-card p-8 shadow-2xl rounded-3xl">
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-1">Administrative Access</h1>
        <p class="text-xs text-gray-500 text-center mb-6">
          Enter your admin authorization credentials to access cohorts.
        </p>

        <?php if (!empty($error_msg)): ?>
          <div class="mb-5 p-3.5 bg-red-500/10 border border-red-500/20 rounded-xl text-xs font-medium text-red-400 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <?php echo htmlspecialchars($error_msg); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/mentor/index.php" class="space-y-4">
          <input type="hidden" name="login" value="1">
          <div class="flex flex-col gap-1.5">
            <label class="elysian-label">Security Password</label>
            <input
              type="password"
              name="password"
              placeholder="••••••••"
              class="elysian-input"
              required
            />
          </div>

          <button type="submit" class="w-full elysian-btn elysian-btn-gold mt-2 py-3.5">
            Access Dashboard
          </button>
        </form>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- MENTOR DASHBOARD WORKSPACE -->
  <?php 
  $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'students';
  ?>

  <div class="flex flex-col lg:flex-row gap-6 w-full min-h-full py-2 px-1">
    <!-- Tab navigation -->
    <aside class="w-full lg:w-64 flex-shrink-0">
      <div class="elysian-card p-5 shadow-md sticky top-4">
        <!-- Mobile/Tablet Only Header block inside Sidebar -->
        <div class="lg:hidden flex items-center justify-between border-b border-gray-200 pb-4 mb-4 gap-3">
          <!-- Mini Logo -->
          <a href="/index.php" class="flex items-center gap-2 flex-shrink-0 group">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-[#1E293B]">
              <span class="text-white font-black text-xs font-display">E</span>
            </div>
            <span class="font-black text-sm tracking-tight font-display text-gray-900">
              ELYSIAN<span class="text-[#D97706]">SUCCESS</span>
            </span>
          </a>
          <!-- Logout & Area -->
          <div class="flex items-center gap-2">
            <span class="text-[9px] font-bold text-[#D97706] bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-mono">
              Mentor
            </span>
            <a href="/logout.php" class="text-red-500 hover:text-red-700 font-bold text-xs hover:underline flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Logout
            </a>
          </div>
        </div>

        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-4 block">
          Menu Navigation
        </span>
        <nav class="space-y-1.5">
          <a
            href="/mentor/index.php?tab=students"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all border <?php echo $active_tab === 'students' ? 'bg-[#FF9D9D]/20 border-[#FF9D9D]/50 text-gray-900' : 'text-gray-600 hover:bg-[#EEF8CD] hover:text-gray-900 border-transparent'; ?>"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Student Registry
          </a>

          <a
            href="/mentor/index.php?tab=payments"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all border <?php echo $active_tab === 'payments' ? 'bg-[#FF9D9D]/20 border-[#FF9D9D]/50 text-gray-900' : 'text-gray-600 hover:bg-[#EEF8CD] hover:text-gray-900 border-transparent'; ?>"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            Reconciliation Panel
          </a>

          <a
            href="/mentor/index.php?tab=programs"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all border <?php echo $active_tab === 'programs' ? 'bg-[#FF9D9D]/20 border-[#FF9D9D]/50 text-gray-900' : 'text-gray-600 hover:bg-[#EEF8CD] hover:text-gray-900 border-transparent'; ?>"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            Program Database
          </a>

          <a
            href="/mentor/index.php?tab=pillars"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all border <?php echo $active_tab === 'pillars' ? 'bg-[#FF9D9D]/20 border-[#FF9D9D]/50 text-gray-900' : 'text-gray-600 hover:bg-[#EEF8CD] hover:text-gray-900 border-transparent'; ?>"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/>
            </svg>
            Pillar Content
          </a>

          <a
            href="/mentor/index.php?tab=archetypes"
            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all border <?php echo $active_tab === 'archetypes' ? 'bg-[#FF9D9D]/20 border-[#FF9D9D]/50 text-gray-900' : 'text-gray-600 hover:bg-[#EEF8CD] hover:text-gray-900 border-transparent'; ?>"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            16-Archetype Manager
          </a>
        </nav>
      </div>
    </aside>

    <!-- Content Workspace -->
    <main class="flex-1 min-w-0 flex flex-col">
      
      <!-- TAB 1: STUDENT REGISTRY -->
      <?php if ($active_tab === 'students'): ?>
        <?php 
        // Fetch all students
        $stmt = $pdo->query("SELECT * FROM `students` ORDER BY `registered_at` DESC");
        $students = $stmt->fetchAll();

        $selected_student_id = isset($_GET['selected_student_id']) ? $_GET['selected_student_id'] : '';
        $sel_stud = null;
        if (!empty($selected_student_id)) {
            foreach ($students as $st) {
                if ($st['permanent_id'] === $selected_student_id) {
                    $sel_stud = $st;
                    break;
                }
            }
        }
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch w-full min-h-[600px]">
          <!-- Student list -->
          <div class="lg:col-span-4 elysian-card overflow-hidden flex flex-col h-full min-h-0">
            <div class="p-4 border-b border-gray-200 bg-[#EEF8CD]/50 flex items-center justify-between">
              <div>
                <h3 class="font-bold text-sm text-gray-900 font-display">Student Inbox</h3>
                <p class="text-[9px] text-gray-500 font-mono">Cohort: <?php echo count($students); ?> Total Users</p>
              </div>
              <a href="/mentor/index.php?tab=students&add_student=1" class="elysian-btn elysian-btn-brand py-1 px-3 text-[10px] font-bold">
                + Student
              </a>
            </div>
            <div class="flex-grow overflow-y-auto divide-y divide-gray-100 custom-scrollbar">
              <?php if (count($students) === 0): ?>
                <div class="p-8 text-center text-gray-400 text-xs">No students registered yet.</div>
              <?php else: ?>
                <?php foreach ($students as $stud): ?>
                  <?php $is_sel = ($stud['permanent_id'] === $selected_student_id); ?>
                  <a
                    href="/mentor/index.php?tab=students&selected_student_id=<?php echo urlencode($stud['permanent_id']); ?>"
                    class="p-4 transition-all block cursor-pointer flex flex-col gap-1.5 <?php echo $is_sel ? 'bg-[#BBF1D2]/30 border-l-4 border-[#BBF1D2] pl-3' : 'hover:bg-gray-50 border-l-4 border-transparent'; ?>"
                  >
                    <div class="flex justify-between items-center">
                      <span class="status-pill text-[9px] scale-90 <?php echo 'status-' . $stud['status']; ?>">
                        <?php echo str_replace('_', ' ', $stud['status']); ?>
                      </span>
                      <span class="text-[9px] text-gray-400 font-mono">
                        <?php echo date('M j', strtotime($stud['registered_at'])); ?>
                      </span>
                    </div>
                    <div>
                      <h4 class="text-xs font-bold text-gray-900"><?php echo htmlspecialchars($stud['name']); ?></h4>
                      <p class="text-[9px] font-mono text-gray-400 truncate"><?php echo htmlspecialchars($stud['permanent_id']); ?></p>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Student details and chat -->
          <div class="lg:col-span-8 h-full min-h-0 flex flex-col">
            <?php 
            $add_student = isset($_GET['add_student']) ? 1 : 0;
            $edit_student_id = isset($_GET['edit_student_id']) ? $_GET['edit_student_id'] : '';
            $edit_stud = null;
            if (!empty($edit_student_id)) {
                $stmt = $pdo->prepare("SELECT * FROM `students` WHERE `permanent_id` = ?");
                $stmt->execute([$edit_student_id]);
                $edit_stud = $stmt->fetch();
            }
            ?>
            <?php if ($add_student || $edit_stud): ?>
              <?php
              $stmt_all_p = $pdo->query("SELECT * FROM `programs` ORDER BY `title` ASC");
              $all_programs = $stmt_all_p->fetchAll();
              $form_title = $edit_stud ? 'Edit Candidate Profile' : 'Register New Candidate';
              $s_id = $edit_stud ? $edit_stud['permanent_id'] : 'UIN-' . rand(100000, 999999);
              $s_name = $edit_stud ? $edit_stud['name'] : '';
              $s_email = $edit_stud ? $edit_stud['email'] : '';
              $s_status = $edit_stud ? $edit_stud['status'] : 'program_selection';
              $s_prog_id = $edit_stud ? $edit_stud['selected_program_id'] : '';
              $s_active_block = $edit_stud ? (int)$edit_stud['active_block_index'] : 0;
              $s_profile = $edit_stud ? $edit_stud['profile_code'] : '';
              $s_ttid = $edit_stud ? $edit_stud['ttid'] : '';
              ?>
              <div class="elysian-card p-6 flex flex-col gap-4 overflow-y-auto custom-scrollbar h-full min-h-0 bg-white">
                <div class="flex items-center justify-between border-b border-gray-200 pb-3.5 mb-2">
                  <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                      <?php echo $form_title; ?>
                    </h3>
                    <p class="text-[9px] text-gray-500 font-mono mt-0.5"><?php echo $edit_stud ? 'Modifying properties for ' . htmlspecialchars($s_name) : 'Create a new candidate record manually'; ?></p>
                  </div>
                  <a href="/mentor/index.php?tab=students<?php echo $edit_stud ? '&selected_student_id=' . urlencode($s_id) : ''; ?>" class="text-gray-400 hover:text-gray-600 text-xs font-semibold px-2 py-1">✕ Close</a>
                </div>

                <?php if (isset($_SESSION['student_error'])): ?>
                  <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 font-medium">
                    ⚠️ <?php echo htmlspecialchars($_SESSION['student_error']); unset($_SESSION['student_error']); ?>
                  </div>
                <?php endif; ?>

                <form method="POST" action="/mentor/index.php?tab=students" class="space-y-4">
                  <input type="hidden" name="save_student" value="1">
                  <input type="hidden" name="is_edit" value="<?php echo $edit_stud ? 1 : 0; ?>">

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">UIN Reference (Primary Key)</label>
                      <input type="text" name="stud_id" value="<?php echo htmlspecialchars($s_id); ?>" <?php echo $edit_stud ? 'readonly class="elysian-input bg-gray-50 text-gray-500 font-mono select-none"' : 'class="elysian-input font-mono"'; ?> required placeholder="e.g. UIN-123456">
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Full Name</label>
                      <input type="text" name="stud_name" value="<?php echo htmlspecialchars($s_name); ?>" class="elysian-input" required placeholder="e.g. Alexander Mercer">
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Email Address</label>
                      <input type="email" name="stud_email" value="<?php echo htmlspecialchars($s_email); ?>" class="elysian-input" required placeholder="e.g. alexander@example.com">
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Candidate Status</label>
                      <select name="stud_status" class="elysian-input cursor-pointer">
                        <option value="program_selection" <?php echo $s_status === 'program_selection' ? 'selected' : ''; ?>>Program Selection</option>
                        <option value="payment_pending" <?php echo $s_status === 'payment_pending' ? 'selected' : ''; ?>>Payment Pending</option>
                        <option value="active" <?php echo $s_status === 'active' ? 'selected' : ''; ?>>Active / Enrolled</option>
                        <option value="completed" <?php echo $s_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                      </select>
                    </div>
                    <div class="flex flex-col gap-1.5 col-span-1 sm:col-span-2">
                      <label class="elysian-label">Assigned Program Track</label>
                      <select name="stud_prog_id" class="elysian-input cursor-pointer font-semibold">
                        <option value="">-- No Assigned Program --</option>
                        <?php foreach ($all_programs as $p): ?>
                          <option value="<?php echo htmlspecialchars($p['id']); ?>" <?php echo $s_prog_id === $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['title']); ?> (<?php echo htmlspecialchars($p['code']); ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Archetype Trait Code (MBTI)</label>
                      <input type="text" name="stud_profile_code" value="<?php echo htmlspecialchars($s_profile); ?>" class="elysian-input font-mono uppercase" placeholder="e.g. INTJ" maxlength="4">
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Wire reference ID (TTID)</label>
                      <input type="text" name="stud_ttid" value="<?php echo htmlspecialchars($s_ttid); ?>" class="elysian-input font-mono" placeholder="e.g. TT-9876543">
                    </div>
                    <div class="flex flex-col gap-1.5">
                      <label class="elysian-label">Active Block Index</label>
                      <input type="number" name="stud_active_block" value="<?php echo htmlspecialchars($s_active_block); ?>" class="elysian-input font-mono" min="0" placeholder="0">
                    </div>
                  </div>
                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="/mentor/index.php?tab=students<?php echo $edit_stud ? '&selected_student_id=' . urlencode($s_id) : ''; ?>" class="elysian-btn elysian-btn-ghost">Cancel</a>
                    <button type="submit" class="elysian-btn elysian-btn-cyan"><?php echo $edit_stud ? 'Save Changes' : 'Create Candidate'; ?></button>
                  </div>
                </form>
              </div>
            <?php elseif (!$sel_stud): ?>
              <div class="flex flex-col items-center justify-center h-full text-gray-400 text-xs border border-dashed border-gray-300 rounded-2xl bg-gray-50">
                <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                Select a student from the registry on the left to start communication.
              </div>
            <?php else: ?>
              <?php 
              // Fetch student program
              $stmt_prog = $pdo->prepare("SELECT * FROM `programs` WHERE `id` = ?");
              $stmt_prog->execute([$sel_stud['selected_program_id']]);
              $s_prog = $stmt_prog->fetch();

              // Fetch student payment
              $stmt_pay = $pdo->prepare("SELECT * FROM `payments` WHERE `student_permanent_id` = ? ORDER BY `submitted_at` DESC LIMIT 1");
              $stmt_pay->execute([$sel_stud['permanent_id']]);
              $s_payment = $stmt_pay->fetch();

              // Fetch chat history
              $stmt_chat = $pdo->prepare("SELECT * FROM `messages` WHERE `thread_id` = ? ORDER BY `timestamp` ASC");
              $stmt_chat->execute([$sel_stud['permanent_id']]);
              $s_messages = $stmt_chat->fetchAll();

              // Fetch components map for human-readable questions & option labels
              $stmt_comps_all = $pdo->query("SELECT c.*, p.title as pillar_title FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id");
              $all_comps_map = [];
              foreach ($stmt_comps_all->fetchAll() as $cmp) {
                  $cmp['options'] = $cmp['options'] ? json_decode($cmp['options'], true) : [];
                  $all_comps_map[$cmp['id']] = $cmp;
              }

              // Calculate program progress
              $s_answers = json_decode($sel_stud['answers'], true) ?: [];

              // Archived fallback for answers whose original component was deleted
              $missing_comp_ids = array_diff(array_keys($s_answers), array_keys($all_comps_map));
              $archived_comps_map = getArchivedComponents($pdo, $missing_comp_ids);

              $answered_count = count($s_answers);
              $total_blocks_count = 0;
              if (!empty($sel_stud['selected_program_id'])) {
                  $stmt_tot = $pdo->prepare("SELECT COUNT(*) FROM `components` c JOIN `pillars` p ON c.pillar_id = p.id WHERE p.program_id = ? AND c.type NOT IN ('content_only', 'content_block', 'video_embed', 'h1', 'h2', 'h3', 'h4', 'paragraph', 'result_reveal', 'composite')");
                  $stmt_tot->execute([$sel_stud['selected_program_id']]);
                  $total_blocks_count = (int)$stmt_tot->fetchColumn();
              }
              $progress_pct = ($total_blocks_count > 0) ? min(100, round(($answered_count / $total_blocks_count) * 100)) : 0;
              $is_course_completed = ($sel_stud['status'] === 'completed' || ($total_blocks_count > 0 && $answered_count >= $total_blocks_count));
              ?>
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full min-h-0">
                <!-- Response Log and Profile -->
                <div id="response-log-panel" class="lg:col-span-12 elysian-card p-5 h-full min-h-0 overflow-y-auto custom-scrollbar flex flex-col">

                  <!-- Header Container -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-200 mb-4 shrink-0">

                    <!-- Left Column: Status, Actions, Name & Email -->
                    <div class="min-w-0 flex-1">

                      <!-- Status Badge & Micro Action Buttons -->
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <?php if ($is_course_completed): ?>
                          <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/15 text-emerald-700 border border-emerald-500/30 font-mono tracking-wide shrink-0">Course Completed ✓</span>
                        <?php else: ?>
                          <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-brand-cyan/15 text-brand-cyan border border-brand-cyan/30 font-mono tracking-wide shrink-0">In Progress</span>
                        <?php endif; ?>

                        <!-- Micro Action Buttons -->
                        <div class="inline-flex items-center gap-1 shrink-0">
                          <button type="button" onclick="toggleMentorChat()"
                             class="text-[9px] text-white font-bold px-1.5 py-0.5 bg-indigo-600 border border-indigo-600 rounded hover:bg-indigo-700 transition-colors uppercase tracking-wider inline-flex items-center gap-1">
                            💬 <?php echo count($s_messages); ?>
                          </button>
                          <a href="/mentor/index.php?tab=students&edit_student_id=<?php echo urlencode($sel_stud['permanent_id']); ?>"
                             class="text-[9px] text-indigo-600 hover:text-indigo-800 font-bold px-1.5 py-0.5 bg-indigo-50 border border-indigo-200 rounded hover:bg-indigo-100 transition-colors uppercase tracking-wider">
                            Edit
                          </a>
                          <a href="/mentor/index.php?action=delete_student&id=<?php echo urlencode($sel_stud['permanent_id']); ?>"
                             onclick="return confirm('Are you sure you want to permanently delete this student record and all support chat history? This cannot be undone.');"
                             class="text-[9px] text-red-600 hover:text-red-800 font-bold px-1.5 py-0.5 bg-red-50 border border-red-200 rounded hover:bg-red-100 transition-colors uppercase tracking-wider">
                            Delete
                          </a>
                        </div>
                      </div>

                      <!-- Student Name & Email (truncate prevents overflow) -->
                      <h2 class="text-base font-bold text-gray-900 font-display truncate">
                        <?php echo htmlspecialchars($sel_stud['name']); ?>
                      </h2>
                      <p class="text-xs text-gray-500 font-mono mt-0.5 truncate">
                        <?php echo htmlspecialchars($sel_stud['email']); ?>
                      </p>
                    </div>

                    <!-- Right Column: Compact UIN Reference Badge -->
                    <div class="sm:text-right shrink-0">
                      <span class="text-[8px] text-gray-400 font-bold block uppercase tracking-wider">UIN Reference</span>
                      <span class="text-[11px] font-mono font-bold text-gray-800 inline-block bg-gray-100 px-2 py-0.5 rounded border border-gray-200 mt-0.5 select-all">
                        <?php echo htmlspecialchars($sel_stud['permanent_id']); ?>
                      </span>
                    </div>

                  </div>

                  <!-- Course Progress Bar & Track Info -->
                  <div class="mb-3 p-3 bg-[#EEF8CD]/50 border border-gray-200 rounded-xl">
                    <div class="flex justify-between items-center text-[10px] mb-1.5">
                      <span class="font-bold text-gray-700 truncate mr-2"><?php echo htmlspecialchars($s_prog ? $s_prog['title'] : 'Program Track'); ?></span>
                      <span class="font-mono text-brand-gold font-bold shrink-0"><?php echo $answered_count; ?> / <?php echo $total_blocks_count; ?> Blocks (<?php echo $progress_pct; ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                      <div class="bg-gradient-to-r from-brand-cyan to-brand-gold h-full transition-all duration-500" style="width: <?php echo $progress_pct; ?>%"></div>
                    </div>
                    <div class="flex justify-between items-center mt-1.5 text-[8px] text-gray-500 font-mono">
                      <span>Registered: <?php echo date('M j, Y', strtotime($sel_stud['registered_at'])); ?></span>
                      <span>Block #<?php echo (int)$sel_stud['active_block_index'] + 1; ?></span>
                    </div>
                  </div>

                  <!-- Quick Payment Verification -->
                  <?php if ($s_payment && $s_payment['status'] === 'pending'): ?>
                    <div class="mb-3 p-3 bg-brand-gold/10 border border-brand-gold/25 rounded-xl flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <span class="text-[8px] font-bold text-brand-gold uppercase tracking-widest block font-mono">Verification Request</span>
                        <h4 class="text-[10px] font-bold text-gray-900 mt-0.5 truncate">Approve wire transaction reference</h4>
                        <p class="text-[8px] text-gray-500 font-mono mt-0.5">TTID: <?php echo htmlspecialchars($s_payment['ttid']); ?> | Fee: $<?php echo number_format($s_payment['amount']); ?></p>
                      </div>
                      <div class="flex gap-1.5 shrink-0">
                        <a href="/mentor/index.php?action=approve_payment&id=<?php echo urlencode($s_payment['id']); ?>" class="elysian-btn elysian-btn-emerald py-1 px-2.5 text-[10px] font-bold transition-all">Approve</a>
                        <a href="/mentor/index.php?action=reject_payment&id=<?php echo urlencode($s_payment['id']); ?>" class="elysian-btn elysian-btn-danger py-1 px-2.5 text-[10px] font-bold transition-all">Reject</a>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Trait Profile Reveal / In-Progress Badge -->
                  <?php 
                  $s_prof_code = strtoupper(trim($sel_stud['profile_code']));
                  $s_code_len = strlen($s_prof_code);
                  $s_matched = ($s_code_len === 4 && isset($master_profiles[$s_prof_code])) ? $master_profiles[$s_prof_code] : null;
                  ?>
                  <?php if ($s_matched): ?>
                    <div class="mb-3 p-3 bg-[#EEF8CD] border border-[#BBF1D2] rounded-xl">
                      <div class="flex justify-between items-center mb-1">
                        <span class="text-[8px] font-bold text-brand-gold uppercase tracking-widest font-mono">Final Compiled Archetype</span>
                        <span class="px-1.5 py-0.5 rounded bg-brand-gold/20 text-brand-gold text-[9px] font-mono font-bold"><?php echo htmlspecialchars($s_prof_code); ?></span>
                      </div>
                      <h4 class="text-[11px] font-bold text-gray-900 font-display truncate"><?php echo htmlspecialchars($s_matched['title']); ?></h4>
                      <div class="grid grid-cols-2 gap-1.5 mt-2 text-[9px]">
                        <div class="p-1.5 rounded-lg bg-white border border-gray-200">
                          <span class="text-green-600 font-bold block mb-0.5">Strengths</span>
                          <span class="text-gray-700 leading-snug"><?php echo htmlspecialchars(implode(', ', $s_matched['strengths'])); ?></span>
                        </div>
                        <div class="p-1.5 rounded-lg bg-white border border-gray-200">
                          <span class="text-blue-500 font-bold block mb-0.5">Growth Areas</span>
                          <span class="text-gray-700 leading-snug"><?php echo htmlspecialchars(implode(', ', $s_matched['weaknesses'])); ?></span>
                        </div>
                      </div>
                    </div>
                  <?php elseif ($s_code_len > 0): ?>
                    <div class="mb-3 p-3 bg-[#EEF8CD] border border-[#BBF1D2] rounded-xl">
                      <div class="flex justify-between items-center gap-2">
                        <div class="min-w-0">
                          <span class="text-[8px] font-bold text-brand-cyan uppercase tracking-widest block font-mono">Trait Profile In Progress</span>
                          <h4 class="text-[10px] font-bold text-gray-900 mt-0.5">Code: <span class="font-mono text-[#D97706] font-extrabold"><?php echo htmlspecialchars($s_prof_code . str_repeat('_', 4 - $s_code_len)); ?></span></h4>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-brand-cyan/15 text-brand-cyan text-[9px] font-mono font-bold border border-brand-cyan/30 shrink-0"><?php echo $s_code_len; ?>/4 Done</span>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Answers Summary -->
                  <div class="flex-grow">
                    <h3 class="text-[9px] font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center justify-between">
                      <span>Diagnostic Response Log</span>
                      <span class="font-mono text-gray-400 font-normal"><?php echo count($s_answers); ?> Answers</span>
                    </h3>
                    <div class="space-y-2">
                      <?php if (count($s_answers) === 0): ?>
                        <div class="p-6 border border-dashed border-gray-200 rounded-xl text-center text-gray-400 text-[10px]">
                          No diagnostic responses submitted yet by this candidate.
                        </div>
                      <?php else: ?>
                        <?php foreach ($s_answers as $b_id => $ans): ?>
                          <?php
                          $cmp_info = isset($all_comps_map[$b_id]) ? $all_comps_map[$b_id] : null;
                          $is_archived = false;
                          if (!$cmp_info && isset($archived_comps_map[$b_id])) {
                              $arc = $archived_comps_map[$b_id];
                              $cmp_info = [
                                  'question' => $arc['question'],
                                  'pillar_title' => $arc['pillar_title'],
                                  'type' => $arc['type'],
                                  'options' => $arc['options'] ? json_decode($arc['options'], true) : [],
                              ];
                              $is_archived = true;
                          }
                          $q_text = $cmp_info ? $cmp_info['question'] : $b_id;
                          $p_title = $cmp_info ? $cmp_info['pillar_title'] : '';
                          $b_type = $cmp_info ? $cmp_info['type'] : '';

                          // Format human-readable answer label
                          $formatted_ans = is_array($ans) ? implode(', ', $ans) : $ans;
                          if ($cmp_info && !empty($cmp_info['options'])) {
                              foreach ($cmp_info['options'] as $opt_item) {
                                  if ($opt_item['value'] === $ans) {
                                      $code_suffix = !empty($opt_item['hidden_code']) ? ' [' . $opt_item['hidden_code'] . ']' : '';
                                      $formatted_ans = $opt_item['label'] . $code_suffix;
                                      break;
                                  }
                              }
                          }
                          ?>
                          <div class="p-2.5 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex justify-between items-start gap-1.5 mb-1">
                              <span class="text-[8px] font-bold text-brand-gold font-mono uppercase tracking-wider truncate flex items-center gap-1">
                                <?php echo htmlspecialchars($b_type ?: 'Block'); ?> &bull; <?php echo htmlspecialchars($p_title); ?>
                                <?php if ($is_archived): ?>
                                  <span class="text-red-600 bg-red-50 border border-red-200 px-1 py-0.5 rounded normal-case" title="This question was later removed from the program">Archived</span>
                                <?php endif; ?>
                              </span>
                              <span class="text-[7px] font-mono text-gray-400 bg-gray-100 px-1 py-0.5 rounded border border-gray-200 shrink-0"><?php echo htmlspecialchars($b_id); ?></span>
                            </div>
                            <h5 class="text-[10px] font-semibold text-gray-900 mb-1 leading-snug"><?php echo htmlspecialchars($q_text); ?></h5>
                            <p class="text-[10px] text-gray-700 font-medium leading-relaxed bg-white p-2 rounded border border-gray-200 break-words font-sans">
                              <?php echo nl2br(htmlspecialchars($formatted_ans)); ?>
                            </p>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Chat Box panel -->
                <div id="support-workspace-panel" class="hidden lg:col-span-12 elysian-card overflow-hidden flex flex-col h-full min-h-0">
                  <div class="p-3 bg-[#EEF8CD]/50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                      <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-900">Support Workspace</h4>
                      <p class="text-[8px] text-gray-400 font-mono">Chat thread with candidate</p>
                    </div>
                    <button type="button" onclick="toggleMentorChat()" class="text-gray-400 hover:text-gray-600 text-xs font-bold px-2">
                      ✕ Close
                    </button>
                  </div>

                  <div id="mentor-chat-messages" class="flex-grow p-4 overflow-y-auto flex flex-col gap-3 bg-gray-50">
                    <?php if (count($s_messages) === 0): ?>
                      <div class="text-center py-12 text-gray-400 text-[10px] italic">No support messages on thread.</div>
                    <?php else: ?>
                      <?php foreach ($s_messages as $msg): ?>
                        <?php $is_mentor = ($msg['sender'] === 'mentor'); ?>
                        <div class="<?php echo $is_mentor ? 'chat-bubble-student' : 'chat-bubble-mentor chat-bubble-mentor-dark'; ?>">
                          <div class="text-[8px] opacity-60 font-bold uppercase tracking-wider mb-0.5">
                            <?php echo htmlspecialchars($msg['sender_label']); ?>
                          </div>
                          <div class="leading-relaxed"><?php echo htmlspecialchars($msg['content']); ?></div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <form id="mentor-chat-form" method="POST" action="/mentor/index.php?tab=students&selected_student_id=<?php echo urlencode($selected_student_id); ?>" class="p-3 border-t border-gray-200 flex gap-2 bg-white">
                    <input type="hidden" name="send_mentor_chat" value="1">
                    <input type="hidden" id="chat-student-id" name="student_id" value="<?php echo htmlspecialchars($selected_student_id); ?>">
                    <input
                      type="text"
                      id="mentor-chat-input"
                      name="chat_content"
                      placeholder="Send message..."
                      class="flex-grow elysian-input text-xs"
                      required
                      autocomplete="off"
                    >
                    <button type="submit" class="elysian-btn elysian-btn-cyan p-2.5">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                      </svg>
                    </button>
                  </form>
                </div>

              <!-- Inline Chat Polling JS for Mentor Dashboard -->
              <script>
              function toggleMentorChat() {
                document.getElementById('response-log-panel').classList.toggle('hidden');
                document.getElementById('support-workspace-panel').classList.toggle('hidden');
              }

              function escapeHtml(str) {
                const d = document.createElement('div');
                d.appendChild(document.createTextNode(str));
                return d.innerHTML;
              }

              function pollMentorChat() {
                const studId = document.getElementById('chat-student-id').value;
                if (!studId) return;
                fetch('/tunnel.php?fetch_chat=1&ajax=1&student_id=' + encodeURIComponent(studId))
                  .then(res => res.json())
                  .then(data => {
                    const container = document.getElementById('mentor-chat-messages');
                    if (!container) return;
                    let html = '';
                    if (data.length === 0) {
                      container.innerHTML = '<div class="text-center py-12 text-gray-400 text-[10px] italic">No support messages on thread.</div>';
                      return;
                    }
                    data.forEach(msg => {
                      const isMentor = (msg.sender === 'mentor');
                      const bubbleClass = isMentor ? 'chat-bubble-student' : 'chat-bubble-mentor chat-bubble-mentor-dark';
                      html += `
                        <div class="${bubbleClass}">
                          <div class="text-[8px] opacity-60 font-bold uppercase tracking-wider mb-0.5">${escapeHtml(msg.sender_label)}</div>
                          <div class="leading-relaxed">${escapeHtml(msg.content)}</div>
                        </div>
                      `;
                    });
                    if (container.innerHTML !== html) {
                      container.innerHTML = html;
                      container.scrollTop = container.scrollHeight;
                    }
                  });
              }
              
              document.getElementById('mentor-chat-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const input = document.getElementById('mentor-chat-input');
                const val = input.value.trim();
                const studId = document.getElementById('chat-student-id').value;
                if (!val || !studId) return;
                
                const formData = new FormData();
                formData.append('send_mentor_chat', '1');
                formData.append('student_id', studId);
                formData.append('chat_content', val);
                input.value = '';
                
                fetch('/mentor/index.php?ajax=1', {
                  method: 'POST',
                  body: formData
                })
                .then(res => res.json())
                .then(data => {
                  pollMentorChat();
                });
              });
              
              setInterval(pollMentorChat, 5000);
              document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('mentor-chat-messages');
                if (container) container.scrollTop = container.scrollHeight;
              });
              </script>
            <?php endif; ?>
          </div>
        </div>

      <!-- TAB 2: RECONCILIATION PANEL -->
      <?php elseif ($active_tab === 'payments'): ?>
        <?php 
        // Fetch all payments
        $stmt_pay = $pdo->query("SELECT p.*, s.name as student_name FROM `payments` p JOIN `students` s ON p.student_permanent_id = s.permanent_id ORDER BY p.submitted_at DESC");
        $payments = $stmt_pay->fetchAll();
        ?>
        <div class="elysian-card p-6 shadow-2xl max-h-full flex flex-col overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-gray-200 mb-5 flex-shrink-0">
            <div>
              <h2 class="text-lg font-bold font-display text-gray-900">Payment Reconciliation</h2>
              <p class="text-[10px] text-gray-500 font-mono">Verify student transactions using Transaction ID (TTID)</p>
            </div>
          </div>

          <div class="overflow-y-auto custom-scrollbar flex-grow min-h-0">
            <?php if (count($payments) === 0): ?>
              <div class="text-center py-12 text-gray-400 text-xs">No transaction submissions recorded yet.</div>
            <?php else: ?>
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-gray-200 text-[10px] font-bold text-gray-500 uppercase tracking-wider sticky top-0 bg-[#EEF8CD] z-10">
                    <th class="pb-3 px-3">Student UIN</th>
                    <th class="pb-3 px-3">Program ID</th>
                    <th class="pb-3 px-3 font-mono">TTID Reference</th>
                    <th class="pb-3 px-3">Status</th>
                    <th class="pb-3 px-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($payments as $pay): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <td class="py-4 px-3">
                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($pay['student_name']); ?></div>
                        <div class="text-[9px] font-mono text-gray-400 mt-0.5"><?php echo htmlspecialchars($pay['student_permanent_id']); ?></div>
                      </td>
                      <td class="py-4 px-3">
                        <div class="font-semibold text-gray-700"><?php echo htmlspecialchars($pay['program_id']); ?></div>
                        <div class="text-[9px] font-mono text-gray-400 mt-0.5">Pay ID: <?php echo htmlspecialchars($pay['id']); ?></div>
                      </td>
                      <td class="py-4 px-3 font-mono">
                        <div class="font-bold text-[#D97706]"><?php echo htmlspecialchars($pay['ttid']); ?></div>
                        <div class="text-[9px] text-gray-400 mt-0.5">$<?php echo number_format($pay['amount'], 2); ?></div>
                      </td>
                      <td class="py-4 px-3">
                        <span class="status-pill text-[9px] <?php echo 'status-' . $pay['status']; ?>">
                          <?php echo htmlspecialchars($pay['status']); ?>
                        </span>
                      </td>
                      <td class="py-4 px-3 text-right">
                        <?php if ($pay['status'] === 'pending'): ?>
                          <div class="inline-flex gap-2">
                            <a href="/mentor/index.php?action=approve_payment&id=<?php echo urlencode($pay['id']); ?>" class="elysian-btn elysian-btn-emerald py-1 px-3 text-[10px] font-bold">Approve</a>
                            <a href="/mentor/index.php?action=reject_payment&id=<?php echo urlencode($pay['id']); ?>" class="elysian-btn elysian-btn-danger py-1 px-3 text-[10px] font-bold">Reject</a>
                          </div>
                        <?php else: ?>
                          <span class="text-[10px] text-gray-400">
                            Processed: <?php echo $pay['verified_at'] ? date('Y-m-d', strtotime($pay['verified_at'])) : '--'; ?>
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

      <!-- TAB 3: PROGRAM DATABASE -->
      <?php elseif ($active_tab === 'programs'): ?>
        <?php 
        // Fetch programs list
        $stmt_list = $pdo->query("SELECT * FROM `programs` ORDER BY `code` ASC");
        $all_programs = $stmt_list->fetchAll();

        // Handle edit item request
        $edit_prog_id = isset($_GET['edit_id']) ? $_GET['edit_id'] : '';
        $edit_prog = null;
        if (!empty($edit_prog_id)) {
            $stmt = $pdo->prepare("SELECT * FROM `programs` WHERE `id` = ?");
            $stmt->execute([$edit_prog_id]);
            $edit_prog = $stmt->fetch();
        }
        $is_adding = isset($_GET['add']) && $_GET['add'] == 1;
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          
          <!-- Program registry listing -->
          <div class="lg:col-span-5 elysian-card p-5 flex flex-col h-full min-h-0 overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center pb-4 border-b border-gray-200 mb-4">
              <div>
                <h3 class="font-bold text-sm text-gray-900 font-display">Program Database</h3>
                <p class="text-[10px] text-gray-500 font-mono">CRUD Actions (<?php echo count($all_programs); ?> Tracks)</p>
              </div>
              <a href="/mentor/index.php?tab=programs&add=1" class="elysian-btn elysian-btn-cyan py-1 px-3 text-xs">
                Add Program
              </a>
            </div>

            <div class="space-y-3 flex-grow">
              <?php foreach ($all_programs as $p): ?>
                <?php $is_sel = ($edit_prog && $p['id'] === $edit_prog['id']); ?>
                <div class="p-4 rounded-xl border transition-all cursor-pointer bg-white border-gray-200 hover:bg-gray-50 <?php echo $is_sel ? 'border-[#FF9D9D] bg-[#EEF8CD]/40' : ''; ?>" onclick="window.location='/mentor/index.php?tab=programs&edit_id=<?php echo urlencode($p['id']); ?>'">
                  <div class="flex justify-between items-start mb-2">
                    <div class="flex gap-2 items-center">
                      <span class="text-[9px] font-bold text-gray-600 font-mono bg-gray-100 border border-gray-200 px-2 py-0.5 rounded">
                        <?php echo htmlspecialchars($p['code']); ?>
                      </span>
                      <span class="w-2 h-2 rounded-full <?php echo $p['is_active'] ? 'bg-green-500' : 'bg-gray-300'; ?>"></span>
                    </div>
                    <span class="text-[10px] font-bold text-[#D97706] font-mono">$<?php echo number_format($p['fee'], 2); ?></span>
                  </div>
                  <h4 class="text-xs font-bold text-gray-900"><?php echo htmlspecialchars($p['title']); ?></h4>
                  <p class="text-[10px] text-gray-500 line-clamp-2 mt-1"><?php echo htmlspecialchars($p['description']); ?></p>

                  <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100 text-[9px] text-gray-400 font-semibold uppercase">
                    <span>Duration: <?php echo htmlspecialchars($p['duration']); ?></span>
                    <div class="flex gap-2.5">
                      <a href="/mentor/index.php?action=toggle_program&id=<?php echo urlencode($p['id']); ?>" class="hover:text-blue-600 transition-colors">Toggle Status</a>
                      <a href="/mentor/index.php?action=delete_program&id=<?php echo urlencode($p['id']); ?>" onclick="return confirm('Delete program? This will delete all pillars and blocks.');" class="text-red-500 hover:text-red-400">Delete</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Program form editor -->
          <div class="lg:col-span-7 elysian-card p-5 h-full min-h-0 overflow-y-auto custom-scrollbar">
            <?php if ($is_adding || $edit_prog): ?>
              <?php 
              $p_id = $edit_prog ? $edit_prog['id'] : '';
              $p_code = $edit_prog ? $edit_prog['code'] : '';
              $p_title = $edit_prog ? $edit_prog['title'] : '';
              $p_desc = $edit_prog ? $edit_prog['description'] : '';
              $p_fee = $edit_prog ? $edit_prog['fee'] : 0.0;
              $p_dur = $edit_prog ? $edit_prog['duration'] : '';
              $p_active = $edit_prog ? $edit_prog['is_active'] : 1;
              $p_scheme_id = $edit_prog ? ($edit_prog['scheme_id'] ?? 'mbti_16_types') : 'mbti_16_types';
              $all_schemes = getAllTraitSchemes($pdo);
              $p_outcomes = '';
              if ($edit_prog && $edit_prog['outcomes']) {
                  $dec_out = json_decode($edit_prog['outcomes'], true);
                  if (is_array($dec_out)) {
                      $p_outcomes = implode("\n", $dec_out);
                  }
              }
              ?>
              <form method="POST" action="/mentor/index.php?tab=programs" class="space-y-4">
                <input type="hidden" name="save_program" value="1">
                <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($p_id); ?>">

                <h3 class="text-sm font-bold text-slate-100 uppercase tracking-wider border-b border-slate-800 pb-3">
                  <?php echo $edit_prog ? 'Edit Program Path' : 'Create Accelerator Path'; ?>
                </h3>

                <div class="grid grid-cols-2 gap-4">
                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Program Code</label>
                    <input type="text" name="code" value="<?php echo htmlspecialchars($p_code); ?>" class="elysian-input" required placeholder="e.g. ESA-ACC">
                  </div>
                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Duration</label>
                    <input type="text" name="duration" value="<?php echo htmlspecialchars($p_dur); ?>" class="elysian-input" required placeholder="e.g. 12 Weeks">
                  </div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="elysian-label">Program Title</label>
                  <input type="text" name="title" value="<?php echo htmlspecialchars($p_title); ?>" class="elysian-input" required placeholder="Path name">
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="elysian-label">Trait Evaluation Scheme</label>
                  <select name="scheme_id" class="elysian-input cursor-pointer font-semibold">
                    <?php foreach ($all_schemes as $sch): ?>
                      <option value="<?php echo htmlspecialchars($sch['scheme_id']); ?>" <?php echo $p_scheme_id === $sch['scheme_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sch['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="elysian-label">Fee ($)</label>
                  <input type="number" step="0.01" name="fee" value="<?php echo htmlspecialchars($p_fee); ?>" class="elysian-input font-mono" required placeholder="Price">
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="elysian-label">Description</label>
                  <textarea name="description" rows="3" class="elysian-input resize-none" required placeholder="Program synopsis..."><?php echo htmlspecialchars($p_desc); ?></textarea>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="elysian-label">Key Outcomes (One per line)</label>
                  <textarea name="outcomes" rows="3" class="elysian-input resize-none" placeholder="Strategic Outcome 1&#10;Strategic Outcome 2"><?php echo htmlspecialchars($p_outcomes); ?></textarea>
                </div>

                <div class="flex items-center gap-2 pt-2">
                  <input type="checkbox" name="is_active" id="is_active_box" value="1" <?php echo $p_active ? 'checked' : ''; ?> class="w-4 h-4 rounded text-emerald-600 bg-white border-gray-300">
                  <label for="is_active_box" class="text-xs font-semibold text-gray-700">Mark as Active (Visible to students)</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                  <a href="/mentor/index.php?tab=programs" class="elysian-btn elysian-btn-ghost">Cancel</a>
                  <button type="submit" class="elysian-btn elysian-btn-cyan">Save Program</button>
                </div>
              </form>
            <?php else: ?>
              <div class="flex flex-col items-center justify-center h-full text-gray-400 text-xs py-12">
                <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                Select a program path to view or edit, or create a new path.
              </div>
            <?php endif; ?>
          </div>
        </div>

      <!-- TAB 4: PILLAR CONTENT MANAGER -->
      <?php elseif ($active_tab === 'pillars'): ?>
        <?php 
        // Get active programs
        $stmt_ap = $pdo->query("SELECT * FROM `programs` WHERE `is_active` = 1 ORDER BY `code` ASC");
        $active_programs = $stmt_ap->fetchAll();

        $selected_prog_id = isset($_GET['selected_program_id']) ? $_GET['selected_program_id'] : (count($active_programs) > 0 ? $active_programs[0]['id'] : '');
        
        $p_pillars = [];
        if (!empty($selected_prog_id)) {
            $stmt_pils = $pdo->prepare("SELECT * FROM `pillars` WHERE `program_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
            $stmt_pils->execute([$selected_prog_id]);
            $p_pillars = $stmt_pils->fetchAll();

            foreach ($p_pillars as &$pil) {
                // Fetch Named Blocks (Tier 3)
                $stmt_nblks = $pdo->prepare("SELECT * FROM `blocks` WHERE `pillar_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
                $stmt_nblks->execute([$pil['id']]);
                $pil['named_blocks'] = $stmt_nblks->fetchAll();

                // Fetch Components (Tier 4)
                $stmt_comps = $pdo->prepare("SELECT * FROM `components` WHERE `pillar_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
                $stmt_comps->execute([$pil['id']]);
                $all_comps = $stmt_comps->fetchAll();
                
                // Group components by block_id
                foreach ($pil['named_blocks'] as &$nb) {
                    $nb['components'] = [];
                    foreach ($all_comps as $comp) {
                        if ($comp['block_id'] === $nb['id']) {
                            $nb['components'][] = $comp;
                        }
                    }
                }
                unset($nb);
                
                // Also gather flat list for the showIf target selector
                $pil['flat_components'] = $all_comps;
            }
            unset($pil);
        }

        // Subform trigger values
        $edit_pillar_id = isset($_GET['edit_pillar_id']) ? $_GET['edit_pillar_id'] : '';
        $edit_pillar = null;
        if (!empty($edit_pillar_id)) {
            $stmt = $pdo->prepare("SELECT * FROM `pillars` WHERE `id` = ?");
            $stmt->execute([$edit_pillar_id]);
            $edit_pillar = $stmt->fetch();
        }

        $edit_block_id = isset($_GET['edit_block_id']) ? $_GET['edit_block_id'] : '';
        $edit_block = null;
        if (!empty($edit_block_id)) {
            $stmt = $pdo->prepare("SELECT * FROM `components` WHERE `id` = ?");
            $stmt->execute([$edit_block_id]);
            $edit_block = $stmt->fetch();
        }

        $add_block_pillar_id = isset($_GET['add_block_pillar_id']) ? $_GET['add_block_pillar_id'] : '';
        ?>
        <!-- ════════════════════════════════════════════════════════════
             AUTHORING SUITE STYLES
        ════════════════════════════════════════════════════════════ -->
        <style>
          /* Authoring suite: Editor Panel is always full width. The Preview
             is a slide-over drawer (toggled via toggleComponentPreview()) that
             overlays the right edge instead of sharing the row with the editor. */
          .authoring-split { display: grid; grid-template-columns: 1fr; gap: 1.25rem; min-height: 100%; }

          /* Preview Panel — light mint-tinted chrome, off-canvas drawer */
          .preview-panel {
            background: #FAFAFA;
            border: 1px solid #E2E8F0;
            border-radius: 1.25rem 0 0 1.25rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 400px;
            max-width: 90vw;
            z-index: 60;
            box-shadow: -12px 0 32px rgba(15, 23, 42, 0.18);
            transform: translateX(100%);
            visibility: hidden;
            transition: transform 0.3s ease, visibility 0.3s ease;
          }
          .preview-panel.drawer-open {
            transform: translateX(0);
            visibility: visible;
          }
          .preview-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            z-index: 55;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
          }
          .preview-backdrop.backdrop-open {
            opacity: 1;
            pointer-events: auto;
          }
          .preview-header {
            background: linear-gradient(135deg, #BBF1D2 0%, #EEF8CD 100%);
            border-bottom: 1px solid #D1FAE5;
            padding: 0.875rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-shrink: 0;
          }
          .preview-dot { width: 8px; height: 8px; border-radius: 50%; }
          .preview-body { flex: 1; overflow-y: auto; padding: 1.25rem; background: #FFFFFF; }

          /* Student-mode preview question card */
          .pv-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            position: relative;
            transition: border-color 0.2s;
          }
          .pv-card:hover { border-color: #FFC5AA; }
          .pv-type-badge {
            font-size: 9px; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; color: #D97706;
            font-family: monospace; margin-bottom: 0.5rem; display: block;
          }
          .pv-question { font-size: 0.875rem; font-weight: 600; color: #1E293B; margin-bottom: 0.875rem; line-height: 1.5; }
          .pv-input {
            width: 100%; background: #F8FAFC; border: 1px solid #E2E8F0;
            border-radius: 0.625rem; padding: 0.6rem 0.875rem; font-size: 0.8rem;
            color: #374151; outline: none; transition: border-color 0.18s;
          }
          .pv-input:focus { border-color: #BBF1D2; box-shadow: 0 0 0 3px rgba(187,241,210,0.4); }
          .pv-option {
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0.6rem 0.875rem; border-radius: 0.625rem;
            border: 1px solid #E2E8F0; background: #F8FAFC;
            margin-bottom: 0.5rem; cursor: pointer; transition: all 0.18s;
          }
          .pv-option:hover { border-color: #FFC5AA; background: #FFF8F5; }
          .pv-option-dot {
            width: 16px; height: 16px; border-radius: 50%;
            border: 2px solid #CBD5E1; flex-shrink: 0;
            transition: border-color 0.18s, background 0.18s;
          }
          .pv-option:hover .pv-option-dot { border-color: #FF9D9D; }
          .pv-option-label { font-size: 0.8rem; color: #374151; }
          .pv-required-badge {
            font-size: 8px; font-weight: 700; color: #B91C1C;
            background: #FEF2F2; border: 1px solid #FECACA;
            padding: 2px 7px; border-radius: 99px; margin-left: 0.5rem;
            text-transform: uppercase; letter-spacing: 0.08em;
          }
          .pv-cond-pill {
            font-size: 8px; font-weight: 700; color: #2563EB;
            background: rgba(37,99,235,0.08); border: 1px solid rgba(37,99,235,0.2);
            padding: 2px 8px; border-radius: 99px; display: inline-block; margin-top: 0.5rem;
          }
          .pv-goal-unit {
            font-size: 0.7rem; font-weight: 700; color: #D97706;
            background: rgba(217,119,6,0.1); border: 1px solid rgba(217,119,6,0.2);
            padding: 0.35rem 0.75rem; border-radius: 0.5rem; margin-right: 0.5rem; display: inline-block;
          }
          .pv-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; color: #CBD5E1; text-align: center; padding: 2rem;
          }
          .pv-empty-icon { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.5; }
          .pv-empty-text { font-size: 0.75rem; color: #94A3B8; }

          /* ── Step 1: Block Grid Container ─────────────────────────── */
          /* Forces all .block-card children to fill the full column width */
          .block-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            width: 100%;
          }

          /* ── Step 2: Block Card — updated for grid layout ──────────── */
          .block-card {
            display: flex; align-items: flex-start; gap: 0.625rem;
            background: #FFFFFF; border: 1px solid #E2E8F0;
            padding: 0.75rem; border-radius: 0.75rem; transition: all 0.18s;
            width: 100%;           /* stretch to fill the 1fr grid column */
            box-sizing: border-box; /* padding does not break the 100% width */
          }
          .block-card:hover { border-color: #FFC5AA; background: #FFF8F5; }
          .block-card.dragging { opacity: 0.4; border-style: dashed; }
          .block-card.drag-over { border-color: #FF9D9D; background: rgba(255,157,157,0.06); }
          .drag-handle {
            cursor: grab; color: #CBD5E1; padding: 0.25rem;
            border-radius: 0.375rem; transition: color 0.18s;
            flex-shrink: 0; margin-top: 1px; font-size: 1rem; line-height: 1;
          }
          .drag-handle:hover { color: #FF9D9D; }
          .block-card-body { flex: 1; min-width: 0; }
          .block-card-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }

          /* Options builder table */
          .options-table { width: 100%; border-collapse: collapse; }
          .options-table th {
            font-size: 8px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.1em; color: #94A3B8; padding: 0.4rem 0.5rem;
            border-bottom: 1px solid #E2E8F0; text-align: left;
          }
          .options-table td { padding: 0.4rem 0.375rem; vertical-align: middle; }
          .opt-input {
            width: 100%; background: #F8FAFC; border: 1px solid #E2E8F0;
            border-radius: 0.4rem; padding: 0.4rem 0.6rem; font-size: 0.75rem;
            color: #1E293B; outline: none; transition: border-color 0.18s;
          }
          .opt-input:focus { border-color: #BBF1D2; }
          .opt-input.slug-val { color: #D97706; font-family: monospace; }
          .opt-code-select {
            background: #F8FAFC; border: 1px solid #E2E8F0;
            border-radius: 0.4rem; padding: 0.35rem 0.5rem; font-size: 0.72rem;
            color: #374151; outline: none; width: 100%;
          }
          .opt-del-btn {
            background: #FEF2F2; border: 1px solid #FECACA;
            color: #DC2626; border-radius: 0.375rem; padding: 0.3rem 0.5rem;
            font-size: 0.7rem; cursor: pointer; transition: all 0.18s;
          }
          .opt-del-btn:hover { background: #FECACA; }
          .add-choice-btn {
            display: flex; align-items: center; gap: 0.4rem;
            font-size: 0.75rem; font-weight: 600; color: #2E7D52;
            background: rgba(187,241,210,0.4); border: 1px solid #BBF1D2;
            border-radius: 0.5rem; padding: 0.45rem 0.875rem;
            cursor: pointer; transition: all 0.18s; margin-top: 0.625rem;
          }
          .add-choice-btn:hover { background: rgba(187,241,210,0.7); }

          /* showIf builder */
          .showif-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-top: 0.625rem; }
          @media (max-width: 900px) { .showif-row { grid-template-columns: 1fr; } }
          .showif-toggle-wrap { display: flex; gap: 0; border-radius: 0.5rem; overflow: hidden; border: 1px solid #E2E8F0; }
          .showif-toggle-btn {
            flex: 1; padding: 0.45rem 0; font-size: 0.7rem; font-weight: 600;
            background: #F8FAFC; color: #64748B; cursor: pointer; border: none;
            transition: all 0.18s;
          }
          .showif-toggle-btn.active { background: #BBF1D2; color: #1E293B; }

          /* Authoring form section headers */
          .form-section-label {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;
            color: #64748B; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
          }
          .form-section-label::after {
            content: ''; flex: 1; height: 1px; background: #E2E8F0;
          }

          /* Duplicate block button */
          .dup-btn {
            background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.2);
            color: #D97706; border-radius: 0.375rem; padding: 0.25rem 0.5rem;
            font-size: 0.65rem; font-weight: 700; cursor: pointer;
            transition: all 0.18s; white-space: nowrap;
          }
          .dup-btn:hover { background: rgba(217,119,6,0.18); }

          /* Authoring header bar */
          .authoring-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.875rem 1.25rem;
            background: linear-gradient(135deg, rgba(187,241,210,0.35) 0%, rgba(238,248,205,0.35) 100%);
            border-bottom: 1px solid #E2E8F0;
            border-radius: 1.25rem 1.25rem 0 0;
            flex-shrink: 0;
          }
          .authoring-badge {
            font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            background: linear-gradient(135deg, #BBF1D2, #EEF8CD);
            color: #1E293B; padding: 3px 10px; border-radius: 99px;
            border: 1px solid #BBF1D2;
          }

          /* Tree item active highlight */
          .ely-tree-item { transition: background 0.18s, border-color 0.18s; }
          .ely-tree-item.active { background: #EEF8CD !important; border-color: #BBF1D2 !important; }

          /* Danger badge */
          .ely-badge-danger {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
            background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;
            padding: 2px 8px; border-radius: 99px;
          }
        </style>

        <?php
        // Gather all component IDs for the showIf target selector
        $all_block_ids_for_showif = [];
        foreach ($p_pillars as $pil_si) {
            foreach (($pil_si['flat_components'] ?? []) as $blk_si) {
                $all_block_ids_for_showif[] = ['id' => $blk_si['id'], 'label' => $blk_si['question'] ?? $blk_si['id']];
            }
        }
        ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full h-full lg:h-[calc(100vh-5rem)] min-h-0 text-gray-800">

          <!-- ══ LEFT COLUMN: 4-Tier Tree Navigation Sidebar ══ -->
          <div class="elysian-card p-5 flex flex-col h-full min-h-0 overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
              <h3 class="font-bold text-sm text-gray-900 font-display flex items-center gap-2">
                <span>📚</span> Content Tree Hierarchy
              </h3>
              <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&add_pillar=1" class="elysian-btn elysian-btn-brand py-1 px-3 text-[10px] font-bold">
                + Pillar
              </a>
            </div>

            <!-- Program Selector -->
            <form method="GET" action="/mentor/index.php" class="mb-5">
              <input type="hidden" name="tab" value="pillars">
              <label class="elysian-label text-[9px] mb-1.5 block">Target Program</label>
              <select name="selected_program_id" class="elysian-input cursor-pointer font-semibold text-xs" onchange="this.form.submit()">
                <?php foreach ($active_programs as $ap): ?>
                  <option value="<?php echo htmlspecialchars($ap['id']); ?>" <?php echo $selected_prog_id === $ap['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ap['title']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>

            <!-- Tree Navigation List -->
            <ul class="space-y-4" id="pillars-list">
              <?php foreach ($p_pillars as $pil_idx => $pil): ?>
                <!-- Tier 2: Pillar Node (Bold Parent) -->
                <li class="border border-gray-200 rounded-xl bg-white overflow-hidden shadow-sm">
                  <div class="flex items-center justify-between p-3 bg-[#EEF8CD]/60 border-b border-gray-200">
                    <span class="font-bold text-xs text-gray-900 font-display flex items-center gap-1.5">
                      <span class="text-indigo-600 font-mono text-[10px]"><?php echo $pil_idx + 1; ?>.</span>
                      <?php echo htmlspecialchars($pil['title']); ?>
                    </span>
                    <div class="flex items-center gap-1.5">
                      <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&edit_pillar_id=<?php echo urlencode($pil['id']); ?>" class="text-[9px] text-gray-600 hover:text-indigo-600 font-bold px-1.5 py-0.5 rounded hover:bg-white">Edit</a>
                      <a href="/mentor/index.php?action=delete_pillar&id=<?php echo urlencode($pil['id']); ?>&program_id=<?php echo urlencode($selected_prog_id); ?>" onclick="return confirm('Delete this entire pillar and all its blocks?');" class="text-red-500 hover:text-red-700 font-bold text-[9px] px-1.5 py-0.5 rounded hover:bg-red-50">Del</a>
                    </div>
                  </div>

                  <!-- Tier 3: Named Blocks (Nested Child Nodes) -->
                  <div class="p-2.5 space-y-3" id="nb-list-<?php echo htmlspecialchars($pil['id']); ?>">
                    <?php if (count($pil['named_blocks']) === 0): ?>
                      <p class="text-gray-400 text-[10px] italic px-2">No blocks created yet.</p>
                    <?php else: ?>
                      <?php foreach ($pil['named_blocks'] as $nb): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-2.5 ml-2">
                          <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-[10px] text-amber-600 uppercase tracking-wider font-mono flex items-center gap-1">
                              <span>📦</span> <?php echo htmlspecialchars($nb['title']); ?>
                            </span>
                            <div class="flex gap-1.5 items-center">
                              <button type="button" onclick="moveNamedBlock('<?php echo $nb['id']; ?>', 'up')" class="text-gray-400 hover:text-indigo-600 text-[9px] px-1">▲</button>
                              <button type="button" onclick="moveNamedBlock('<?php echo $nb['id']; ?>', 'down')" class="text-gray-400 hover:text-indigo-600 text-[9px] px-1">▼</button>
                              <span class="text-gray-300">|</span>
                              <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&edit_named_block_id=<?php echo urlencode($nb['id']); ?>&nb_pillar_id=<?php echo urlencode($pil['id']); ?>" class="text-[9px] text-indigo-600 hover:underline font-bold">Edit</a>
                              <a href="/mentor/index.php?action=delete_named_block&id=<?php echo urlencode($nb['id']); ?>&program_id=<?php echo urlencode($selected_prog_id); ?>" onclick="return confirm('Delete this block and all its components?');" class="text-red-500 hover:text-red-700 font-bold text-[9px]">Del</a>
                            </div>
                          </div>

                          <!-- Tier 4: Components inside Named Block (Item Nodes) -->
                          <div class="block-grid ml-2 pl-2 border-l border-indigo-300 comp-drop-zone" id="blk-list-<?php echo htmlspecialchars($nb['id']); ?>" data-block-id="<?php echo htmlspecialchars($nb['id']); ?>">
                            <?php if (count($nb['components']) === 0): ?>
                              <p class="text-gray-400 text-[9px] italic py-1 empty-block-msg">Empty block.</p>
                            <?php else: ?>
                              <?php foreach ($nb['components'] as $blk): ?>
                                <?php
                                $is_active_comp = (isset($_GET['edit_block_id']) && $_GET['edit_block_id'] === $blk['id']);
                                $type_label = match($blk['type']) {
                                  'content_only','content_block' => 'Content Block',
                                  'short_answer'  => 'Short Answer',
                                  'free_text'     => 'Free Text',
                                  'dropdown'      => 'Dropdown',
                                  'checklist'     => 'Checklist',
                                  'file_upload'   => 'File Upload',
                                  'rating_scale'  => 'Rating Scale',
                                  'video_embed'   => 'Video Embed',
                                  'goal'          => 'Goal Entry',
                                  'scoring_block' => 'Scoring Matrix',
                                  'h1','h2','h3','h4' => 'Heading',
                                  'paragraph'     => 'Paragraph',
                                  'branching','scoring' => 'Multiple Choice',
                                  'result_reveal' => 'Result Reveal',
                                  'composite'     => 'Composite (Empty)',
                                  default         => strtoupper($blk['type'])
                                };
                                $badge_cls = match($blk['type']) {
                                  'content_only','content_block' => 'bg-slate-100 text-slate-800 border-slate-300',
                                  'short_answer','free_text' => 'bg-blue-100 text-blue-800 border-blue-300',
                                  'dropdown','checklist' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                  'file_upload','rating_scale' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                                  'video_embed' => 'bg-purple-100 text-purple-800 border-purple-300',
                                  'scoring_block','goal' => 'bg-amber-100 text-amber-800 border-amber-300',
                                  'h1','h2','h3','h4','paragraph' => 'bg-gray-100 text-gray-700 border-gray-300',
                                  'composite' => 'bg-red-100 text-red-700 border-red-300',
                                  default => 'bg-gray-100 text-gray-700 border-gray-300'
                                };
                                ?>
                                <div class="ely-tree-item p-2 rounded-md bg-white border border-gray-200 flex items-center justify-between gap-2 cursor-pointer min-w-0 <?php echo $is_active_comp ? 'active' : ''; ?>" draggable="true" data-comp-id="<?php echo htmlspecialchars($blk['id']); ?>" data-block-id="<?php echo htmlspecialchars($nb['id']); ?>">
                                  <div class="flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                                    <!-- Visual drag handle -->
                                    <span class="text-gray-400 font-bold text-xs cursor-grab select-none font-mono flex-shrink-0">⋮⋮</span>
                                    <div class="min-w-0 flex-1 overflow-hidden">
                                      <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="px-1.5 py-0.2 text-[8px] font-bold rounded border uppercase tracking-wider whitespace-nowrap <?php echo $badge_cls; ?>">
                                          <?php echo $type_label; ?>
                                        </span>
                                        <?php if ($blk['required']): ?><span class="text-[8px] font-bold text-red-500 whitespace-nowrap">*Req</span><?php endif; ?>
                                      </div>
                                      <span class="text-[10px] text-gray-700 block truncate w-full"><?php echo htmlspecialchars($blk['question']); ?></span>
                                    </div>
                                  </div>
                                  <div class="flex items-center gap-1.5 text-[9px] flex-shrink-0">
                                    <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&edit_block_id=<?php echo urlencode($blk['id']); ?>" class="text-indigo-600 font-bold hover:underline">Edit</a>
                                    <a href="/mentor/index.php?action=delete_block&id=<?php echo urlencode($blk['id']); ?>&program_id=<?php echo urlencode($selected_prog_id); ?>" onclick="return confirm('Delete component?');" class="text-red-500 hover:text-red-700 font-bold">✕</a>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            <?php endif; ?>
                            <!-- Add Component button -->
                            <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&add_block_pillar_id=<?php echo urlencode($pil['id']); ?>&named_block_id=<?php echo urlencode($nb['id']); ?>" class="text-[9px] font-bold text-gray-500 hover:text-indigo-600 transition-colors mt-1.5 inline-block">
                              + Add Component
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <!-- Create Named Block Button -->
                  <div class="p-2.5 bg-gray-50 border-t border-gray-200 text-center">
                    <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>&add_named_block_pillar_id=<?php echo urlencode($pil['id']); ?>" class="text-[10px] font-bold text-amber-600 hover:text-amber-700 transition-all inline-flex items-center gap-1">
                      + Create Named Block
                    </a>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- ══ RIGHT COLUMN: Authoring Suite Editor + Preview ══ -->
          <div class="min-w-0 w-full h-full min-h-0 flex flex-col gap-4">

            <?php if (isset($_GET['add_pillar']) || $edit_pillar): ?>
              <!-- ── Pillar Form ── -->
              <?php 
              $p_id = $edit_pillar ? $edit_pillar['id'] : '';
              $p_title = $edit_pillar ? $edit_pillar['title'] : '';
              $p_cong_note = $edit_pillar ? ($edit_pillar['congratulatory_note'] ?? '') : '';
              $p_sort_order = $edit_pillar ? ($edit_pillar['sort_order'] ?? 0) : '';
              ?>
              <div class="elysian-card p-5 flex flex-col gap-4 overflow-y-auto custom-scrollbar">
                <form method="POST" action="/mentor/index.php?tab=pillars" class="space-y-4">
                  <input type="hidden" name="save_pillar" value="1">
                  <input type="hidden" name="p_program_id" value="<?php echo htmlspecialchars($selected_prog_id); ?>">
                  <input type="hidden" name="p_pillar_id" value="<?php echo htmlspecialchars($p_id); ?>">
                  <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-200 pb-3">
                    <?php echo $edit_pillar ? 'Edit Pillar Info' : 'Add Strategic Pillar'; ?>
                  </h3>
                  
                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Pillar Title</label>
                    <input type="text" name="p_title" value="<?php echo htmlspecialchars($p_title); ?>" class="elysian-input" required placeholder="e.g. Pillar 1: Vision Mapping">
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Sort Order (Number)</label>
                    <input type="number" name="p_sort_order" value="<?php echo htmlspecialchars($p_sort_order); ?>" class="elysian-input" required placeholder="1, 2, 3...">
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Congratulatory Note (Shown when pillar is completed)</label>
                    <textarea name="p_congratulatory_note" rows="3" class="elysian-input resize-none" placeholder="🌟 Excellent work! Keep the momentum going..."><?php echo htmlspecialchars($p_cong_note); ?></textarea>
                  </div>
                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>" class="elysian-btn elysian-btn-ghost">Cancel</a>
                    <button type="submit" class="elysian-btn elysian-btn-cyan">Save Pillar</button>
                  </div>
                </form>
              </div>
            <?php endif; ?>

            <?php 
            $add_named_block_pillar_id = isset($_GET['add_named_block_pillar_id']) ? $_GET['add_named_block_pillar_id'] : '';
            $edit_named_block_id = isset($_GET['edit_named_block_id']) ? $_GET['edit_named_block_id'] : '';
            $nb_pillar_id = isset($_GET['nb_pillar_id']) ? $_GET['nb_pillar_id'] : $add_named_block_pillar_id;
            
            $edit_nb = null;
            if (!empty($edit_named_block_id)) {
                $stmt = $pdo->prepare("SELECT * FROM `blocks` WHERE `id` = ?");
                $stmt->execute([$edit_named_block_id]);
                $edit_nb = $stmt->fetch();
                if ($edit_nb) $nb_pillar_id = $edit_nb['pillar_id'];
            }
            ?>
            <?php if (!empty($add_named_block_pillar_id) || $edit_nb): ?>
              <!-- ── Named Block Form ── -->
              <?php 
              $nb_id = $edit_nb ? $edit_nb['id'] : '';
              $nb_title = $edit_nb ? $edit_nb['title'] : '';
              ?>
              <div class="elysian-card p-5 flex flex-col gap-4 overflow-y-auto custom-scrollbar">
                <form method="POST" action="/mentor/index.php?tab=pillars" class="space-y-4">
                  <input type="hidden" name="save_named_block" value="1">
                  <input type="hidden" name="nb_program_id" value="<?php echo htmlspecialchars($selected_prog_id); ?>">
                  <input type="hidden" name="nb_pillar_id" value="<?php echo htmlspecialchars($nb_pillar_id); ?>">
                  <input type="hidden" name="nb_block_id" value="<?php echo htmlspecialchars($nb_id); ?>">
                  <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-200 pb-3">
                    <?php echo $edit_nb ? 'Edit Block Name' : 'Create Named Block'; ?>
                  </h3>
                  
                  <div class="flex flex-col gap-1.5">
                    <label class="elysian-label">Block Title</label>
                    <input type="text" name="nb_title" value="<?php echo htmlspecialchars($nb_title); ?>" class="elysian-input" required placeholder="e.g. Assessment 1, Module Intro...">
                  </div>

                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>" class="elysian-btn elysian-btn-ghost">Cancel</a>
                    <button type="submit" class="elysian-btn elysian-btn-cyan">Save Block</button>
                  </div>
                </form>
              </div>

            <?php elseif (!empty($add_block_pillar_id) || $edit_block): ?>
              <!-- ── AUTHORING SUITE (Component Add/Edit) ── -->
              <?php 
              $b_id = $edit_block ? $edit_block['id'] : '';
              $b_pill_id = $edit_block ? $edit_block['pillar_id'] : $add_block_pillar_id;
              $b_named_block_id = $edit_block ? $edit_block['block_id'] : (isset($_GET['named_block_id']) ? $_GET['named_block_id'] : null);
              $b_type = $edit_block ? $edit_block['type'] : 'content_only';
              $b_question = $edit_block ? $edit_block['question'] : '';
              $b_place = $edit_block ? $edit_block['placeholder'] : '';
              $b_req = $edit_block ? $edit_block['required'] : 1;
              $b_show_if_raw = $edit_block ? ($edit_block['show_if'] ?? '') : '';
              $b_options_raw = $edit_block ? ($edit_block['options'] ?? '') : '';
              $b_show_if_arr = ($b_show_if_raw) ? json_decode($b_show_if_raw, true) : null;
              $b_options_arr = ($b_options_raw) ? json_decode($b_options_raw, true) : null;

              // Fetch program's trait evaluation scheme traits for dynamic dropdowns
              $stmt_prog_sch = $pdo->prepare("SELECT `scheme_id` FROM `programs` WHERE `id` = ?");
              $stmt_prog_sch->execute([$selected_prog_id]);
              $prog_sch_row = $stmt_prog_sch->fetch();
              $active_scheme_id = $prog_sch_row['scheme_id'] ?? 'mbti_16_types';
              $available_traits = getSchemeTraits($pdo, $active_scheme_id);

              // Load content_schema for composite block editing
              $b_content_schema_raw = $edit_block ? ($edit_block['content_schema'] ?? '') : '';
              $b_content_schema_arr = ($b_content_schema_raw) ? json_decode($b_content_schema_raw, true) : null;

              // Auto-seed legacy fields into content_schema if editing a block with no schema yet
              if ($edit_block && !$b_content_schema_arr) {
                  $auto_seed = [];
                  if (!empty($b_question)) {
                      $auto_seed[] = ['type' => 'heading', 'level' => 'h3', 'text' => $b_question];
                  }
                  if (!empty($b_place) && !in_array($b_type, ['scoring_block','result_reveal'])) {
                      $auto_seed[] = ['type' => 'paragraph', 'text' => $b_place];
                  }
                  // Map legacy interactive type to composite sub-element
                  $interactive_map = [
                      'short_answer' => 'input_short_answer',
                      'free_text'    => 'input_free_text',
                      'goal'         => 'input_free_text',
                      'dropdown'     => 'input_dropdown',
                      'checklist'    => 'input_dropdown',
                      'file_upload'  => 'file_upload',
                      'video_embed'  => 'video_embed',
                  ];
                  if (isset($interactive_map[$b_type])) {
                      $seed_elem = ['type' => $interactive_map[$b_type], 'required' => (bool)$b_req];
                      if ($b_type === 'video_embed') {
                          $b_config_arr_tmp = $b_content_schema_arr ?? [];
                          try { $b_config_arr_tmp = json_decode($edit_block['config'] ?? '{}', true) ?: []; } catch(\Exception $e) {}
                          $seed_elem['url'] = $b_config_arr_tmp['video_url'] ?? $b_place ?? '';
                      }
                      if (in_array($b_type, ['dropdown','checklist']) && $b_options_arr) {
                          $seed_elem['options'] = implode(', ', array_column($b_options_arr, 'label'));
                      }
                      $auto_seed[] = $seed_elem;
                  }
                  if (!empty($auto_seed)) {
                      $b_content_schema_arr = $auto_seed;
                  }
              }
              ?>

              <div class="authoring-split flex-1 min-h-0">

                <!-- ── LEFT: Editor Panel ── -->
                <div id="editor-panel" class="elysian-card flex flex-col overflow-hidden">
                  <!-- Sticky Header (info + on-demand preview toggle; actions live in the bottom submit row) -->
                  <div class="sticky top-0 z-20 bg-white/95 backdrop-blur-md p-3.5 border-b border-gray-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse flex-shrink-0"></span>
                      <div class="min-w-0">
                        <span class="text-xs font-bold text-gray-900 font-display">Component Builder</span>
                        <p class="text-[9px] text-gray-500 font-mono truncate"><?php echo $edit_block ? 'Editing: ' . htmlspecialchars(substr($b_question, 0, 24)) . (strlen($b_question) > 24 ? '…' : '') : 'New Component'; ?></p>
                      </div>
                    </div>
                    <button type="button" onclick="toggleComponentPreview()"
                       class="flex-shrink-0 text-[9px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 px-2 py-1 rounded hover:bg-indigo-100 transition-colors inline-flex items-center gap-1 whitespace-nowrap">
                      👁️ Preview
                    </button>
                  </div>

                  <div class="flex-1 overflow-y-auto custom-scrollbar p-5">
                    <form id="block-authoring-form" method="POST" action="/mentor/index.php?tab=pillars" class="space-y-5">
                      <input type="hidden" name="save_block" value="1">
                      <input type="hidden" name="b_program_id" value="<?php echo htmlspecialchars($selected_prog_id); ?>">
                      <input type="hidden" name="b_pillar_id" value="<?php echo htmlspecialchars($b_pill_id); ?>">
                      <input type="hidden" name="b_block_id" value="<?php echo htmlspecialchars($b_id); ?>">
                      <input type="hidden" name="b_named_block_id" value="<?php echo htmlspecialchars($b_named_block_id); ?>">
                      <input type="hidden" name="b_options" id="hf-options">
                      <input type="hidden" name="b_show_if" id="hf-show-if">
                      <input type="hidden" name="b_config" id="hf-config">
                      <input type="hidden" name="b_content_schema" id="hf-content-schema">


                      <!-- ══ COMPOSITE BLOCK BUILDER ══════════════════════════════════════ -->
                      <div id="cbs-panel" class="mb-5">
                        <div class="form-section-label">Component Elements</div>
                        <div class="flex items-center justify-between mb-3">
                          <button type="button" id="cbs-add-btn" onclick="cbsShowTypePicker()" class="flex items-center gap-1.0 px-[0.85rem] py-[0.3rem] text-[0.7rem] font-bold text-white bg-gradient-to-br from-indigo-600 to-violet-600 rounded-lg cursor-pointer shadow-sm hover:-translate-y-0.5 transition-all">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Add Element
                          </button>
                        </div>

                        <!-- Sub-Element Stack List -->
                        <div id="cbs-stack" class="flex flex-col gap-2 min-h-[48px]">
                          <!-- Elements injected by JS -->
                        </div>

                        <!-- Empty state -->
                        <div id="cbs-empty" style="display:none; text-align:center; padding:1.25rem; border:1.5px dashed #C7D2FE; border-radius:0.75rem; background:#F5F3FF; color:#6D28D9; font-size:0.72rem; font-weight:600;">
                          <div style="font-size:1.5rem; margin-bottom:0.35rem;">🧩</div>
                          No elements yet. Click <strong>Add Element</strong> to start building.
                        </div>

                        <!-- Type Picker Dropdown -->
                        <div id="cbs-type-picker" style="display:none; position:relative; z-index:50; margin-top:0.4rem; background:#fff; border:1.5px solid #C7D2FE; border-radius:0.875rem; padding:0.625rem; box-shadow:0 8px 32px rgba(79,70,229,0.12);">
                          <div style="font-size:0.65rem; font-weight:700; color:#7C3AED; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:0.5rem; padding:0 0.25rem;">Choose Element Type</div>
                          <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:0.4rem;">
                            <?php foreach ([
                              ['heading','📝','Heading (H1–H4)'],
                              ['paragraph','¶','Paragraph Text'],
                              ['callout_box','💡','Callout / Highlight Box'],
                              ['input_short_answer','✏️','Short Answer Prompt'],
                              ['input_free_text','📄','Free Text / Reflection'],
                              ['goal_statement','📌','Goal Statement (Worksheet)'],
                              ['input_dropdown','☰','Dropdown Choices'],
                              ['rating_scale','⭐','1 to 5 Rating Scale'],
                              ['resource_link','🔗','Resource Link / Download'],
                              ['file_upload','📁','File Upload Field'],
                              ['video_embed','▶','Video Embed Player'],
                              ['trait_matrix','🎯','Trait / Archetype Scoring'],
                              ['result_reveal','🔮','Profile Result Reveal'],
                            ] as [$val,$ico,$lbl]): ?>
                            <button type="button" onclick="cbsAddElement('<?php echo $val; ?>')" style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.65rem; background:#F5F3FF; border:1px solid #DDD6FE; border-radius:0.625rem; cursor:pointer; font-size:0.7rem; font-weight:600; color:#4C1D95; text-align:left; transition:all 0.12s;" onmouseover="this.style.background='#EDE9FE'; this.style.borderColor='#7C3AED'" onmouseout="this.style.background='#F5F3FF'; this.style.borderColor='#DDD6FE'">
                              <span style="font-size:1rem; line-height:1;"><?php echo $ico; ?></span>
                              <?php echo $lbl; ?>
                            </button>
                            <?php endforeach; ?>
                          </div>
                          <button type="button" onclick="document.getElementById('cbs-type-picker').style.display='none'" style="margin-top:0.5rem; width:100%; padding:0.3rem; font-size:0.65rem; font-weight:700; color:#6B7280; background:none; border:1px solid #E5E7EB; border-radius:0.5rem; cursor:pointer;">Cancel</button>
                        </div>
                      </div>
                      <div style="border-top:1.5px dashed #E0E7FF; margin-bottom:1rem; margin-top:0.25rem;"></div>

                      <!-- Display Condition -->

                      <div>
                        <div class="form-section-label">Display Condition</div>
                        <div class="showif-toggle-wrap" id="showif-toggle">
                          <button type="button" class="showif-toggle-btn" id="si-always-btn" onclick="asSetShowIf('always')">Always Visible</button>
                          <button type="button" class="showif-toggle-btn" id="si-cond-btn" onclick="asSetShowIf('conditional')">Conditional</button>
                        </div>

                        <div id="si-builder" style="display:none; margin-top:0.75rem;">
                          <div class="showif-row">
                            <div>
                              <label class="elysian-label" style="font-size:9px;">Target Block ID</label>
                              <select id="si-target" class="elysian-input" style="font-size:0.75rem; padding:0.45rem 0.75rem; margin-top:0.3rem;" onchange="asCompileShowIf()">
                                <option value="">&mdash; Select Block &mdash;</option>
                                <?php foreach ($all_block_ids_for_showif as $bsi): ?>
                                  <option value="<?php echo htmlspecialchars($bsi['id']); ?>" <?php echo ($b_show_if_arr && ($b_show_if_arr['blockId'] ?? '') === $bsi['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(substr($bsi['label'], 0, 30)); ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div>
                              <label class="elysian-label" style="font-size:9px;">Operator</label>
                              <select id="si-operator" class="elysian-input" style="font-size:0.75rem; padding:0.45rem 0.75rem; margin-top:0.3rem;" onchange="asCompileShowIf()">
                                <option value="equals" <?php echo ($b_show_if_arr && ($b_show_if_arr['operator'] ?? '') === 'equals') ? 'selected' : ''; ?>>Equals</option>
                                <option value="not_equals" <?php echo ($b_show_if_arr && ($b_show_if_arr['operator'] ?? '') === 'not_equals') ? 'selected' : ''; ?>>Not Equals</option>
                                <option value="contains" <?php echo ($b_show_if_arr && ($b_show_if_arr['operator'] ?? '') === 'contains') ? 'selected' : ''; ?>>Contains</option>
                              </select>
                            </div>
                            <div>
                              <label class="elysian-label" style="font-size:9px;">Expected Value</label>
                              <input id="si-value" type="text" class="elysian-input" style="font-size:0.75rem; padding:0.45rem 0.75rem; margin-top:0.3rem;" placeholder="e.g. operational_efficiency" value="<?php echo htmlspecialchars($b_show_if_arr['value'] ?? ''); ?>" oninput="asCompileShowIf()">
                            </div>
                          </div>
                          <div id="si-preview-pill" class="pv-cond-pill" style="margin-top:0.625rem;"></div>
                        </div>
                      </div>

                      <!-- Required -->
                      <div class="form-section-label">Required</div>
                      <div id="as-required-wrap" class="flex items-center gap-2.5 py-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                          <input type="checkbox" id="as-required" name="b_required" value="1" <?php echo $b_req ? 'checked' : ''; ?> class="sr-only peer" onchange="asUpdatePreview()">
                          <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#BBF1D2]"></div>
                        </label>
                        <span class="text-xs font-semibold text-gray-700">Answer Required</span>
                      </div>

                      <!-- Submit -->
                      <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <a href="/mentor/index.php?tab=pillars&selected_program_id=<?php echo urlencode($selected_prog_id); ?>" class="elysian-btn elysian-btn-ghost py-1.5 px-4 text-xs">Cancel</a>
                        <button type="submit" class="elysian-btn elysian-btn-cyan py-1.5 px-4 text-xs font-bold flex items-center gap-1.5" onclick="asCompileAndSubmit(event)">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                          Save Block
                        </button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Backdrop for the slide-over preview drawer -->
                <div id="preview-backdrop" class="preview-backdrop" onclick="toggleComponentPreview()"></div>

                <!-- ── RIGHT: Student Preview Panel (slide-over drawer) ── -->
                <div id="preview-panel-wrap" class="preview-panel">
                  <div class="preview-header">
                    <span class="preview-dot" style="background:#ef4444;"></span>
                    <span class="preview-dot" style="background:#f59e0b;"></span>
                    <span class="preview-dot" style="background:#22c55e;"></span>
                    <span style="font-size:10px; color:#475569; font-weight:600; margin-left:0.5rem;">Student Mode Preview</span>
                    <span style="margin-left:auto; font-size:9px; font-weight:700; color:#C99700; background:rgba(201,151,0,0.1); padding:2px 8px; border-radius:99px;">LIVE</span>
                    <button type="button" onclick="toggleComponentPreview()" style="font-size:9px; font-weight:700; color:#475569; background:none; border:none; cursor:pointer; padding:2px 6px;">✕ Close</button>
                  </div>
                  <div class="preview-body custom-scrollbar" id="as-preview-body">
                    <div class="pv-empty" id="as-preview-empty">
                      <div class="pv-empty-icon">⚡</div>
                      <p class="pv-empty-text">Fill in the form to see a live preview of how students will experience this block.</p>
                    </div>
                    <div id="as-preview-card" style="display:none;">
                      <!-- rendered by JS -->
                    </div>
                  </div>
                </div>

              </div><!-- /.authoring-split -->

              <!-- ── Authoring Suite JavaScript ── -->
              <script>
              (function() {
                // ── Toggle: Student Mode Preview slide-over drawer ──
                // Editor Panel stays full width and anchored in place; the
                // preview slides in from the right edge as an overlay instead.
                window.toggleComponentPreview = function() {
                  document.getElementById('preview-panel-wrap').classList.toggle('drawer-open');
                  document.getElementById('preview-backdrop').classList.toggle('backdrop-open');
                };

                // ── Debounce Helper Function (Step 3 Optimization) ───────────
                function debounce(func, delay = 300) {
                  let timeoutId;
                  return function(...args) {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                      func.apply(this, args);
                    }, delay);
                  };
                }

                // ── Debounced Preview Wrapper (300ms) ─────────────────────────
                window.asDebouncedPreview = debounce(function() {
                  if (typeof window.asUpdatePreview === 'function') {
                    window.asUpdatePreview();
                  }
                }, 300);

                // ── State ──────────────────────────────────────────────────────
                const TYPES_NO_OPTIONS = ['content_only','content_block','free_text','short_answer','goal','file_upload','resource_link','rating_scale','number_input','date_picker','video_embed','h1','h2','h3','h4','paragraph'];
                const TYPES_WITH_CODE  = ['scoring_block'];
                let siMode = '<?php echo ($b_show_if_arr && !empty($b_show_if_arr["blockId"])) ? "conditional" : "always"; ?>';

                // ── Init on load ───────────────────────────────────────────────
                document.addEventListener('DOMContentLoaded', function() {
                  const initTypeEl = document.getElementById('as-type');
                  if (initTypeEl) {
                    asUpdateType(initTypeEl.value, true);
                  }
                  asSetShowIf(siMode, true);
                  asCompileShowIf();
                  asUpdatePreview();

                  // Attach debounced event listeners to form input fields
                  const form = document.getElementById('block-authoring-form');
                  if (form) {
                    form.addEventListener('input', function(e) {
                      asDebouncedPreview();
                    });
                    form.addEventListener('change', function(e) {
                      asDebouncedPreview();
                    });
                  }
                });

                // ── Type change handler ────────────────────────────────────────
                window.asUpdateType = function(type, isInit) {
                  if (!type) return;
                  const isScoring      = (type === 'scoring_block');
                  const isReveal       = (type === 'result_reveal');
                  const isInteractive  = ['short_answer','free_text','dropdown','checklist','radio_buttons','file_upload','rating_scale','number_input','date_picker','goal','branching','scoring_block'].includes(type);
                  const showOpts       = ['dropdown','checklist','radio_buttons','branching','scoring'].includes(type);
                  const showGoal       = (type === 'goal');
                  const showCode       = TYPES_WITH_CODE.includes(type);

                  // Dynamic Field Labels
                  const qLabelEl  = document.getElementById('as-question-label');
                  const phLabelEl = document.getElementById('as-placeholder-label');

                  if (qLabelEl && phLabelEl) {
                    if (type === 'video_embed') {
                      qLabelEl.textContent = '① Video Title / Block Heading';
                      phLabelEl.textContent = '② Video URL / Embed String / Instructions';
                    } else if (type === 'callout_box') {
                      qLabelEl.textContent = '① Callout Title / Block Heading';
                      phLabelEl.textContent = '② Callout Body Text / Highlight';
                    } else {
                      qLabelEl.textContent = '① Block Heading / Title';
                      phLabelEl.textContent = '② Body Paragraph / Explanatory Text';
                    }
                  }

                  // Safe DOM access with guards
                  const optWrap = document.getElementById('as-options-wrap'); if (optWrap) optWrap.style.display = showOpts ? '' : 'none';
                  const scWrap  = document.getElementById('as-scoring-wrap'); if (scWrap) scWrap.style.display = isScoring ? '' : 'none';
                  const gWrap   = document.getElementById('as-goal-wrap');    if (gWrap) gWrap.style.display = showGoal ? '' : 'none';
                  const phWrap  = document.getElementById('as-placeholder-wrap'); if (phWrap) phWrap.style.display = '';
                  const thCode  = document.getElementById('th-code'); if (thCode) thCode.style.display = showCode ? '' : 'none';

                  const vWrap = document.getElementById('as-video-wrap');    if (vWrap) vWrap.style.display = (type === 'video_embed') ? '' : 'none';
                  const rWrap = document.getElementById('as-resource-wrap'); if (rWrap) rWrap.style.display = (type === 'resource_link') ? '' : 'none';
                  const fWrap = document.getElementById('as-file-wrap');     if (fWrap) fWrap.style.display = (type === 'file_upload') ? '' : 'none';
                  const rtWrap = document.getElementById('as-rating-wrap');  if (rtWrap) rtWrap.style.display = (type === 'rating_scale') ? '' : 'none';
                  const nWrap = document.getElementById('as-number-wrap');   if (nWrap) nWrap.style.display = (type === 'number_input') ? '' : 'none';

                  // Required Checkbox (#as-required-wrap) - Show ONLY when interactive
                  const reqWrap = document.getElementById('as-required-wrap');
                  if (reqWrap) {
                    reqWrap.style.display = isInteractive ? '' : 'none';
                  }

                  // Show/hide code column on all rows
                  document.querySelectorAll('.opt-code-cell').forEach(c => {
                    c.style.display = showCode ? '' : 'none';
                  });

                  const optsTbody = document.getElementById('opts-tbody');
                  if (!isInit && showOpts && optsTbody && optsTbody.children.length === 0) {
                    asAddOption();
                  }
                  asUpdatePreview();
                };

                // ── Slug helper ────────────────────────────────────────────────
                function toSlug(str) {
                  return str.toLowerCase().trim()
                    .replace(/[^a-z0-9\s_-]/g, '')
                    .replace(/[\s-]+/g, '_')
                    .substring(0, 40);
                }

                // ── Add option row ─────────────────────────────────────────────
                window.asAddOption = function(label, value, code) {
                  const tbody = document.getElementById('opts-tbody');
                  const type  = document.getElementById('as-type').value;
                  const showCode = TYPES_WITH_CODE.includes(type);
                  const idx = tbody.children.length;

                  const tr = document.createElement('tr');
                  tr.innerHTML = `
                    <td>
                      <input type="text" class="opt-input opt-label-input" placeholder="Option label" value="${escapeAttr(label||'')}" oninput="asSlugify(this)" data-idx="${idx}">
                    </td>
                    <td>
                      <input type="text" class="opt-input slug-val opt-value-input" placeholder="auto_slug" value="${escapeAttr(value||'')}" data-idx="${idx}">
                    </td>
                    <td class="opt-code-cell" style="display:${showCode?'':'none'}">
                      <select class="opt-code-select opt-code-input">
                        <option value="">-- none --</option>
                        ${['E','I','S','N','T','F','J','P','ESTP','ISTJ','INFJ','ENFP','INTJ','ENTP','ISFJ','ESTJ','INFP','INTP','ENTJ','ENFJ','ISFP','ISTP','ESFP','ESFJ'].map(c=>`<option value="${c}"${code===c?' selected':''}>${c}</option>`).join('')}
                      </select>
                    </td>
                    <td>
                      <button type="button" class="opt-del-btn" onclick="this.closest('tr').remove(); asUpdatePreview();">✕</button>
                    </td>
                  `;
                  tbody.appendChild(tr);
                  // Auto-update preview on input
                  tr.querySelectorAll('input, select').forEach(el => el.addEventListener('input', asUpdatePreview));
                  asUpdatePreview();
                };

                window.asApplyStepPreset = function(preset) {
                  const codeA = document.getElementById('sc-opt-a-code');
                  const codeB = document.getElementById('sc-opt-b-code');
                  if (!codeA || !codeB) return;
                  if (preset === 'step1') { codeA.value = 'E'; codeB.value = 'I'; }
                  else if (preset === 'step2') { codeA.value = 'S'; codeB.value = 'N'; }
                  else if (preset === 'step3') { codeA.value = 'T'; codeB.value = 'F'; }
                  else if (preset === 'step4') { codeA.value = 'J'; codeB.value = 'P'; }
                  asUpdatePreview();
                };

                window.asSlugify = function(labelInput) {
                  const tr = labelInput.closest('tr');
                  const valInput = tr.querySelector('.opt-value-input');
                  if (valInput && (!valInput.dataset.manually || valInput.dataset.manually === 'false')) {
                    valInput.value = toSlug(labelInput.value);
                  }
                  asUpdatePreview();
                };

                // Allow manual override of slug
                document.addEventListener('input', function(e) {
                  if (e.target.classList.contains('opt-value-input')) {
                    e.target.dataset.manually = 'true';
                  }
                });

                // ── Build options JSON from table / scoring builder ────────────
                function compileOptions() {
                  const type = document.getElementById('as-type').value;
                  if (type === 'scoring') {
                    const lblA  = document.getElementById('sc-opt-a-label')?.value?.trim();
                    const codeA = document.getElementById('sc-opt-a-code')?.value;
                    const lblB  = document.getElementById('sc-opt-b-label')?.value?.trim();
                    const codeB = document.getElementById('sc-opt-b-code')?.value;
                    const opts = [];
                    if (lblA) opts.push({ label: lblA, value: toSlug(lblA) || 'choice_a', hidden_code: codeA });
                    if (lblB) opts.push({ label: lblB, value: toSlug(lblB) || 'choice_b', hidden_code: codeB });
                    return opts.length ? JSON.stringify(opts) : '';
                  }

                  const rows = document.querySelectorAll('#opts-tbody tr');
                  const showCode = TYPES_WITH_CODE.includes(type);
                  const opts = [];
                  rows.forEach(tr => {
                    const lbl = tr.querySelector('.opt-label-input')?.value?.trim();
                    const val = tr.querySelector('.opt-value-input')?.value?.trim() || toSlug(lbl);
                    const code = tr.querySelector('.opt-code-input')?.value?.trim();
                    if (!lbl) return;
                    const obj = { label: lbl, value: val };
                    if (showCode && code) obj.hidden_code = code;
                    opts.push(obj);
                  });
                  return opts.length ? JSON.stringify(opts) : '';
                }

                // ── showIf mode toggle ─────────────────────────────────────────
                window.asSetShowIf = function(mode, isInit) {
                  siMode = mode;
                  document.getElementById('si-always-btn').classList.toggle('active', mode === 'always');
                  document.getElementById('si-cond-btn').classList.toggle('active', mode === 'conditional');
                  document.getElementById('si-builder').style.display = (mode === 'conditional') ? '' : 'none';
                  if (!isInit) asCompileShowIf();
                };

                window.asCompileShowIf = function() {
                  const hf = document.getElementById('hf-show-if');
                  const preview = document.getElementById('si-preview-pill');
                  if (siMode !== 'conditional') { hf.value = ''; return; }
                  const target   = document.getElementById('si-target')?.value?.trim();
                  const operator = document.getElementById('si-operator')?.value || 'equals';
                  const value    = document.getElementById('si-value')?.value?.trim();
                  if (!target || !value) { hf.value = ''; preview.textContent = '⚠ Select a target block and expected value'; return; }
                  const payload = { blockId: target, operator: operator, value: value };
                  hf.value = JSON.stringify(payload);
                  preview.textContent = `Show if block "${target}" ${operator.replace('_',' ')} "${value}"`;
                  asUpdatePreview();
                };

                // ── Composite Block Builder JS Engine ──────────────────────────
                window._availableTraits = <?php echo json_encode($available_traits ?? []); ?>;
                let cbsStack = <?php echo $b_content_schema_arr ? json_encode($b_content_schema_arr) : '[]'; ?>;

                window.cbsShowTypePicker = function() {
                  const p = document.getElementById('cbs-type-picker');
                  if (p) p.style.display = (p.style.display === 'none' || !p.style.display) ? 'block' : 'none';
                };

                window.cbsAddElement = function(type) {
                  document.getElementById('cbs-type-picker').style.display = 'none';
                  const elem = { id: 'elem_' + Date.now() + '_' + Math.floor(Math.random()*100), type: type };
                  if (type === 'heading') { elem.text = ''; elem.level = 'h3'; }
                  else if (type === 'paragraph') { elem.text = ''; }
                  else if (type === 'callout_box') { elem.text = ''; elem.variant = 'insight'; }
                  else if (type === 'input_short_answer') { elem.label = ''; elem.placeholder = ''; elem.required = true; }
                  else if (type === 'input_free_text') { elem.label = ''; elem.placeholder = ''; elem.required = true; }
                  else if (type === 'goal_statement') { elem.label = 'Core Goal Statement'; elem.placeholder = 'Define your SMART goal for this module...'; elem.required = true; }
                  else if (type === 'input_dropdown') { elem.label = ''; elem.options = 'Option A, Option B'; elem.required = true; }
                  else if (type === 'rating_scale') { elem.label = 'Self Assessment Rating'; elem.max_scale = 5; elem.low_label = 'Low'; elem.high_label = 'High'; elem.required = true; }
                  else if (type === 'resource_link') { elem.label = 'Resource Download'; elem.url = 'https://example.com/guide.pdf'; elem.button_text = 'Download Guide PDF'; }
                  else if (type === 'file_upload') { elem.label = 'Upload Supporting File'; elem.file_types = '.pdf,.docx,.png,.jpg'; elem.required = true; }
                  else if (type === 'video_embed') { elem.url = ''; elem.caption = ''; }
                  else if (type === 'trait_matrix') { elem.question = 'Select your cognitive orientation:'; elem.opt_a_label = 'Option A'; elem.opt_a_trait = 'E'; elem.opt_b_label = 'Option B'; elem.opt_b_trait = 'I'; }
                  else if (type === 'result_reveal') { elem.title = 'Strategic Profile Outcome'; }
                  cbsStack.push(elem);
                  cbsRenderStack();
                  asUpdatePreview();
                };

                window.cbsRemoveElement = function(index) {
                  cbsStack.splice(index, 1);
                  cbsRenderStack();
                  asUpdatePreview();
                };

                window.cbsUpdateElemField = function(index, key, val) {
                  if (cbsStack[index]) {
                    cbsStack[index][key] = val;
                    asUpdatePreview();
                  }
                };

                window.cbsRenderStack = function() {
                  const container = document.getElementById('cbs-stack');
                  const emptyState = document.getElementById('cbs-empty');
                  if (!container) return;

                  if (!cbsStack || cbsStack.length === 0) {
                    container.innerHTML = '';
                    if (emptyState) emptyState.style.display = 'block';
                    return;
                  }

                  if (emptyState) emptyState.style.display = 'none';

                  const typeIcons = {
                    heading: '📝', paragraph: '¶', callout_box: '💡', input_short_answer: '✏️',
                    input_free_text: '📄', goal_statement: '📌', input_dropdown: '☰', rating_scale: '⭐',
                    resource_link: '🔗', file_upload: '📁', video_embed: '▶', trait_matrix: '🎯', result_reveal: '🔮'
                  };
                  const typeNames = {
                    heading: 'Heading', paragraph: 'Paragraph Text', callout_box: 'Callout Box', input_short_answer: 'Short Answer Input',
                    input_free_text: 'Free Text Reflection', goal_statement: 'Goal Statement (Worksheet)', input_dropdown: 'Dropdown Input',
                    rating_scale: '1-to-5 Rating Scale', resource_link: 'Resource Download Card', file_upload: 'File Upload',
                    video_embed: 'Video Embed', trait_matrix: 'Archetype / Trait Scoring Matrix', result_reveal: 'Profile Result Reveal'
                  };

                  container.innerHTML = cbsStack.map((elem, idx) => `
                    <div class="cbs-row-card" data-index="${idx}" draggable="true" ondragstart="cbsDragStart(event, ${idx})" ondragover="cbsDragOver(event)" ondrop="cbsDrop(event, ${idx})" style="background:#fff; border:1px solid #E0E7FF; border-radius:0.75rem; padding:0.65rem 0.75rem; box-shadow:0 1px 3px rgba(0,0,0,0.03); transition:all 0.15s ease;">
                      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.4rem;">
                        <div style="display:flex; align-items:center; gap:0.4rem; cursor:grab;">
                          <span style="color:#A5B4FC; font-weight:bold; font-size:0.85rem; user-select:none;">⋮⋮</span>
                          <span style="font-size:0.85rem;">${typeIcons[elem.type] || '⚙️'}</span>
                          <span style="font-size:0.72rem; font-weight:700; color:#3730A3;">${typeNames[elem.type] || elem.type}</span>
                        </div>
                        <button type="button" onclick="cbsRemoveElement(${idx})" style="color:#EF4444; background:none; border:none; cursor:pointer; font-size:0.75rem; padding:2px 4px; border-radius:4px;" title="Delete Element">✕</button>
                      </div>
                      <div style="display:flex; flex-direction:column; gap:0.4rem; padding-left:1.25rem;">
                        ${cbsRenderElemControls(elem, idx)}
                      </div>
                    </div>
                  `).join('');
                };

                function cbsRenderElemControls(elem, idx) {
                  if (elem.type === 'heading') {
                    return `
                      <div style="display:flex; gap:0.4rem;">
                        <select onchange="cbsUpdateElemField(${idx}, 'level', this.value)" style="padding:0.25rem 0.4rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem; background:#F9FAFB;">
                          <option value="h1" ${elem.level==='h1'?'selected':''}>H1</option>
                          <option value="h2" ${elem.level==='h2'?'selected':''}>H2</option>
                          <option value="h3" ${elem.level==='h3'||!elem.level?'selected':''}>H3</option>
                          <option value="h4" ${elem.level==='h4'?'selected':''}>H4</option>
                        </select>
                        <input type="text" value="${escapeAttr(elem.text||'')}" oninput="cbsUpdateElemField(${idx}, 'text', this.value)" placeholder="Heading text..." style="flex:1; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                      </div>
                    `;
                  } else if (elem.type === 'paragraph') {
                    return `
                      <textarea oninput="cbsUpdateElemField(${idx}, 'text', this.value)" placeholder="Paragraph / explanatory text..." style="width:100%; padding:0.35rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; min-height:42px; resize:vertical;">${escapeHtml(elem.text||'')}</textarea>
                    `;
                  } else if (elem.type === 'callout_box') {
                    return `
                      <div style="display:flex; gap:0.4rem; margin-bottom:0.2rem;">
                        <select onchange="cbsUpdateElemField(${idx}, 'variant', this.value)" style="padding:0.25rem 0.4rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem; background:#F9FAFB;">
                          <option value="insight" ${elem.variant==='insight'||!elem.variant?'selected':''}>💡 Insight (Indigo)</option>
                          <option value="warning" ${elem.variant==='warning'?'selected':''}>⚠️ Warning (Amber)</option>
                          <option value="action" ${elem.variant==='action'?'selected':''}>🎯 Action Item (Emerald)</option>
                        </select>
                      </div>
                      <textarea oninput="cbsUpdateElemField(${idx}, 'text', this.value)" placeholder="Callout message..." style="width:100%; padding:0.35rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; min-height:42px; resize:vertical;">${escapeHtml(elem.text||'')}</textarea>
                    `;
                  } else if (elem.type === 'input_short_answer' || elem.type === 'input_free_text' || elem.type === 'goal_statement') {
                    return `
                      <input type="text" value="${escapeAttr(elem.label||'')}" oninput="cbsUpdateElemField(${idx}, 'label', this.value)" placeholder="${elem.type==='goal_statement'?'Goal Worksheet Field Label...':'Field Label...'}" style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem;">
                        <input type="text" value="${escapeAttr(elem.placeholder||'')}" oninput="cbsUpdateElemField(${idx}, 'placeholder', this.value)" placeholder="Placeholder guidance..." style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                        <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.68rem; color:#4B5563; font-weight:600; cursor:pointer;">
                          <input type="checkbox" ${elem.required!==false?'checked':''} onchange="cbsUpdateElemField(${idx}, 'required', this.checked)"> Required
                        </label>
                      </div>
                      ${elem.type==='goal_statement'?'<div style="font-size:0.65rem; color:#D97706; font-weight:bold; margin-top:0.2rem;">📌 Mapped to Mentee Goals Worksheet</div>':''}
                    `;
                  } else if (elem.type === 'input_dropdown') {
                    return `
                      <input type="text" value="${escapeAttr(elem.label||'')}" oninput="cbsUpdateElemField(${idx}, 'label', this.value)" placeholder="Dropdown Label..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <input type="text" value="${escapeAttr(elem.options||'')}" oninput="cbsUpdateElemField(${idx}, 'options', this.value)" placeholder="Options (comma separated, e.g. High, Medium, Low)..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.68rem; color:#4B5563; font-weight:600; cursor:pointer;">
                        <input type="checkbox" ${elem.required!==false?'checked':''} onchange="cbsUpdateElemField(${idx}, 'required', this.checked)"> Required
                      </label>
                    `;
                  } else if (elem.type === 'rating_scale') {
                    return `
                      <input type="text" value="${escapeAttr(elem.label||'')}" oninput="cbsUpdateElemField(${idx}, 'label', this.value)" placeholder="Rating Prompt Label..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <div style="display:flex; gap:0.4rem;">
                        <input type="text" value="${escapeAttr(elem.low_label||'Low')}" oninput="cbsUpdateElemField(${idx}, 'low_label', this.value)" placeholder="Low Label (e.g. Low)" style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                        <input type="text" value="${escapeAttr(elem.high_label||'High')}" oninput="cbsUpdateElemField(${idx}, 'high_label', this.value)" placeholder="High Label (e.g. High)" style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                      </div>
                    `;
                  } else if (elem.type === 'resource_link') {
                    return `
                      <input type="text" value="${escapeAttr(elem.label||'')}" oninput="cbsUpdateElemField(${idx}, 'label', this.value)" placeholder="Resource Title..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <div style="display:flex; gap:0.4rem;">
                        <input type="text" value="${escapeAttr(elem.url||'')}" oninput="cbsUpdateElemField(${idx}, 'url', this.value)" placeholder="Download URL..." style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                        <input type="text" value="${escapeAttr(elem.button_text||'Download Guide PDF')}" oninput="cbsUpdateElemField(${idx}, 'button_text', this.value)" placeholder="Button Text..." style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                      </div>
                    `;
                  } else if (elem.type === 'file_upload') {
                    return `
                      <input type="text" value="${escapeAttr(elem.label||'')}" oninput="cbsUpdateElemField(${idx}, 'label', this.value)" placeholder="Upload Prompt Label..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                      <div style="display:flex; align-items:center; gap:0.4rem;">
                        <input type="text" value="${escapeAttr(elem.file_types||'.pdf,.docx,.png,.jpg')}" oninput="cbsUpdateElemField(${idx}, 'file_types', this.value)" placeholder="Allowed Extensions (.pdf, .png)..." style="flex:1; padding:0.25rem 0.5rem; font-size:0.7rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                        <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.68rem; color:#4B5563; font-weight:600; cursor:pointer;">
                          <input type="checkbox" ${elem.required!==false?'checked':''} onchange="cbsUpdateElemField(${idx}, 'required', this.checked)"> Required
                        </label>
                      </div>
                    `;
                  } else if (elem.type === 'video_embed') {
                    return `
                      <input type="text" value="${escapeAttr(elem.url||'')}" oninput="cbsUpdateElemField(${idx}, 'url', this.value)" placeholder="Video URL or Embed Code..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                    `;
                  } else if (elem.type === 'trait_matrix') {
                    const traitsList = (window._availableTraits && window._availableTraits.length > 0)
                      ? window._availableTraits
                      : [{code:'E',label:'Extraversion'},{code:'I',label:'Introversion'},{code:'S',label:'Sensing'},{code:'N',label:'Intuition'},{code:'T',label:'Thinking'},{code:'F',label:'Feeling'},{code:'J',label:'Judging'},{code:'P',label:'Perceiving'}];
                    
                    const optAOptions = traitsList.map(t => `<option value="${t.code}" ${ (elem.opt_a_trait||'E') === t.code ? 'selected' : '' }>[${t.code}] ${t.label}</option>`).join('');
                    const optBOptions = traitsList.map(t => `<option value="${t.code}" ${ (elem.opt_b_trait||'I') === t.code ? 'selected' : '' }>[${t.code}] ${t.label}</option>`).join('');

                    return `
                      <input type="text" value="${escapeAttr(elem.question||'')}" oninput="cbsUpdateElemField(${idx}, 'question', this.value)" placeholder="Scoring Question Prompt..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.3rem;">
                      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.4rem;">
                        <div>
                          <input type="text" value="${escapeAttr(elem.opt_a_label||'')}" oninput="cbsUpdateElemField(${idx}, 'opt_a_label', this.value)" placeholder="Option A Label..." style="width:100%; padding:0.2rem 0.4rem; font-size:0.68rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                          <select onchange="cbsUpdateElemField(${idx}, 'opt_a_trait', this.value)" style="width:100%; padding:0.2rem 0.4rem; font-size:0.68rem; font-family:monospace; color:#D97706; font-weight:bold; border:1px solid #D1D5DB; border-radius:0.375rem; background:#FFFBEB;">
                            ${optAOptions}
                          </select>
                        </div>
                        <div>
                          <input type="text" value="${escapeAttr(elem.opt_b_label||'')}" oninput="cbsUpdateElemField(${idx}, 'opt_b_label', this.value)" placeholder="Option B Label..." style="width:100%; padding:0.2rem 0.4rem; font-size:0.68rem; border:1px solid #D1D5DB; border-radius:0.375rem; margin-bottom:0.2rem;">
                          <select onchange="cbsUpdateElemField(${idx}, 'opt_b_trait', this.value)" style="width:100%; padding:0.2rem 0.4rem; font-size:0.68rem; font-family:monospace; color:#D97706; font-weight:bold; border:1px solid #D1D5DB; border-radius:0.375rem; background:#FFFBEB;">
                            ${optBOptions}
                          </select>
                        </div>
                      </div>
                    `;
                  } else if (elem.type === 'result_reveal') {
                    return `
                      <input type="text" value="${escapeAttr(elem.title||'')}" oninput="cbsUpdateElemField(${idx}, 'title', this.value)" placeholder="Report Heading Title..." style="width:100%; padding:0.25rem 0.5rem; font-size:0.72rem; border:1px solid #D1D5DB; border-radius:0.375rem;">
                    `;
                  }
                  return '';
                }

                // Drag & Drop reordering
                let cbsDragIdx = null;
                window.cbsDragStart = function(e, idx) { cbsDragIdx = idx; e.dataTransfer.effectAllowed = 'move'; };
                window.cbsDragOver = function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; };
                window.cbsDrop = function(e, targetIdx) {
                  e.preventDefault();
                  if (cbsDragIdx !== null && cbsDragIdx !== targetIdx) {
                    const item = cbsStack.splice(cbsDragIdx, 1)[0];
                    cbsStack.splice(targetIdx, 0, item);
                    cbsRenderStack();
                    asUpdatePreview();
                  }
                  cbsDragIdx = null;
                };

                // Initialize stack on load
                setTimeout(() => { cbsRenderStack(); }, 50);

                // ── Compile and submit ─────────────────────────────────────────
                window.asCompileAndSubmit = function(e) {
                  // Guard against saving a dropdown with blank/whitespace-only options,
                  // which renders as an unselectable, unusable <select> for students.
                  if (cbsStack) {
                    cbsStack.forEach(elem => {
                      if (elem.type === 'input_dropdown' && (!elem.options || !elem.options.trim())) {
                        elem.options = 'Option A, Option B';
                      }
                    });
                  }

                  // Compile composite content_schema JSON payload if stack has elements
                  const csField = document.getElementById('hf-content-schema');
                  if (csField) {
                    csField.value = (cbsStack && cbsStack.length > 0) ? JSON.stringify(cbsStack) : '';
                  }
                };

                 // ── Real-time Preview ──────────────────────────────────────────
                window.asUpdatePreview = function() {
                  const empty = document.getElementById('as-preview-empty');
                  const card  = document.getElementById('as-preview-card');

                  // Composite mode preview: render cbsStack if non-empty
                  if (cbsStack && cbsStack.length > 0) {
                    if (empty) empty.style.display = 'none';
                    if (card) card.style.display = '';

                    const subHTML = cbsStack.map(elem => {
                      if (elem.type === 'heading') {
                        const tag = elem.level || 'h3';
                        const sizes = { h1:'text-2xl font-extrabold', h2:'text-xl font-bold', h3:'text-lg font-bold', h4:'text-base font-semibold' };
                        return `<${tag} class="${sizes[tag]||'text-lg font-bold'} text-gray-900 font-display mb-2 leading-tight">${escapeHtml(elem.text||'Heading Title')}</${tag}>`;
                      } else if (elem.type === 'paragraph') {
                        return `<p class="text-sm text-gray-600 leading-relaxed font-normal mb-3">${escapeHtml(elem.text||'Body paragraph text...').replace(/\n/g, '<br>')}</p>`;
                      } else if (elem.type === 'callout_box') {
                        const bgMap = { insight:'bg-indigo-50 border-indigo-200 text-indigo-900', warning:'bg-amber-50 border-amber-200 text-amber-900', action:'bg-emerald-50 border-emerald-200 text-emerald-900' };
                        return `<div class="mb-3 p-3 rounded-xl border ${bgMap[elem.variant]||bgMap.insight} text-xs font-medium">${escapeHtml(elem.text||'Callout message text...')}</div>`;
                      } else if (elem.type === 'input_short_answer') {
                        return `<div class="mb-3">
                          ${elem.label ? `<label class="block text-xs font-bold text-gray-700 mb-1">${escapeHtml(elem.label)}${elem.required!==false?' <span class="text-red-500">*</span>':''}</label>` : ''}
                          <input class="pv-input" type="text" placeholder="${escapeAttr(elem.placeholder||'Type answer...')}" disabled>
                        </div>`;
                      } else if (elem.type === 'input_free_text' || elem.type === 'goal_statement') {
                        return `<div class="mb-3">
                          ${elem.label ? `<label class="block text-xs font-bold text-gray-700 mb-1">${escapeHtml(elem.label)}${elem.required!==false?' <span class="text-red-500">*</span>':''}</label>` : ''}
                          <textarea class="pv-input" rows="3" placeholder="${escapeAttr(elem.placeholder||'Type reflection...')}" disabled style="resize:none;"></textarea>
                          ${elem.type==='goal_statement'?'<div style="margin-top:0.3rem;font-size:9px;color:#C99700;background:rgba(201,151,0,0.08);border:1px solid rgba(201,151,0,0.2);border-radius:6px;padding:3px 8px;display:inline-block;">📌 Mapped to Mentee Goals Worksheet</div>':''}
                        </div>`;
                      } else if (elem.type === 'input_dropdown') {
                        const optsArr = (elem.options||'Option A, Option B').split(',').map(s=>s.trim()).filter(Boolean);
                        return `<div class="mb-3">
                          ${elem.label ? `<label class="block text-xs font-bold text-gray-700 mb-1">${escapeHtml(elem.label)}${elem.required!==false?' <span class="text-red-500">*</span>':''}</label>` : ''}
                          <select class="pv-input" style="cursor:pointer;"><option value="">Select option...</option>${optsArr.map(o=>`<option>${escapeHtml(o)}</option>`).join('')}</select>
                        </div>`;
                      } else if (elem.type === 'rating_scale') {
                        return `<div class="mb-3 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                          <label class="block text-xs font-bold text-gray-700 mb-2">${escapeHtml(elem.label||'Rating Scale')}</label>
                          <div class="flex justify-between text-[10px] text-gray-500 font-bold mb-1"><span>1 (${escapeHtml(elem.low_label||'Low')})</span><span>5 (${escapeHtml(elem.high_label||'High')})</span></div>
                          <div class="flex justify-between gap-1">${[1,2,3,4,5].map(n=>`<div class="flex-1 py-1 bg-white border border-gray-300 rounded text-center text-xs font-bold">${n}</div>`).join('')}</div>
                        </div>`;
                      } else if (elem.type === 'resource_link') {
                        return `<div class="mb-3 p-3 bg-indigo-50 border border-indigo-100 rounded-xl flex items-center justify-between">
                          <span class="text-xs font-bold text-indigo-900">🔗 ${escapeHtml(elem.label||'Resource Card')}</span>
                          <span class="px-2.5 py-1 bg-indigo-600 text-white rounded text-xs font-bold">${escapeHtml(elem.button_text||'Download PDF')}</span>
                        </div>`;
                      } else if (elem.type === 'file_upload') {
                        return `<div class="mb-3 p-3 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 text-center">
                          <span class="text-xs font-bold text-gray-700">📁 ${escapeHtml(elem.label||'File Upload')}</span>
                          <span class="text-[10px] text-gray-400 block mt-0.5">Accepted: ${escapeHtml(elem.file_types||'.pdf,.docx')}</span>
                        </div>`;
                      } else if (elem.type === 'video_embed') {
                        return `<div class="mb-3 p-3 bg-slate-900 rounded-xl text-center text-white font-mono text-xs">
                          ▶ Video Presentation Container (${escapeHtml(elem.url||'No URL set')})
                        </div>`;
                      } else if (elem.type === 'trait_matrix') {
                        return `<div class="mb-3 p-3 bg-amber-50/60 border border-amber-200 rounded-xl space-y-2">
                          <p class="text-xs font-bold text-amber-900">🎯 ${escapeHtml(elem.question||'Trait Scoring Question')}</p>
                          <div class="space-y-1">
                            <div class="p-2 bg-white rounded border border-amber-100 flex items-center justify-between text-xs"><span>${escapeHtml(elem.opt_a_label||'Option A')}</span><span class="font-mono text-amber-700 font-bold">[ ${escapeHtml(elem.opt_a_trait||'E')} ]</span></div>
                            <div class="p-2 bg-white rounded border border-amber-100 flex items-center justify-between text-xs"><span>${escapeHtml(elem.opt_b_label||'Option B')}</span><span class="font-mono text-amber-700 font-bold">[ ${escapeHtml(elem.opt_b_trait||'I')} ]</span></div>
                          </div>
                        </div>`;
                      } else if (elem.type === 'result_reveal') {
                        return `<div class="mb-4 p-5 bg-slate-900 text-white rounded-2xl border border-slate-800 shadow-lg space-y-3">
                          <div class="flex items-center justify-between">
                            <span class="text-[9px] font-mono font-bold text-indigo-400 uppercase tracking-widest">🔮 PROFILE REVEAL REPORT</span>
                            <span class="text-[9px] font-mono bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded border border-indigo-500/30 font-bold">16-ARCHETYPE ENGINE</span>
                          </div>
                          <div class="text-base font-bold font-display text-white">${escapeHtml(elem.title||'Your Strategic Elysian Success Profile')}</div>
                          <div class="grid grid-cols-2 gap-2 text-[10px] text-slate-300 font-medium pt-1">
                            <div class="p-2.5 bg-slate-800/80 rounded-xl border border-emerald-500/30 text-emerald-300 font-semibold flex items-center gap-1.5">⚡ <span>Core Strengths (4 items)</span></div>
                            <div class="p-2.5 bg-slate-800/80 rounded-xl border border-indigo-500/30 text-indigo-300 font-semibold flex items-center gap-1.5">🎯 <span>Growth Areas (4 items)</span></div>
                          </div>
                          <div class="p-2.5 bg-slate-800/50 rounded-xl text-[10px] font-mono text-amber-400 font-semibold flex items-center justify-between border border-slate-700/50">
                            <span>📌 4 Prescribed SMART Action Plan Goals</span>
                            <span class="text-[8px] text-amber-300 bg-amber-500/20 px-1.5 py-0.5 rounded">Auto-Calculated</span>
                          </div>
                        </div>`;
                      }
                      return '';
                    }).join('');

                    if (card) {
                      card.innerHTML = `
                        <div style="border:1.5px solid #C7D2FE; background:#FFFFFF; border-radius:1rem; padding:1.25rem; box-shadow:0 4px 12px rgba(79,70,229,0.06);">
                          <div class="mb-3 border-b border-indigo-50 pb-2">
                            <span class="pv-type-badge" style="background:#EEF2FF; color:#4F46E5;">${cbsStack.length} element${cbsStack.length !== 1 ? 's' : ''}</span>
                          </div>
                          ${subHTML}
                        </div>`;
                    }
                    return;
                  }

                  const type     = document.getElementById('as-type')?.value || '';
                  const question = document.getElementById('as-question')?.value?.trim() || '';
                  const ph       = document.getElementById('as-placeholder')?.value?.trim() || '';
                  const required = document.getElementById('as-required')?.checked;
                  const hLevel   = document.getElementById('as-heading-level')?.value || 'h3';
                  const style    = document.getElementById('as-style')?.value || 'standard';

                  if (!question && !ph) {
                    if (empty) empty.style.display = '';
                    if (card) card.style.display  = 'none';
                    return;
                  }
                  if (empty) empty.style.display = 'none';
                  if (card) card.style.display  = '';


                  // Determine showIf pill
                  let condPill = '';
                  if (siMode === 'conditional') {
                    const target = document.getElementById('si-target')?.value;
                    const op     = document.getElementById('si-operator')?.value;
                    const val    = document.getElementById('si-value')?.value;
                    if (target && val) {
                      condPill = `<span class="pv-cond-pill">Shows if &ldquo;${escapeHtml(target)}&rdquo; ${(op||'').replace('_',' ')} &ldquo;${escapeHtml(val)}&rdquo;</span>`;
                    }
                  }

                  // Style Container CSS Class
                  let styleCss = 'border:1px solid #E2E8F0; background:#FFFFFF; border-radius:1rem; padding:1.25rem; shadow:0 1px 2px rgba(0,0,0,0.05);';
                  if (style === 'callout' || type === 'callout_box') {
                    styleCss = 'border-left:4px solid #0EA5E9; border:1px solid #E0F2FE; background:#F0F9FF; border-radius:1rem; padding:1.25rem;';
                  } else if (style === 'warning') {
                    styleCss = 'border-left:4px solid #F59E0B; border:1px solid #FEF3C7; background:#FFFBEB; border-radius:1rem; padding:1.25rem;';
                  } else if (style === 'action') {
                    styleCss = 'border:2px solid #10B981; background:#ECFDF5; border-radius:1rem; padding:1.25rem; shadow:0 4px 6px -1px rgba(16,185,129,0.1);';
                  } else if (style === 'minimal') {
                    styleCss = 'border:0; background:transparent; padding:0.5rem 0;';
                  }

                  // Heading HTML based on selected tag
                  let headHTML = '';
                  if (question) {
                    if (hLevel === 'h1') headHTML = `<h1 class="text-2xl font-extrabold text-gray-900 font-display mb-1.5 leading-tight">${escapeHtml(question)}</h1>`;
                    else if (hLevel === 'h2') headHTML = `<h2 class="text-xl font-bold text-gray-800 font-display mb-1.5 leading-snug">${escapeHtml(question)}</h2>`;
                    else if (hLevel === 'h4') headHTML = `<h4 class="text-base font-semibold text-gray-700 font-display mb-1.5">${escapeHtml(question)}</h4>`;
                    else headHTML = `<h3 class="text-lg font-bold text-gray-900 font-display mb-1.5 leading-snug">${escapeHtml(question)}</h3>`;
                  }

                  // Attached control preview
                  let inputHTML = '';
                  if (type === 'short_answer') {
                    inputHTML = `<input class="pv-input" type="text" placeholder="Type your answer here..." disabled>`;
                  } else if (type === 'free_text' || type === 'goal') {
                    inputHTML = `<textarea class="pv-input" rows="3" placeholder="Type your reflection here..." disabled style="resize:none;"></textarea>`;
                    if (type === 'goal') {
                      inputHTML += `<div style="margin-top:0.5rem;font-size:9px;color:#C99700;background:rgba(201,151,0,0.08);border:1px solid rgba(201,151,0,0.2);border-radius:6px;padding:4px 10px;display:inline-block;">📌 Mapped to Goals Worksheet</div>`;
                    }
                  } else if (type === 'dropdown') {
                    const opts = getPreviewOptions();
                    const optHTML = opts.length ? opts.map(o => `<option>${escapeHtml(o.label)}</option>`).join('') : '<option>Option A</option><option>Option B</option>';
                    inputHTML = `<select class="pv-input" style="cursor:pointer;"><option value="">Select an option...</option>${optHTML}</select>`;
                  } else if (type === 'checklist' || type === 'radio_buttons') {
                    const opts = getPreviewOptions();
                    const items = opts.length ? opts : [{label:'Option A'},{label:'Option B'}];
                    inputHTML = `<div class="space-y-2 mt-2">${items.map(o => `
                      <div class="flex items-center gap-2.5 p-2.5 rounded-lg border border-gray-200 bg-white">
                        <input type="${type==='radio_buttons'?'radio':'checkbox'}" disabled class="w-4 h-4 text-emerald-600 rounded">
                        <span class="text-xs text-gray-800 font-medium">${escapeHtml(o.label)}</span>
                      </div>
                    `).join('')}</div>`;
                  } else if (type === 'file_upload') {
                    const types = document.getElementById('cfg-file-types')?.value || '.pdf,.docx,.png,.jpg';
                    const maxsz = document.getElementById('cfg-file-maxsize')?.value || '10MB';
                    inputHTML = `<div class="p-4 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 text-center flex flex-col items-center justify-center gap-1">
                      <span class="text-xl">📁</span>
                      <span class="text-xs font-bold text-gray-700">File Upload Container</span>
                      <span class="text-[10px] text-gray-400">Accepted: ${escapeHtml(types)} (Max ${escapeHtml(maxsz)})</span>
                    </div>`;
                  } else if (type === 'resource_link') {
                    const rurl = document.getElementById('cfg-res-url')?.value || '#';
                    const rbtn = document.getElementById('cfg-res-button')?.value || 'Download Resource';
                    inputHTML = `<div class="p-3.5 bg-indigo-50/50 border border-indigo-100 rounded-xl flex items-center justify-between gap-3">
                      <div class="flex items-center gap-2.5">
                        <span class="text-lg">📄</span>
                        <span class="text-xs font-bold text-indigo-900">Resource Download Target</span>
                      </div>
                      <span class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow-sm">${escapeHtml(rbtn)}</span>
                    </div>`;
                  } else if (type === 'rating_scale') {
                    const maxVal = parseInt(document.getElementById('cfg-rate-max')?.value || '5', 10);
                    const lowLbl = document.getElementById('cfg-rate-low')?.value || 'Low';
                    const highLbl = document.getElementById('cfg-rate-high')?.value || 'High';
                    const numArr = Array.from({length: maxVal}, (_, i) => i + 1);
                    inputHTML = `<div class="p-3 bg-white border border-gray-200 rounded-xl mt-2">
                      <div class="flex items-center justify-between text-[10px] text-gray-500 font-mono font-bold mb-2">
                        <span>1 (${escapeHtml(lowLbl)})</span>
                        <span>${maxVal} (${escapeHtml(highLbl)})</span>
                      </div>
                      <div class="flex justify-between gap-1.5">
                        ${numArr.map(n => `<div class="flex-1 py-1.5 rounded-lg border border-gray-300 bg-gray-50 text-center text-xs font-bold text-gray-700">${n}</div>`).join('')}
                      </div>
                    </div>`;
                  } else if (type === 'number_input') {
                    const unit = document.getElementById('cfg-num-unit')?.value || '';
                    inputHTML = `<input class="pv-input" type="number" placeholder="Enter numeric value ${unit ? `(${escapeAttr(unit)})` : ''}..." disabled>`;
                  } else if (type === 'date_picker') {
                    inputHTML = `<input class="pv-input" type="date" disabled>`;
                  } else if (type === 'video_embed') {
                    const vurl = document.getElementById('cfg-video-url')?.value || '';
                    inputHTML = `<div class="p-4 bg-slate-900 rounded-xl text-center text-white flex flex-col items-center justify-center gap-2">
                      <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-lg">▶</div>
                      <span class="text-xs font-semibold">Video Presentation Player</span>
                      <span class="text-[9px] text-slate-400 font-mono">${escapeHtml(vurl || 'Video URL or Embed String')}</span>
                    </div>`;
                  } else if (['branching','scoring'].includes(type)) {
                    const opts = getPreviewOptions();
                    const items = opts.length ? opts : [{label:'Option A'},{label:'Option B'}];
                    inputHTML = items.map(o => `
                      <div class="pv-option">
                        <div class="pv-option-dot"></div>
                        <span class="pv-option-label">${escapeHtml(o.label)}</span>
                      </div>`).join('');
                  } else if (type === 'result_reveal') {
                    inputHTML = `<div style="text-align:center;padding:1.5rem 0;">
                      <div style="font-size:2rem;margin-bottom:0.5rem;">🎯</div>
                      <div style="font-size:0.8rem;color:#475569;">Profile Reveal Block &mdash; calculated from scoring answers</div>
                    </div>`;
                  }

                  const isInteractive = ['short_answer','free_text','dropdown','checklist','radio_buttons','file_upload','rating_scale','number_input','date_picker','goal','branching','scoring_block'].includes(type);

                  card.innerHTML = `
                    <div style="${styleCss}">
                      <div class="flex items-center justify-between mb-2">
                        <span class="pv-type-badge">${escapeHtml(type.replace('_',' '))}</span>
                        <span class="text-[9px] font-mono text-gray-400 uppercase font-bold">${escapeHtml(style)} | ${escapeHtml(hLevel)}</span>
                      </div>
                      <div class="mb-3">
                        ${headHTML}
                        ${(ph && type !== 'video_embed') ? `<p class="text-sm text-gray-600 leading-relaxed font-normal">${escapeHtml(ph).replace(/\n/g, '<br>')}</p>` : ''}
                      </div>
                      ${inputHTML}
                      ${(required && isInteractive) ? `<div class="mt-3"><span class="pv-required-badge">Answer Required</span></div>` : ''}
                      ${condPill}
                    </div>`;
                };

                function getPreviewOptions() {
                  const type = document.getElementById('as-type')?.value;
                  if (type === 'scoring') {
                    const lblA  = document.getElementById('sc-opt-a-label')?.value?.trim();
                    const codeA = document.getElementById('sc-opt-a-code')?.value;
                    const lblB  = document.getElementById('sc-opt-b-label')?.value?.trim();
                    const codeB = document.getElementById('sc-opt-b-code')?.value;
                    const opts = [];
                    if (lblA) opts.push({ label: lblA, value: toSlug(lblA), hidden_code: codeA });
                    if (lblB) opts.push({ label: lblB, value: toSlug(lblB), hidden_code: codeB });
                    return opts;
                  }

                  const rows = document.querySelectorAll('#opts-tbody tr');
                  const opts = [];
                  rows.forEach(tr => {
                    const lbl  = tr.querySelector('.opt-label-input')?.value?.trim();
                    const val  = tr.querySelector('.opt-value-input')?.value?.trim();
                    const code = tr.querySelector('.opt-code-input')?.value?.trim();
                    if (lbl) opts.push({ label: lbl, value: val, hidden_code: code });
                  });
                  return opts;
                }

                // ── Utils ──────────────────────────────────────────────────────
                function escapeAttr(str) {
                  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }

                // Load existing options into the table
                const existingOpts = <?php echo $b_options_arr ? json_encode($b_options_arr) : '[]'; ?>;
                existingOpts.forEach(o => asAddOption(o.label, o.value, o.hidden_code||''));

              })();
              </script>

            <?php else: ?>
              <!-- ── Empty State ── -->
              <div class="elysian-card flex flex-col items-center justify-center h-full text-gray-400 text-xs py-12">
                <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-gray-500 font-medium">Select a pillar to add blocks,</p>
                <p class="text-gray-400 mt-0.5">or choose a block to modify parameters.</p>
              </div>
            <?php endif; ?>

          </div><!-- /.right col -->
        </div><!-- /.grid -->

        <!-- ══ Drag-and-Drop Reorder + Duplicate JS ══ -->
        <script>
        // ── Drag-and-drop Component & Block Reordering (Batched JSON Update) ──
        (function() {
          let draggedComp = null;

          function initCompDragAndDrop() {
            const items = document.querySelectorAll('.ely-tree-item[draggable="true"]');
            const dropZones = document.querySelectorAll('.comp-drop-zone');

            items.forEach(item => {
              item.addEventListener('dragstart', function(e) {
                draggedComp = this;
                this.classList.add('opacity-50', 'border-dashed', 'border-indigo-400');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', this.dataset.compId || '');
              });

              item.addEventListener('dragend', function() {
                this.classList.remove('opacity-50', 'border-dashed', 'border-indigo-400');
                document.querySelectorAll('.ely-tree-item').forEach(el => el.classList.remove('bg-indigo-50', 'border-indigo-300'));
                draggedComp = null;
              });

              item.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (draggedComp && draggedComp !== this) {
                  this.classList.add('bg-indigo-50', 'border-indigo-300');
                }
              });

              item.addEventListener('dragleave', function() {
                this.classList.remove('bg-indigo-50', 'border-indigo-300');
              });

              item.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('bg-indigo-50', 'border-indigo-300');
                if (!draggedComp || draggedComp === this) return;

                const parentZone = this.closest('.comp-drop-zone');
                if (parentZone) {
                  const rect = this.getBoundingClientRect();
                  const offset = e.clientY - rect.top;
                  if (offset > rect.height / 2) {
                    parentZone.insertBefore(draggedComp, this.nextSibling);
                  } else {
                    parentZone.insertBefore(draggedComp, this);
                  }
                  saveBatchComponentOrder(parentZone);
                }
              });
            });

            dropZones.forEach(zone => {
              zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('bg-indigo-50/50');
              });

              zone.addEventListener('dragleave', function() {
                this.classList.remove('bg-indigo-50/50');
              });

              zone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('bg-indigo-50/50');
                if (!draggedComp) return;

                // Move component into target drop zone if not already dropped inside child handler
                if (e.target === this || e.target.classList.contains('empty-block-msg')) {
                  const emptyMsg = this.querySelector('.empty-block-msg');
                  if (emptyMsg) emptyMsg.remove();
                  this.appendChild(draggedComp);
                  saveBatchComponentOrder(this);
                }
              });
            });
          }

          // Batch collect updated sort_order and block_id, then fire single JSON AJAX request
          function saveBatchComponentOrder(zone) {
            const pillarTree = zone.closest('#pillars-list') || document;
            const allItems = pillarTree.querySelectorAll('.ely-tree-item');
            const payload = [];

            // Compute global/block-level sort_order across all components in the active tree
            let currentOrder = 1;
            allItems.forEach(item => {
              const compId = item.dataset.compId;
              const parentZone = item.closest('.comp-drop-zone');
              const blockId = parentZone ? parentZone.dataset.blockId : item.dataset.blockId;

              if (compId) {
                // Update local dataset blockId if moved across blocks
                if (blockId) item.dataset.blockId = blockId;
                payload.push({
                  id: compId,
                  block_id: blockId || null,
                  sort_order: currentOrder++
                });
              }
            });

            if (payload.length === 0) return;

            // Visual feedback pulse
            if (draggedComp) {
              draggedComp.style.transition = 'box-shadow 0.3s';
              draggedComp.style.boxShadow = '0 0 0 2px #BBF1D2';
              setTimeout(() => { draggedComp.style.boxShadow = ''; }, 700);
            }

            // Single AJAX request sending batched JSON
            const formData = new FormData();
            formData.append('batch_reorder_components', '1');
            formData.append('components_json', JSON.stringify(payload));

            fetch('/mentor/index.php?ajax=1', {
              method: 'POST',
              body: formData
            })
            .then(res => res.json())
            .then(data => {
              console.log('Batch reorder successful:', data);
            })
            .catch(err => {
              console.error('Error executing batch reorder:', err);
            });
          }

          document.addEventListener('DOMContentLoaded', initCompDragAndDrop);
        })();

        // ── Duplicate block ─────────────────────────────────────────────────
        function duplicateBlock(blockId, progId) {
          if (!confirm('Duplicate this block? A copy will be added to the same pillar.')) return;
          window.location = '/mentor/index.php?action=duplicate_block&id=' + encodeURIComponent(blockId) + '&program_id=' + encodeURIComponent(progId);
        }


        // ── escapeHtml utility (global) ────────────────────────────────────
        if (typeof escapeHtml === 'undefined') {
          window.escapeHtml = function(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(String(str)));
            return d.innerHTML;
          };
        }
        </script>
      <?php elseif ($active_tab === 'archetypes'): ?>
        <!-- ── TAB 5: 16-ARCHETYPE OUTCOME MANAGER ── -->
        <?php 
        $all_codes = ['INTJ','INTP','ENTJ','ENTP','INFJ','INFP','ENFJ','ENFP','ISTJ','ISFJ','ESTJ','ESFJ','ISTP','ISFP','ESTP','ESFP'];
        $sel_code = isset($_GET['selected_code']) ? strtoupper(trim($_GET['selected_code'])) : 'ISFP';
        if (!in_array($sel_code, $all_codes)) {
            $sel_code = 'ISFP';
        }
        $arch_data = getProfileByCode($pdo, $sel_code);
        $is_saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        ?>

        <div class="elysian-card p-6 flex flex-col h-full min-h-0 text-gray-800 overflow-y-auto custom-scrollbar">
          <div class="flex justify-between items-center pb-4 border-b border-gray-200 mb-6">
            <div>
              <span class="text-[9px] font-bold text-[#D97706] uppercase tracking-widest block font-mono">16-Archetype Outcome Manager</span>
              <h2 class="text-xl font-bold text-gray-900 font-display mt-0.5">Custom Score Card Authoring</h2>
              <p class="text-xs text-gray-500 mt-1">Author customized titles, taglines, strengths, growth areas, and SMART goals for every MBTI outcome combination.</p>
            </div>
            <div>
              <span class="px-3 py-1.5 bg-[#FFC5AA]/40 text-gray-900 border border-[#FFC5AA] rounded-full font-mono text-xs font-bold">
                Active Code: <?php echo htmlspecialchars($sel_code); ?>
              </span>
            </div>
          </div>

          <?php if ($is_saved): ?>
            <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl flex items-center justify-between gap-3 text-emerald-400 text-xs font-medium animate-fade-in">
              <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Archetype <strong><?php echo htmlspecialchars($sel_code); ?></strong> outcome card successfully saved to database!</span>
              </div>
            </div>
          <?php endif; ?>

          <form method="POST" action="/save_profile.php" class="space-y-6 max-w-4xl">
            <input type="hidden" name="save_archetype" value="1">
            
            <!-- Code Selector Dropdown -->
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-2xl">
              <label class="elysian-label mb-1.5 block">Select Archetype Code to Customize</label>
              <select name="code" class="elysian-input text-sm font-mono font-bold cursor-pointer text-[#D97706]" onchange="window.location.href='/mentor/index.php?tab=archetypes&selected_code='+this.value">
                <?php foreach ($all_codes as $ac): ?>
                  <option value="<?php echo $ac; ?>" <?php echo $sel_code === $ac ? 'selected' : ''; ?>>
                    <?php echo $ac; ?> &mdash; <?php echo htmlspecialchars($master_profiles[$ac]['title'] ?? $ac); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Title & Tagline -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="elysian-label mb-1.5 block">Archetype Title</label>
                <input type="text" name="title" class="elysian-input text-xs" required placeholder="e.g. The Adventurer" value="<?php echo htmlspecialchars($arch_data['title'] ?? ''); ?>">
              </div>
              <div>
                <label class="elysian-label mb-1.5 block">Defining Tagline</label>
                <input type="text" name="tagline" class="elysian-input text-xs" placeholder="e.g. A creative, empathetic problem solver" value="<?php echo htmlspecialchars($arch_data['tagline'] ?? ''); ?>">
              </div>
            </div>

            <!-- Strengths & Growth Areas Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="elysian-label mb-1.5 block text-emerald-400">Core Strengths (One strength per line)</label>
                <textarea name="strengths" rows="5" class="elysian-input text-xs leading-relaxed" placeholder="Strategic Mindset&#10;High Autonomy&#10;Analytical Precision&#10;Systemic Thinking"><?php echo htmlspecialchars(implode("\n", $arch_data['strengths'] ?? [])); ?></textarea>
              </div>
              <div>
                <label class="elysian-label mb-1.5 block text-cyan-400">Growth Areas & Blind Spots (One growth area per line)</label>
                <textarea name="growth_areas" rows="5" class="elysian-input text-xs leading-relaxed" placeholder="Overly Critical&#10;Perfectionist&#10;Impatient with Inefficiency&#10;Dismissive of Emotion"><?php echo htmlspecialchars(implode("\n", $arch_data['growth_areas'] ?? [])); ?></textarea>
              </div>
            </div>

            <!-- 4 SMART Goals -->
            <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl space-y-4">
              <label class="elysian-label mb-1 block text-[#D97706]">4 Prescribed SMART Goals</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <?php 
                $curr_goals = $arch_data['smart_goals'] ?? [];
                for ($g_i = 1; $g_i <= 4; $g_i++): 
                ?>
                  <div>
                    <label class="elysian-label text-[9px] mb-1 block">Goal #<?php echo $g_i; ?></label>
                    <input type="text" name="goal_<?php echo $g_i; ?>" class="elysian-input text-xs" placeholder="Enter actionable SMART goal <?php echo $g_i; ?>..." value="<?php echo htmlspecialchars($curr_goals[$g_i - 1] ?? ''); ?>">
                  </div>
                <?php endfor; ?>
              </div>
            </div>

            <div class="flex justify-end pt-2">
              <button type="submit" class="elysian-btn elysian-btn-gold px-8 py-3 text-xs font-bold shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Archetype Outcome Card
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

    </main>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
