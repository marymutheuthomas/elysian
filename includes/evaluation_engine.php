<?php
// includes/evaluation_engine.php — Scheme-Agnostic Assessment Evaluation Engine

require_once __DIR__ . '/profiles.php';

/**
 * Ensure database tables for trait evaluation exist and are seeded.
 */
function ensureTraitTablesExist(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) return;

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

        try { $pdo->exec("ALTER TABLE `programs` ADD COLUMN `scheme_id` VARCHAR(50) NULL"); } catch (Throwable $t) {}
        try { $pdo->exec("ALTER TABLE `students` ADD COLUMN `raw_scores` TEXT NULL"); } catch (Throwable $t) {}
        try { $pdo->exec("ALTER TABLE `students` ADD COLUMN `archetype_id` VARCHAR(50) NULL"); } catch (Throwable $t) {}

        // Seed MBTI default scheme & traits
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

        $cnt_arch = (int)$pdo->query("SELECT COUNT(*) FROM `archetypes` WHERE `scheme_id` = 'mbti_16_types'")->fetchColumn();
        if ($cnt_arch === 0) {
            global $master_profiles;
            if (!empty($master_profiles)) {
                $st_arch = $pdo->prepare("INSERT INTO `archetypes` (`archetype_id`, `scheme_id`, `trigger_conditions_json`, `title`, `description`) VALUES (?, 'mbti_16_types', ?, ?, ?)");
                foreach ($master_profiles as $m_code => $m_data) {
                    $cond = json_encode(['code' => $m_code]);
                    $st_arch->execute(['mbti_' . strtolower($m_code), $cond, $m_data['title'], "Strategic profile for " . $m_data['title']]);
                }
            }
        }

        $ensured = true;
    } catch (Throwable $t) {}
}

/**
 * Fetch all registered trait evaluation schemes.
 */
function getAllTraitSchemes(PDO $pdo): array {
    ensureTraitTablesExist($pdo);
    try {
        $stmt = $pdo->query("SELECT * FROM `trait_schemes` ORDER BY `name` ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * Fetch traits for a given scheme ID.
 */
function getSchemeTraits(PDO $pdo, string $scheme_id): array {
    ensureTraitTablesExist($pdo);
    if (empty($scheme_id)) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM `traits` WHERE `scheme_id` = ? ORDER BY `code` ASC");
        $stmt->execute([$scheme_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * Centralized assessment evaluation engine.
 */
function evaluateAssessment(PDO $pdo, string $scheme_id, array $raw_scores): ?array {
    ensureTraitTablesExist($pdo);

    if (empty($scheme_id)) {
        $scheme_id = 'mbti_16_types';
    }

    // 1. Fetch scheme details
    $stmt_sch = $pdo->prepare("SELECT * FROM `trait_schemes` WHERE `scheme_id` = ?");
    $stmt_sch->execute([$scheme_id]);
    $scheme = $stmt_sch->fetch(PDO::FETCH_ASSOC);

    $scheme_type = $scheme['scheme_type'] ?? 'dominant_trait';

    // 2. Aggregate raw scores by trait code / trait_id
    $scores_by_trait = [];
    foreach ($raw_scores as $entry) {
        if (is_array($entry)) {
            $t = strtoupper(trim($entry['trait_id'] ?? $entry['code'] ?? ''));
            $pts = (int)($entry['points'] ?? 1);
            if (!empty($t)) {
                $scores_by_trait[$t] = ($scores_by_trait[$t] ?? 0) + $pts;
            }
        } elseif (is_string($entry)) {
            $t = strtoupper(trim($entry));
            if (!empty($t)) {
                $scores_by_trait[$t] = ($scores_by_trait[$t] ?? 0) + 1;
            }
        }
    }

    // 3. Evaluate according to scheme_type
    $calculated_code = '';
    if ($scheme_type === 'dominant_trait' || $scheme_id === 'mbti_16_types') {
        // Evaluate 4 MBTI bipolar dimensions
        $e = $scores_by_trait['E'] ?? 0;
        $i = $scores_by_trait['I'] ?? 0;
        $dim1 = ($e >= $i && ($e > 0 || $i > 0)) ? 'E' : ($i > $e ? 'I' : 'E');

        $s = $scores_by_trait['S'] ?? 0;
        $n = $scores_by_trait['N'] ?? 0;
        $dim2 = ($s >= $n && ($s > 0 || $n > 0)) ? 'S' : ($n > $s ? 'N' : 'S');

        $t = $scores_by_trait['T'] ?? 0;
        $f = $scores_by_trait['F'] ?? 0;
        $dim3 = ($t >= $f && ($t > 0 || $f > 0)) ? 'T' : ($f > $t ? 'F' : 'T');

        $j = $scores_by_trait['J'] ?? 0;
        $p = $scores_by_trait['P'] ?? 0;
        $dim4 = ($j >= $p && ($j > 0 || $p > 0)) ? 'J' : ($p > $j ? 'P' : 'J');

        $calculated_code = $dim1 . $dim2 . $dim3 . $dim4;
    } else {
        // Fallback for non-MBTI schemes: highest scoring trait code
        arsort($scores_by_trait);
        $calculated_code = key($scores_by_trait) ?: '';
    }

    // 4. Resolve Archetype matching calculated_code
    $archetype = null;
    $archetype_id = '';

    if (!empty($calculated_code)) {
        try {
            // Check archetypes table for matching trigger_conditions_json
            $stmt_arch = $pdo->prepare("SELECT * FROM `archetypes` WHERE `scheme_id` = ?");
            $stmt_arch->execute([$scheme_id]);
            $all_archs = $stmt_arch->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_archs as $arc) {
                $conds = json_decode($arc['trigger_conditions_json'] ?? '{}', true) ?: [];
                if (isset($conds['code']) && strtoupper($conds['code']) === $calculated_code) {
                    $archetype = $arc;
                    $archetype_id = $arc['archetype_id'];
                    break;
                }
            }

            if (!$archetype && !empty($all_archs)) {
                // Try matching by archetype_id convention e.g. mbti_intj
                $target_id = strtolower($scheme_id . '_' . $calculated_code);
                foreach ($all_archs as $arc) {
                    if ($arc['archetype_id'] === $target_id || stristr($arc['archetype_id'], $calculated_code)) {
                        $archetype = $arc;
                        $archetype_id = $arc['archetype_id'];
                        break;
                    }
                }
            }
        } catch (Throwable $t) {}

        // Fallback to legacy getProfileByCode if archetype details null
        $profile_card = getProfileByCode($pdo, $calculated_code);
        if ($profile_card && !$archetype) {
            $archetype_id = 'mbti_' . strtolower($calculated_code);
            $archetype = [
                'archetype_id' => $archetype_id,
                'scheme_id' => $scheme_id,
                'title' => $profile_card['title'],
                'description' => $profile_card['tagline'] ?? '',
                'strengths' => $profile_card['strengths'] ?? [],
                'growth_areas' => $profile_card['growth_areas'] ?? [],
                'smart_goals' => $profile_card['smart_goals'] ?? []
            ];
        } elseif ($profile_card && $archetype) {
            $archetype['strengths'] = $profile_card['strengths'] ?? [];
            $archetype['growth_areas'] = $profile_card['growth_areas'] ?? [];
            $archetype['smart_goals'] = $profile_card['smart_goals'] ?? [];
        }
    }

    return [
        'scheme_id' => $scheme_id,
        'archetype_id' => $archetype_id,
        'code' => $calculated_code,
        'raw_scores' => $scores_by_trait,
        'archetype' => $archetype
    ];
}

/**
 * Render a full, rich 16-Archetype Profile Reveal card UI.
 */
function renderResultRevealCard(array $profile, string $code = ''): string {
    $title = htmlspecialchars($profile['title'] ?? 'Strategic Profile Outcome');
    $tagline = htmlspecialchars($profile['tagline'] ?? $profile['description'] ?? '');
    $code_display = htmlspecialchars($code ?: ($profile['code'] ?? ''));
    $strengths = $profile['strengths'] ?? [];
    $growth = $profile['growth_areas'] ?? $profile['weaknesses'] ?? [];
    $goals = $profile['smart_goals'] ?? $profile['suggested_goals'] ?? [];

    ob_start();
    ?>
    <div class="space-y-6 w-full">
      <!-- Main Profile Banner -->
      <div class="p-6 sm:p-8 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl text-white relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex items-center justify-between flex-wrap gap-3 mb-3">
          <div class="flex items-center gap-2">
            <span class="text-xl">🔮</span>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-400 font-mono">Strategic Profile Report</span>
          </div>
          <?php if (!empty($code_display)): ?>
            <span class="text-xs font-mono font-bold px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
              <?php echo $code_display; ?> Profile
            </span>
          <?php endif; ?>
        </div>
        <h3 class="text-2xl sm:text-3xl font-extrabold font-display text-white mt-1"><?php echo $title; ?></h3>
        <?php if (!empty($tagline)): ?>
          <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed max-w-3xl"><?php echo $tagline; ?></p>
        <?php endif; ?>
      </div>

      <!-- Core Strengths & Growth Areas Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Core Strengths Card -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border-l-4 border-emerald-500 border-y border-r border-subtle shadow-sm">
          <h4 class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-3 flex items-center gap-2 font-display">
            <span>⚡</span> Core Strengths & Competencies
          </h4>
          <?php if (!empty($strengths)): ?>
            <ul class="text-xs text-main space-y-2.5 font-medium">
              <?php foreach ($strengths as $st): ?>
                <li class="flex items-start gap-2.5">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 flex-shrink-0"></span>
                  <span class="leading-relaxed"><?php echo htmlspecialchars($st); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-xs text-muted italic">Analytical Precision, Strategic Mindset, High Autonomy.</p>
          <?php endif; ?>
        </div>

        <!-- Growth Areas Card -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border-l-4 border-indigo-500 border-y border-r border-subtle shadow-sm">
          <h4 class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-3 flex items-center gap-2 font-display">
            <span>🎯</span> Growth Areas & Development Vectors
          </h4>
          <?php if (!empty($growth)): ?>
            <ul class="text-xs text-main space-y-2.5 font-medium">
              <?php foreach ($growth as $wk): ?>
                <li class="flex items-start gap-2.5">
                  <span class="w-2 h-2 rounded-full bg-indigo-500 mt-1.5 flex-shrink-0"></span>
                  <span class="leading-relaxed"><?php echo htmlspecialchars($wk); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-xs text-muted italic">Overly Critical, Impatient with Inefficiency.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Prescribed Action Plan — 4 SMART Goals -->
      <?php if (!empty($goals)): ?>
        <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-subtle space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-extrabold text-muted uppercase tracking-widest font-display flex items-center gap-2">
              <span>📌</span> Prescribed Action Plan &mdash; SMART Goals
            </h4>
            <span class="text-[9px] font-mono text-amber-600 font-bold bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Worksheet Aligned</span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($goals as $g_idx => $gl): ?>
              <div class="p-3.5 bg-white dark:bg-slate-900 border border-subtle rounded-xl text-xs text-main flex items-start gap-3 shadow-xs">
                <span class="w-6 h-6 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 text-xs font-bold flex items-center justify-center flex-shrink-0 font-mono"><?php echo $g_idx + 1; ?></span>
                <span class="leading-relaxed font-medium"><?php echo htmlspecialchars($gl); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
