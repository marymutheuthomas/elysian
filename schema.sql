-- MySQL Database Schema for Elysian Success (PHP Migration)
-- Single source of truth: consolidates the original schema, migrate_blocks.sql
-- (4-tier content hierarchy), and the auto-migration DDL that used to live
-- only in mentor/index.php and includes/evaluation_engine.php.
USE elysian_success;

CREATE TABLE IF NOT EXISTS `programs` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `outcomes` TEXT NULL, -- JSON string representing outcomes list
  `fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `duration` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `scheme_id` VARCHAR(50) NULL -- FK to trait_schemes.scheme_id (evaluation engine used for this program)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pillars` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `program_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `congratulatory_note` TEXT NULL,
  CONSTRAINT `fk_pillars_programs` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blocks` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `pillar_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Untitled Block',
  `sort_order` INT NOT NULL DEFAULT 0,
  CONSTRAINT `fk_blocks_pillars` FOREIGN KEY (`pillar_id`) REFERENCES `pillars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `components` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `pillar_id` VARCHAR(50) NOT NULL,
  `block_id` VARCHAR(50) NULL,
  `type` VARCHAR(50) NOT NULL, -- 'free_text', 'branching', 'scoring', 'goal', 'result_reveal', 'short_answer', 'dropdown', 'content_only'
  `question` TEXT NOT NULL,
  `placeholder` VARCHAR(255) NULL,
  `required` TINYINT(1) NOT NULL DEFAULT 0,
  `show_if` TEXT NULL, -- JSON string representing ShowIfRule
  `options` TEXT NULL, -- JSON string representing array of option items
  `config` TEXT NULL, -- JSON string representing component-level config
  `content_schema` LONGTEXT NULL, -- JSON array of elements for 'content_only' components
  `sort_order` INT NOT NULL DEFAULT 0,
  CONSTRAINT `fk_components_pillars` FOREIGN KEY (`pillar_id`) REFERENCES `pillars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_components_blocks` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `permanent_id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'program_selection', -- 'program_selection', 'payment_pending', 'active', 'completed'
  `selected_program_id` VARCHAR(50) NULL,
  `active_block_index` INT NOT NULL DEFAULT 0,
  `answers` TEXT NULL, -- JSON string representing answers
  `profile_code` VARCHAR(255) NOT NULL DEFAULT '',
  `ttid` VARCHAR(100) NULL,
  `raw_scores` TEXT NULL, -- JSON string representing raw trait scores for the evaluation engine
  `archetype_id` VARCHAR(50) NULL, -- FK to archetypes.archetype_id (resolved outcome)
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_programs` FOREIGN KEY (`selected_program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `student_permanent_id` VARCHAR(50) NOT NULL,
  `program_id` VARCHAR(50) NOT NULL,
  `ttid` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'verified', 'rejected'
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `verified_at` TIMESTAMP NULL,
  CONSTRAINT `fk_payments_students` FOREIGN KEY (`student_permanent_id`) REFERENCES `students` (`permanent_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_programs` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `thread_id` VARCHAR(50) NOT NULL, -- Maps to student_permanent_id
  `sender` VARCHAR(50) NOT NULL, -- 'student', 'mentor'
  `sender_label` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of a component's question/type/options taken right before a mentor
-- deletes it, so students' previously-submitted answers stay meaningful even
-- after the source content is gone (student answers are never deleted, but the
-- question text they answered lives only here once the live row is removed).
CREATE TABLE IF NOT EXISTS `component_archive` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `pillar_title` VARCHAR(255) NULL,
  `type` VARCHAR(50) NULL,
  `question` TEXT NULL,
  `options` TEXT NULL,
  `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trait Schemes & Assessment Registry (scheme-agnostic evaluation engine)
CREATE TABLE IF NOT EXISTS `trait_schemes` (
  `scheme_id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `scheme_type` VARCHAR(50) NOT NULL DEFAULT 'dominant_trait', -- 'dominant_trait', 'competency_radar', 'bipolar_dimensions'
  `description` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traits` (
  `trait_id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `scheme_id` VARCHAR(50) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  CONSTRAINT `fk_traits_schemes` FOREIGN KEY (`scheme_id`) REFERENCES `trait_schemes` (`scheme_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `archetypes` (
  `archetype_id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `scheme_id` VARCHAR(50) NOT NULL,
  `trigger_conditions_json` TEXT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `badge_url` VARCHAR(255) NULL,
  CONSTRAINT `fk_archetypes_schemes` FOREIGN KEY (`scheme_id`) REFERENCES `trait_schemes` (`scheme_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16-Archetype Profiles Outcome Table (Legacy Profile Cache)
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(4) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `tagline` TEXT NULL,
  `strengths` TEXT NULL, -- JSON array of core strengths
  `growth_areas` TEXT NULL, -- JSON array of growth & weakness areas
  `smart_goals` TEXT NULL, -- JSON array of 4 SMART goals
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default MBTI Trait Scheme & Traits
INSERT INTO `trait_schemes` (`scheme_id`, `name`, `scheme_type`, `description`) VALUES
('mbti_16_types', '16-Personality Archetype Scheme (MBTI)', 'dominant_trait', 'Evaluates Extraversion/Introversion, Sensing/Intuition, Thinking/Feeling, and Judging/Perceiving.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `traits` (`trait_id`, `scheme_id`, `code`, `label`, `description`) VALUES
('mbti_e', 'mbti_16_types', 'E', 'Extraversion', 'Focuses energy outward toward people and action.'),
('mbti_i', 'mbti_16_types', 'I', 'Introversion', 'Focuses energy inward toward ideas and reflection.'),
('mbti_s', 'mbti_16_types', 'S', 'Sensing', 'Processes concrete facts and practical details.'),
('mbti_n', 'mbti_16_types', 'N', 'Intuition', 'Processes abstract possibilities and strategic patterns.'),
('mbti_t', 'mbti_16_types', 'T', 'Thinking', 'Makes decisions using objective logic.'),
('mbti_f', 'mbti_16_types', 'F', 'Feeling', 'Makes decisions using personal values and empathy.'),
('mbti_j', 'mbti_16_types', 'J', 'Judging', 'Prefers structure, planning, and closure.'),
('mbti_p', 'mbti_16_types', 'P', 'Perceiving', 'Prefers flexibility, spontaneity, and open options.')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Seed Data for Default Accelerator Program (Mock Program elysian_accelerator_1)
INSERT INTO `programs` (`id`, `code`, `title`, `description`, `outcomes`, `fee`, `duration`, `is_active`, `scheme_id`) VALUES
('elysian_accelerator_1', 'ESA-ACC', 'Elysian Success Accelerator', 'A premium roadmap for scaling operations, improving leadership depth, and unlocking exponential growth.', '["Define your irreversible strategic vision", "Build high-performance leadership identity", "Install scalable client acquisition systems", "Achieve operational excellence", "Master financial intelligence"]', 4500.00, '12 Weeks', 1, 'mbti_16_types');

INSERT INTO `pillars` (`id`, `program_id`, `title`, `description`, `sort_order`, `congratulatory_note`) VALUES
('pillar_1', 'elysian_accelerator_1', 'Pillar 1: Vision & Strategic Positioning', 'Establish your core direction, identify critical constraints, and set actionable growth milestones.', 1, '🌟 Congratulations! You have successfully completed the "Pillar 1: Vision & Strategic Positioning" pillar. Your insights and responses have been recorded. Keep this momentum going as you advance to your next chapter!');

INSERT INTO `blocks` (`id`, `pillar_id`, `title`, `sort_order`) VALUES
('block_container_1', 'pillar_1', 'Strategic Vision & Assessment Block', 1);

INSERT INTO `components` (`id`, `pillar_id`, `block_id`, `type`, `question`, `placeholder`, `required`, `show_if`, `options`, `sort_order`) VALUES
('block_1', 'pillar_1', 'block_container_1', 'free_text', 'What is your primary professional vision for the next 12 months?', 'Detail your long-term goals, target revenue, or impact area...', 1, NULL, NULL, 1),
('block_2', 'pillar_1', 'block_container_1', 'branching', 'Identify the primary scaling constraint in your business today:', NULL, 1, NULL, '[{"value": "client_acquisition", "label": "Client Acquisition & Branding"}, {"value": "operational_efficiency", "label": "Operational Systems & Delegation"}, {"value": "leadership_capacity", "label": "Leadership Alignment & Team Culture"}]', 2),
('block_3', 'pillar_1', 'block_container_1', 'scoring', 'Step 1: Energy Focus & Direction', NULL, 1, '{"blockId": "block_2", "operator": "equals", "value": "operational_efficiency"}', '[{"value": "introversion", "label": "Prefer deep independent focus and quiet contemplation", "hidden_code": "I"}, {"value": "extraversion", "label": "Prefer high-energy group collaboration and rapid dialogue", "hidden_code": "E"}]', 3),
('block_4', 'pillar_1', 'block_container_1', 'free_text', 'Describe the main channel you currently use to source high-value clients:', 'LinkedIn outreach, paid advertising, referrals, partners...', 1, '{"blockId": "block_2", "operator": "equals", "value": "client_acquisition"}', NULL, 4),
('block_5', 'pillar_1', 'block_container_1', 'goal', 'State the key target metric (e.g. $ revenue, hire headcount) you want to achieve by Q4:', 'Example: Reach $100k MRR or hire 3 senior managers...', 1, NULL, NULL, 5),
('block_6', 'pillar_1', 'block_container_1', 'result_reveal', 'Your Elysian Success Profile', 'These are suggested SMART goals based on your personality type. Rewrite them or add new ones that fit your life better.', 0, NULL, NULL, 6);

-- Seed 16 Archetype Profiles (remaining 15 are auto-seeded at runtime from includes/profiles.php's
-- $master_profiles the first time the `profiles` table is empty — see ensureProfilesTableExists())
INSERT INTO `profiles` (`code`, `title`, `tagline`, `strengths`, `growth_areas`, `smart_goals`) VALUES
('INTJ', 'The Architect', 'A strategic, analytical, and highly autonomous mastermind.', '["Strategic Mindset", "High Autonomy", "Analytical Precision", "Systemic Thinking"]', '["Overly Critical", "Perfectionist", "Impatient with Inefficiency", "Dismissive of Emotion"]', '["Design a 90-day scalable business operational architecture.", "Establish automated KPI tracking dashboards for core initiatives.", "Schedule weekly strategic review blocks without operational interruptions.", "Implement structured delegation guidelines for junior team members."]')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `tagline` = VALUES(`tagline`), `strengths` = VALUES(`strengths`), `growth_areas` = VALUES(`growth_areas`), `smart_goals` = VALUES(`smart_goals`);
