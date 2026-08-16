<?php
// includes/profiles.php

$master_profiles = [
    'INTJ' => [
        'title' => 'INTJ — The Architect',
        'strengths' => ['Strategic Mindset', 'High Autonomy', 'Analytical Precision', 'Systemic Thinking'],
        'weaknesses' => ['Overly Critical', 'Perfectionist', 'Impatient with Inefficiency', 'Dismissive of Emotion'],
        'suggested_goals' => [
            'Design a 90-day scalable business operational architecture.',
            'Establish automated KPI tracking dashboards for core initiatives.',
            'Schedule weekly strategic review blocks without operational interruptions.',
            'Implement structured delegation guidelines for junior team members.'
        ]
    ],
    'INTP' => [
        'title' => 'INTP — The Thinker',
        'strengths' => ['Abstract Problem Solving', 'Intellectual Curiosity', 'Objective Analysis', 'Originality'],
        'weaknesses' => ['Second-guessing Execution', 'Prone to Analysis Paralysis', 'Insensitive Communication', 'Dislike of Routine'],
        'suggested_goals' => [
            'Convert theoretical concepts into 3 actionable prototype milestones.',
            'Limit research time per task to maximum 2 hours before executing.',
            'Establish a daily accountability checklist for core routines.',
            'Conduct bi-weekly peer feedback sessions on key deliverables.'
        ]
    ],
    'ENTJ' => [
        'title' => 'ENTJ — The Commander',
        'strengths' => ['Decisive Leadership', 'Strategic Efficiency', 'Charismatic Drive', 'Goal Alignment'],
        'weaknesses' => ['Dominating Presence', 'Intolerant of Slow Execution', 'Impatience with Nuance', 'High Stress Load'],
        'suggested_goals' => [
            'Conduct quarterly strategic alignment audits across all projects.',
            'Implement an active listening framework during team operational syncs.',
            'Empower secondary leaders through weekly delegated ownership goals.',
            'Establish structured downtime to mitigate executive burnout risk.'
        ]
    ],
    'ENTP' => [
        'title' => 'ENTP — The Visionary',
        'strengths' => ['Innovative Ideation', 'Adaptable Strategy', 'Quick Learning', 'Enthusiastic Engagement'],
        'weaknesses' => ['Difficulty with Follow-through', 'Argumentative Tendency', 'Easily Bored', 'Over-promising'],
        'suggested_goals' => [
            'Select top 2 high-impact initiatives and commit to completion before starting new ones.',
            'Partner with an operational execution lead for daily task tracking.',
            'Build a structured idea repository to park non-immediate concepts.',
            'Implement weekly project finish line reviews.'
        ]
    ],
    'INFJ' => [
        'title' => 'INFJ — The Advocate',
        'strengths' => ['Values-Driven Vision', 'Deep Empathy', 'Creative Strategy', 'Unwavering Integrity'],
        'weaknesses' => ['Prone to Burnout', 'Hypersensitive to Friction', 'Perfectionist Expectations', 'Secretive Planning'],
        'suggested_goals' => [
            'Align strategic roadmap directly with core organizational values.',
            'Establish strict personal and professional boundary audits weekly.',
            'Translate high-level vision into clear 30-day tactical goals.',
            'Practice transparent mid-project progress sharing with stakeholders.'
        ]
    ],
    'INFP' => [
        'title' => 'INFP — The Mediator',
        'strengths' => ['Authentic Communication', 'Harmonious Problem-solving', 'Creative Passion', 'Flexible Mindset'],
        'weaknesses' => ['Overly Idealistic', 'Self-critical', 'Avoids Confrontation', 'Impractical Timelines'],
        'suggested_goals' => [
            'Break ambitious creative visions into quantifiable weekly SMART targets.',
            'Establish conflict-resolution protocols for project hurdles.',
            'Set strict deadlines and use time-blocking to prevent task drift.',
            'Review personal milestones weekly to celebrate objective progress.'
        ]
    ],
    'ENFJ' => [
        'title' => 'ENFJ — The Mentor',
        'strengths' => ['Inspirational Leadership', 'Empathic Coaching', 'Consensus Building', 'Organized Vision'],
        'weaknesses' => ['Over-involvement', 'Neglects Personal Goals', 'Vulnerable to Disapproval', 'Idealizing Others'],
        'suggested_goals' => [
            'Establish clear personal KPI boundaries alongside team mentorship.',
            'Delegate operational ownership to foster independent team growth.',
            'Schedule dedicated personal strategic focus blocks twice weekly.',
            'Conduct structured quarterly performance reviews based on data.'
        ]
    ],
    'ENFP' => [
        'title' => 'ENFP — The Campaigner',
        'strengths' => ['Energetic Catalyst', 'Imaginative Solutions', 'Strong Interpersonal Connection', 'Future-Focused'],
        'weaknesses' => ['Unorganized Priorities', 'Overthinking Logistics', 'Inconsistent Execution', 'Need for Validation'],
        'suggested_goals' => [
            'Focus on 1 primary revenue or growth metric per monthly sprint.',
            'Implement automated reminder workflows for daily operational routines.',
            'Delegate administrative processes to maintain strategic focus.',
            'Establish an end-of-month campaign retrospective protocol.'
        ]
    ],
    'ISTJ' => [
        'title' => 'ISTJ — The Inspector',
        'strengths' => ['Systematic Reliability', 'Methodical Execution', 'Strong Duty & Loyalty', 'Practical Logic'],
        'weaknesses' => ['Resistance to Change', 'Rigid Adherence to Rules', 'Risk Aversion', 'Judgmental Tone'],
        'suggested_goals' => [
            'Document 3 standard operating procedures for team standardization.',
            'Reserve 10% of project planning time for flexible experimentation.',
            'Establish a structured weekly operational review routine.',
            'Incorporate bi-weekly innovation brainstorming into existing processes.'
        ]
    ],
    'ISFJ' => [
        'title' => 'ISFJ — The Protector',
        'strengths' => ['Meticulous Support', 'Dependable Execution', 'Practical Empathy', 'Patience & Persistence'],
        'weaknesses' => ['Reluctant to Change', 'Overcommitted', 'Suppresses Opinions', 'Overly Altruistic'],
        'suggested_goals' => [
            'Implement a task prioritization matrix to filter non-essential requests.',
            'Define explicit scope limits before accepting new project tasks.',
            'Automate repetitive record-keeping to free up capacity.',
            'Practice proactive communication of personal bandwidth limits.'
        ]
    ],
    'ESTJ' => [
        'title' => 'ESTJ — The Executive',
        'strengths' => ['Direct Management', 'High Organizational Ability', 'Clear Standards', 'Results Orientation'],
        'weaknesses' => ['Inflexible Frameworks', 'Uncomfortable with Ambiguity', 'Blunt Communication', 'Over-emphasis on Status'],
        'suggested_goals' => [
            'Streamline core operational workflow for a 15% efficiency gain.',
            'Incorporate team feedback mechanisms into project governance.',
            'Host weekly clear alignment standups to maintain team velocity.',
            'Practice adaptive project planning for unexpected market shifts.'
        ]
    ],
    'ESFJ' => [
        'title' => 'ESFJ — The Provider',
        'strengths' => ['Community Builder', 'Practical Team Organization', 'Loyal & Reliable', 'Strong Social Duty'],
        'weaknesses' => ['Needing External Approval', 'Inflexible to Innovation', 'Conflict Avoidance', 'Vulnerable to Criticism'],
        'suggested_goals' => [
            'Build structured team collaboration frameworks with clear targets.',
            'Track performance metrics objectively alongside team morale.',
            'Set boundaries on unscheduled requests to safeguard focused work.',
            'Conduct quarterly stakeholder feedback surveys.'
        ]
    ],
    'ISTP' => [
        'title' => 'ISTP — The Craftsperson',
        'strengths' => ['Tactical Troubleshooting', 'Crisis Management', 'Rational Pragmatism', 'Hands-on Execution'],
        'weaknesses' => ['Private / Detached', 'Insensitive to Dynamics', 'Risk-seeking', 'Easily Bored with Routine'],
        'suggested_goals' => [
            'Create a 30-60-90 day tactical execution blueprint.',
            'Schedule regular project updates to maintain team alignment.',
            'Combine technical troubleshooting with documented solutions.',
            'Establish long-term milestone check-ins to prevent premature pivoting.'
        ]
    ],
    'ISFP' => [
        'title' => 'ISFP — The Adventurer',
        'strengths' => ['Artistic Intuition', 'Flexible Execution', 'Passionate Dedication', 'Empathetic Presence'],
        'weaknesses' => ['Unpredictable Focus', 'Conflict Avoidance', 'Fluctuating Commitment', 'Dislike of Data Constraints'],
        'suggested_goals' => [
            'Inject unique brand aesthetics into core user experience workflows.',
            'Focus on incremental daily process execution targets.',
            'Establish structured goal-tracking checkpoints every Friday.',
            'Translate creative insights into concrete project deliverables.'
        ]
    ],
    'ESTP' => [
        'title' => 'ESTP — The Entrepreneur',
        'strengths' => ['Bold Action-taking', 'Resourceful Problem-solving', 'Direct Influence', 'High Energy'],
        'weaknesses' => ['Impulsive Decision-making', 'Risk Unawareness', 'Impatient with Theory', 'Inconsistent Planning'],
        'suggested_goals' => [
            'Lead 1 high-impact tactical growth initiative this month.',
            'Draft a comprehensive 3-month risk-mitigation project plan.',
            'Review analytics weekly before pivoting strategic directions.',
            'Establish structured pre-mortem reviews before launching new campaigns.'
        ]
    ],
    'ESFP' => [
        'title' => 'ESFP — The Entertainer',
        'strengths' => ['Dynamic Engagement', 'Practical Adaptability', 'Enthusiastic Facilitation', 'People Orientation'],
        'weaknesses' => ['Short-term Horizon', 'Dislike of Complex Theory', 'Poor Time Management', 'Friction Avoidance'],
        'suggested_goals' => [
            'Design interactive team onboarding and engagement experiences.',
            'Set up daily time-boxed blocks for focused analytical work.',
            'Partner with a structured project manager for timeline adherence.',
            'Establish bi-weekly goal tracking against key performance metrics.'
        ]
    ]
];

// ─── DB HELPER FUNCTIONS FOR ARCHETYPE MANAGER ────────────────────────────────

function ensureProfilesTableExists($pdo) {
    global $master_profiles;
    if (!$pdo) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `profiles` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `code` VARCHAR(4) NOT NULL UNIQUE,
          `title` VARCHAR(255) NOT NULL,
          `tagline` TEXT NULL,
          `strengths` TEXT NULL,
          `growth_areas` TEXT NULL,
          `smart_goals` TEXT NULL,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Migration check: ensure tagline and growth_areas columns exist
        try {
            $pdo->exec("ALTER TABLE `profiles` ADD COLUMN `tagline` TEXT NULL AFTER `title`");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE `profiles` CHANGE COLUMN `weaknesses` `growth_areas` TEXT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE `profiles` CHANGE COLUMN `suggested_goals` `smart_goals` TEXT NULL");
        } catch (Exception $e) {}

        // Seed initial data if table is empty
        $stmt_cnt = $pdo->query("SELECT COUNT(*) FROM `profiles`");
        $cnt = (int)$stmt_cnt->fetchColumn();
        if ($cnt === 0 && !empty($master_profiles)) {
            $stmt_ins = $pdo->prepare("INSERT INTO `profiles` (`code`, `title`, `tagline`, `strengths`, `growth_areas`, `smart_goals`) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($master_profiles as $code => $prof) {
                $tagline = isset($prof['tagline']) ? $prof['tagline'] : "A high-impact " . strtolower(explode('—', $prof['title'])[1] ?? 'leader') . " strategic profile.";
                $stmt_ins->execute([
                    $code,
                    $prof['title'],
                    $tagline,
                    json_encode($prof['strengths']),
                    json_encode($prof['weaknesses'] ?? $prof['growth_areas'] ?? []),
                    json_encode($prof['suggested_goals'] ?? $prof['smart_goals'] ?? [])
                ]);
            }
        }
    } catch (Exception $e) {
        // Fallback silently if DB error
    }
}

function getProfileByCode($pdo, $code) {
    global $master_profiles;
    $code = strtoupper(trim($code));
    if (empty($code)) return null;

    if ($pdo) {
        ensureProfilesTableExists($pdo);
        try {
            $stmt = $pdo->prepare("SELECT * FROM `profiles` WHERE `code` = ?");
            $stmt->execute([$code]);
            $row = $stmt->fetch();

            if ($row) {
                $strengths = json_decode($row['strengths'], true);
                if (!is_array($strengths)) {
                    $strengths = array_filter(array_map('trim', explode("\n", (string)$row['strengths'])));
                }
                $growth = json_decode($row['growth_areas'] ?? $row['weaknesses'] ?? '[]', true);
                if (!is_array($growth)) {
                    $growth = array_filter(array_map('trim', explode("\n", (string)($row['growth_areas'] ?? ''))));
                }
                $goals = json_decode($row['smart_goals'] ?? $row['suggested_goals'] ?? '[]', true);
                if (!is_array($goals)) {
                    $goals = array_filter(array_map('trim', explode("\n", (string)($row['smart_goals'] ?? ''))));
                }

                return [
                    'id' => $row['id'] ?? null,
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'tagline' => $row['tagline'] ?: ("Strategic profile for " . $row['title']),
                    'strengths' => array_values($strengths),
                    'growth_areas' => array_values($growth),
                    'smart_goals' => array_values($goals)
                ];
            }
        } catch (Exception $e) {
            // Fallback to master_profiles
        }
    }

    if (isset($master_profiles[$code])) {
        $p = $master_profiles[$code];
        return [
            'id' => null,
            'code' => $code,
            'title' => $p['title'],
            'tagline' => "Strategic profile for " . $p['title'],
            'strengths' => $p['strengths'],
            'growth_areas' => $p['weaknesses'] ?? [],
            'smart_goals' => $p['suggested_goals'] ?? []
        ];
    }
    return null;
}

function saveProfileArchetype($pdo, $code, $title, $tagline, $strengths_text, $growth_text, $goals_input, $scheme_id = 'mbti_16_types') {
    $code = strtoupper(trim($code));
    $title = trim($title);
    $tagline = trim($tagline);

    $str_arr = is_array($strengths_text) ? $strengths_text : array_filter(array_map('trim', explode("\n", $strengths_text)));
    $growth_arr = is_array($growth_text) ? $growth_text : array_filter(array_map('trim', explode("\n", $growth_text)));
    $goals_arr = is_array($goals_input) ? $goals_input : array_filter(array_map('trim', explode("\n", $goals_input)));

    $str_json = json_encode(array_values($str_arr));
    $growth_json = json_encode(array_values($growth_arr));
    $goals_json = json_encode(array_values($goals_arr));

    ensureProfilesTableExists($pdo);

    // Save to profiles (legacy cache table)
    $stmt = $pdo->prepare("INSERT INTO `profiles` (`code`, `title`, `tagline`, `strengths`, `growth_areas`, `smart_goals`) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `tagline` = VALUES(`tagline`), `strengths` = VALUES(`strengths`), `growth_areas` = VALUES(`growth_areas`), `smart_goals` = VALUES(`smart_goals`)");
    $stmt->execute([$code, $title, $tagline, $str_json, $growth_json, $goals_json]);

    // Save to archetypes registry table
    try {
        $arch_id = strtolower($scheme_id . '_' . $code);
        $conds_json = json_encode(['code' => $code]);
        $stmt_arch = $pdo->prepare("INSERT INTO `archetypes` (`archetype_id`, `scheme_id`, `trigger_conditions_json`, `title`, `description`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `description` = VALUES(`description`), `trigger_conditions_json` = VALUES(`trigger_conditions_json`)");
        $stmt_arch->execute([$arch_id, $scheme_id, $conds_json, $title, $tagline]);
    } catch (Throwable $t) {}
}
