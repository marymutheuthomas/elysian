# Elysian Success

A PHP/MySQL coaching-and-assessment platform. Students register, select a program, pay,
work through a multi-step personality assessment ("tunnel"), and receive a strategic
profile (MBTI-style archetype) with SMART goals. Mentors manage content and students
through an admin CMS.

## Tech stack

- **Backend**: PHP 8.5 (CLI available via `php`), plain procedural/functional style, no framework.
- **Database**: MySQL, accessed via PDO (`config/db.php`). Local dev connects to `127.0.0.1:3307`
  (not `localhost` — must be the IP, per the comment in `config/db.php`), database `elysian_success`.
- **Frontend**: Server-rendered PHP with Tailwind CSS utility classes. No build step for the PHP app.
- **Legacy**: `_react_backup/` is an abandoned React + Vite + TypeScript + Tailwind attempt at the
  same product. Not active — do not extend it. Its `node_modules`/`dist` are gitignored.

## Entry points

| File | Role |
|---|---|
| `index.php` | Landing/login/register entry. Redirects logged-in students by `status`. |
| `register.php` | Student registration. |
| `programs.php` | Program selection. |
| `payment.php` | Payment submission/verification. |
| `tunnel.php` | The assessment itself — renders pillars/blocks/components, scores answers via the evaluation engine, shows result-reveal cards. Largest student-facing file. |
| `completed.php` | Post-completion page. |
| `mentor/index.php` | Admin/mentor CMS — manage programs, pillars, blocks, components, trait schemes, archetypes, and student messaging. Password-gated (`$_SESSION['mentor_logged_in']`), very large single file. |
| `logout.php`, `save_profile.php` | Small utility endpoints. |

## Content hierarchy

`programs → pillars → blocks → components`. A `component` can be a question type
(`free_text`, `branching`, `scoring`, `goal`, `result_reveal`, `short_answer`, `dropdown`) or
`content_only`, which is driven by a JSON `content_schema` array of arbitrary elements
(headings, `trait_matrix` questions, `result_reveal` cards) built in the mentor CMS.

## Decoupled evaluation engine

`includes/evaluation_engine.php` is scheme-agnostic: trait schemes, traits, and archetypes are
DB-backed (`trait_schemes`, `traits`, `archetypes` tables) rather than hardcoded. `evaluateAssessment()`
is the single scoring entry point — takes a `scheme_id` and raw trait scores, returns the matched
archetype. Only one scheme is currently seeded (`mbti_16_types`); the engine supports adding more.

`includes/profiles.php` holds the legacy hardcoded `$master_profiles` (16 MBTI archetypes) used as
seed data and as a fallback when the DB lookup misses.

## Database

- Connection config: `config/db.php` (hardcoded local dev credentials — not read from `.env`; `.env`
  exists but is currently unused by the PHP code).
- `schema.sql` is the single source of truth for a fresh database — run it to bootstrap. It already
  includes the 4-tier hierarchy (`blocks` table, `pillars.sort_order`/`congratulatory_note`,
  `components.block_id`/`sort_order`/`config`/`content_schema`) and the trait-scheme tables.
- `migrate_blocks.sql` is the historical non-destructive migration that introduced the `blocks` table
  on an existing database — keep it for reference but treat `schema.sql` as authoritative for new setups.
- Several files still run defensive `ALTER TABLE ... ADD COLUMN` / `CREATE TABLE IF NOT EXISTS` guards
  at runtime (`mentor/index.php` on login, `evaluation_engine.php`'s `ensureTraitTablesExist()`) as a
  safety net for existing databases. When adding new columns/tables, update `schema.sql` first, then
  keep those runtime guards in sync if the change needs to reach already-deployed databases.

## Working here

- No automated test suite. `scratch/*.php` are ad-hoc PHP scripts (run via `php scratch/foo.php`) used
  to sanity-check DB logic (component CRUD, the evaluation engine, login state) against the real local
  DB — not unit tests, and not cleaned up automatically.
- `mentor/index.php` and `tunnel.php` are very large single files (200KB+ / 90KB+). Prefer targeted
  `Edit`-style changes with exact context over full-file rewrites.
