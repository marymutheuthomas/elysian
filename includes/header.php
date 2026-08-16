<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine route context
$is_mentor_route = strpos($_SERVER['REQUEST_URI'], '/mentor') !== false;
?>
<!DOCTYPE html>
<?php
// Mentor portal uses the bright pastel palette (light mode) — no forced dark class
// Student pages respect localStorage / system preference via JS
$html_class = '';
?>
<html lang="en" <?php echo $html_class; ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Elysian Success — Premium Professional Transformation</title>
  <meta name="description" content="Elysian Success — A strategic diagnostic platform for high-performance professionals.">

  <!-- Anti-FOUC: Force light mode theme -->
  <script>
    (function() {
      localStorage.removeItem('theme');
      document.documentElement.classList.remove('dark');
    })();
  </script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- ══════════════════════════════════════════════════════════════
       TAILWIND CONFIGURATION — Dark mode + Brand color extensions
  ══════════════════════════════════════════════════════════════ -->
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans:    ['Inter',  'system-ui', '-apple-system', 'sans-serif'],
            display: ['Outfit', 'system-ui', '-apple-system', 'sans-serif'],
          },
          colors: {
            'brand-blue':         '#0F172A',
            'brand-gold':         '#C99700',
            'brand-gold-dark':    '#A87D00',
            'brand-cyan':         '#0284C7',
            'brand-cyan-dark':    '#0369A1',
            'brand-emerald':      '#059669',
            'brand-emerald-dark': '#047857',
          }
        }
      }
    }
  </script>

  <!-- ══════════════════════════════════════════════════════════════
       DESIGN SYSTEM — CSS TOKENS & COMPONENT STYLES
  ══════════════════════════════════════════════════════════════ -->
  <style>
    /* ── Color Tokens ─────────────────────────────────────────── */
    :root {
      /* ── Custom 4-Color Pastel Palette ─────────────────────── */
      --coral-red:   #FF9D9D;    /* Accent Red / Warnings / Required Tags */
      --peach-soft:  #FFC5AA;    /* Soft Peach / Active States / Secondary Accents */
      --sage-yellow: #EEF8CD;    /* Soft Sage Yellow / Highlight Cards / Callouts */
      --mint-green:  #BBF1D2;    /* Mint Green / Success Badges / Primary CTAs */

      /* ── Neutral Surfaces & Typography ─────────────────────── */
      --bg-main:      #FAF9F6;   /* Warm Crisp Background Canvas */
      --surface-card: #FFFFFF;   /* Pure White Bento Tiles */
      --surface-subtle: #F7FDF5; /* Sage-tinted nested surface */
      --border-subtle: #E2E8F0;  /* Subtle Card Border */
      --text-main:    #1E293B;   /* High-Contrast Slate Body Text */
      --text-muted:   #64748B;   /* Subtitle & Label Text */

      /* ── Status ─────────────────────────────────────────────── */
      --status-success:  var(--mint-green);
      --status-danger:   var(--coral-red);
      --status-required: #D63384;   /* Pink-Red for Req badges */

      /* ── Component Aliases ──────────────────────────────────── */
      --color-bg:          var(--bg-main);
      --color-surface:     var(--surface-card);
      --color-border:      var(--border-subtle);
      --color-text:        var(--text-main);
      --color-text-muted:  var(--text-muted);

      /* Keep brand/legacy aliases pointing to pastel equivalents */
      --brand-primary:      #2E7D52;        /* Dark Mint — readable CTA text bg */
      --brand-hover:        #1B5E38;        /* Deeper Mint Hover */
      --brand-primary-glow: rgba(187, 241, 210, 0.40);

      --navy-dark:          var(--text-main);
      --blue-vibrant:       #2563EB;        /* kept for inline references */
      --blue-primary:       #2563EB;
      --blue-hover:         #1D4ED8;
      --blue-light:         var(--sage-yellow);
      --gold-vibrant:       #D97706;
      --gold-accent:        #D97706;
      --gold-light:         var(--peach-soft);
      --gold-border:        var(--coral-red);

      --brand-blue-deep:    var(--text-main);
      --brand-blue-primary: #2563EB;
      --brand-blue-soft:    var(--sage-yellow);
      --brand-gold-primary: #D97706;
      --brand-gold-soft:    var(--peach-soft);
      --brand-gold-border:  var(--coral-red);

      --color-blue:         var(--text-main);
      --color-gold:         #D97706;
      --color-gold-dark:    #B45309;
      --color-gold-glow:    rgba(255, 197, 170, 0.35); /* peach glow */
      --color-cyan:         #2563EB;
      --color-cyan-dark:    #1D4ED8;
      --color-cyan-glow:    rgba(187, 241, 210, 0.35); /* mint glow */
      --color-emerald:      #059669;
      --color-emerald-dark: #047857;

      /* ── Shadows (warm tinted) ──────────────────────────────── */
      --shadow-sm:   0 1px 3px rgba(30,41,59,0.06), 0 1px 2px rgba(30,41,59,0.04);
      --shadow-md:   0 4px 16px rgba(30,41,59,0.08);
      --shadow-lg:   0 8px 32px rgba(30,41,59,0.10);
      --shadow-gold: 0 0 0 3px var(--color-gold-glow);
      --shadow-cyan: 0 0 0 3px var(--color-cyan-glow);

      /* ── Radii ──────────────────────────────────────────────── */
      --radius-sm:  0.5rem;
      --radius-md:  0.75rem;
      --radius-lg:  1.25rem;
      --radius-xl:  1.75rem;

      /* ── Header height ──────────────────────────────────────── */
      --header-h: 60px;
    }




    /* ── App Shell (Flexible Scrollable Shell) ───────────────── */
    *,
    *::before,
    *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html {
      height: 100%;
      width: 100%;
      overflow-x: hidden;
    }

    body {
      min-height: 100vh;
      width: 100%;
      overflow-x: hidden;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      background-color: var(--color-bg);
      color: var(--color-text);
      font-family: 'Inter', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* ── Global Header ───────────────────────────────────────── */
    #app-header {
      height: var(--header-h);
      flex-shrink: 0;
      background-color: var(--color-surface);
      border-bottom: 1px solid var(--color-border);
      box-shadow: var(--shadow-sm);
      transition: background-color 0.25s ease, border-color 0.25s ease;
      z-index: 50;
    }

    html.dark #app-header {
      background-color: #0F172A;
      border-color: #1E293B;
    }

    /* ── Main Content Shell ───────────────────────────────────── */
    #app-main {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      min-height: 0;
      -webkit-overflow-scrolling: touch;
    }

    /* ── Scrollable region utility ───────────────────────────── */
    .scroll-region {
      overflow-y: auto;
      overflow-x: hidden;
      -webkit-overflow-scrolling: touch;
    }

    /* ── Custom Scrollbar ─────────────────────────────────────── */
    .scroll-region::-webkit-scrollbar,
    .custom-scrollbar::-webkit-scrollbar      { width: 5px; height: 5px; }
    .scroll-region::-webkit-scrollbar-track,
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .scroll-region::-webkit-scrollbar-thumb,
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background-color: var(--color-border);
      border-radius: 99px;
    }
    .scroll-region::-webkit-scrollbar-thumb:hover,
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background-color: var(--color-text-muted);
    }

    /* ── Typography ──────────────────────────────────────────── */
    h1, h2, h3, h4, h5, h6 {
      font-family: 'Outfit', sans-serif;
      color: var(--color-text);
    }

    /* ── Card Component ──────────────────────────────────────── */
    .elysian-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-md);
      transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    /* ── Glassmorphism (fixed) ───────────────────────────────── */
    .glass {
      background: rgba(255, 255, 255, 0.82);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(226, 232, 240, 0.6);
    }
    html.dark .glass {
      background: rgba(15, 23, 42, 0.82);
      border-color: rgba(30, 41, 59, 0.7);
    }

    /* ── Input Component ─────────────────────────────────────── */
    .elysian-input {
      width: 100%;
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
      padding: 0.7rem 1rem;
      font-size: 0.875rem;
      background-color: var(--color-surface);
      color: var(--color-text);
      transition: border-color 0.18s, box-shadow 0.18s, background-color 0.25s;
      outline: none;
    }
    .elysian-input:focus {
      border-color: var(--color-cyan);
      box-shadow: var(--shadow-cyan);
    }
    html.dark .elysian-input {
      background-color: #0A0F1C;
      border-color: #334155;
      color: #F1F5F9;
    }
    html.dark .elysian-input:focus {
      border-color: var(--color-cyan);
    }

    /* ── Label ───────────────────────────────────────────────── */
    .elysian-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--color-text-muted);
      letter-spacing: 0.02em;
    }

    /* ── Button Base ─────────────────────────────────────────── */
    .elysian-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.65rem 1.4rem;
      border-radius: var(--radius-md);
      font-weight: 600;
      font-size: 0.875rem;
      cursor: pointer;
      border: none;
      transition: background-color 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease, transform 0.12s ease;
      text-decoration: none;
      white-space: nowrap;
      letter-spacing: 0.01em;
    }
    .elysian-btn:active { transform: scale(0.97); }
    .elysian-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    /* Cyan — Primary CTA */
    .elysian-btn-cyan {
      background-color: var(--color-cyan);
      color: #FFFFFF;
      box-shadow: 0 1px 3px rgba(2,132,199,0.25);
    }
    .elysian-btn-cyan:hover:not(:disabled) {
      background-color: var(--color-cyan-dark);
      box-shadow: 0 4px 14px rgba(2,132,199,0.35);
    }

    /* Gold — Brand Accent */
    .elysian-btn-gold {
      background-color: var(--color-gold);
      color: #FFFFFF;
      box-shadow: 0 1px 3px rgba(201,151,0,0.25);
    }
    .elysian-btn-gold:hover:not(:disabled) {
      background-color: var(--color-gold-dark);
      box-shadow: 0 4px 14px rgba(201,151,0,0.35);
    }

    /* Emerald — Success / Milestone */
    .elysian-btn-emerald {
      background-color: var(--color-emerald);
      color: #FFFFFF;
    }
    .elysian-btn-emerald:hover:not(:disabled) {
      background-color: var(--color-emerald-dark);
    }

    /* Ghost — Secondary / Outlined */
    .elysian-btn-ghost {
      background-color: transparent;
      border: 1px solid var(--color-border);
      color: var(--color-text);
    }
    .elysian-btn-ghost:hover:not(:disabled) {
      background-color: var(--color-bg);
      border-color: var(--color-text-muted);
    }
    html.dark .elysian-btn-ghost {
      border-color: #334155;
      color: #E2E8F0;
    }
    html.dark .elysian-btn-ghost:hover:not(:disabled) {
      background-color: #1E293B;
    }

    /* Danger — Destructive */
    .elysian-btn-danger {
      background-color: #DC2626;
      color: #FFFFFF;
    }
    .elysian-btn-danger:hover:not(:disabled) { background-color: #B91C1C; }

    /* ── Status Pills ────────────────────────────────────────── */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.2rem 0.65rem;
      border-radius: 9999px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: capitalize;
    }
    .status-program_selection { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }
    .status-payment_pending   { background:#FFFBEB; color:#B45309; border:1px solid #FDE68A; }
    .status-active            { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; }
    .status-completed         { background:#F0FDF4; color:#166534; border:1px solid #BBF7D0; }
    .status-pending           { background:#FFFBEB; color:#B45309; border:1px solid #FDE68A; }
    .status-verified          { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; }
    .status-rejected          { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }

    /* Dark overrides for status pills */
    html.dark .status-program_selection { background:rgba(29,78,216,.15);  color:#93C5FD; border-color:rgba(29,78,216,.3);  }
    html.dark .status-payment_pending   { background:rgba(180,83,9,.15);   color:#FCD34D; border-color:rgba(180,83,9,.3);   }
    html.dark .status-active            { background:rgba(5,150,105,.15);  color:#6EE7B7; border-color:rgba(5,150,105,.3);  }
    html.dark .status-completed         { background:rgba(22,101,52,.15);  color:#86EFAC; border-color:rgba(22,101,52,.3);  }
    html.dark .status-pending           { background:rgba(180,83,9,.15);   color:#FCD34D; border-color:rgba(180,83,9,.3);   }
    html.dark .status-verified          { background:rgba(5,150,105,.15);  color:#6EE7B7; border-color:rgba(5,150,105,.3);  }
    html.dark .status-rejected          { background:rgba(153,27,27,.15);  color:#FCA5A5; border-color:rgba(153,27,27,.3);  }

    /* ── Chat Bubbles ─────────────────────────────────────────── */
    .chat-bubble-student {
      align-self: flex-end;
      background-color: var(--color-cyan);
      color: #FFFFFF;
      border-radius: 1rem 1rem 0.2rem 1rem;
      padding: 0.55rem 0.9rem;
      font-size: 0.8125rem;
      max-width: 78%;
      line-height: 1.5;
    }
    .chat-bubble-mentor {
      align-self: flex-start;
      background-color: var(--color-surface);
      color: var(--color-text);
      border: 1px solid var(--color-border);
      border-radius: 1rem 1rem 1rem 0.2rem;
      padding: 0.55rem 0.9rem;
      font-size: 0.8125rem;
      max-width: 78%;
      line-height: 1.5;
    }
    html.dark .chat-bubble-mentor {
      background-color: #1E293B;
      border-color: #334155;
      color: #F1F5F9;
    }

    /* ── Animations ──────────────────────────────────────────── */
    .animate-fade-in  { animation: fadeIn  0.35s ease-out forwards; }
    .animate-slide-up { animation: slideUp 0.35s ease-out forwards; }
    .animate-scale-in { animation: scaleIn 0.25s ease-out forwards; }

    @keyframes fadeIn  { from{opacity:0}               to{opacity:1} }
    @keyframes slideUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    @keyframes scaleIn { from{opacity:0;transform:scale(.95)}       to{opacity:1;transform:scale(1)} }

    /* ── Grid Layouts ─────────────────────────────────────────── */
    .bento-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
    }
    @media (max-width: 1024px) { .bento-grid-3 { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 640px)  { .bento-grid-3 { grid-template-columns: 1fr; } }

    /* ── Theme Toggle Button ─────────────────────────────────── */
    #theme-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid var(--color-border);
      background: transparent;
      cursor: pointer;
      color: var(--color-text-muted);
      transition: background-color 0.18s, border-color 0.18s, color 0.18s;
      flex-shrink: 0;
    }
    #theme-toggle:hover {
      background-color: var(--color-bg);
      color: var(--color-gold);
      border-color: var(--color-gold);
    }
    html.dark #theme-toggle { border-color: #334155; }
    html.dark #theme-toggle:hover { background-color: #1E293B; }

    /* ══════════════════════════════════════════════════════════════
       HARDCODED TAILWIND CLASS OVERRIDES
       These rules make existing page files theme-aware without
       requiring each file to be rewritten. They override the
       hardcoded Tailwind color utilities that were written before
       the CSS variable design system existed.
    ══════════════════════════════════════════════════════════════ */

    /* ── Text colours: dark mode must lighten these ─────────── */
    html.dark .text-slate-900,
    html.dark .text-gray-900  { color: #F1F5F9 !important; }

    html.dark .text-slate-800,
    html.dark .text-gray-800  { color: #E2E8F0 !important; }

    html.dark .text-slate-700,
    html.dark .text-gray-700  { color: #CBD5E1 !important; }

    html.dark .text-slate-600,
    html.dark .text-gray-600  { color: #94A3B8 !important; }

    html.dark .text-slate-500,
    html.dark .text-gray-500  { color: #64748B !important; }

    html.dark .text-slate-400 { color: #94A3B8 !important; }

    /* ── Background colours: light mode must not show dark bg ── */
    /* bg-slate-900 used by tunnel chat header — looks intentional in both modes
       (deep navy), so we keep it in light mode too as a decorative contrast element. */
    html:not(.dark) .bg-slate-900  { background-color: #0F172A !important; }
    html:not(.dark) .bg-slate-800  { background-color: #1E293B !important; }
    html:not(.dark) .bg-slate-950  { background-color: #020617 !important; }

    /* bg-white stays white in both modes on student pages */
    html.dark .bg-white            { background-color: var(--color-surface) !important; }

    /* Slate backgrounds in light mode: make airy */
    html:not(.dark) .bg-slate-50   { background-color: #F8FAFC !important; }
    html:not(.dark) .bg-slate-100  { background-color: #F1F5F9 !important; }

    /* ── Border colours ──────────────────────────────────────── */
    html.dark .border-slate-100,
    html.dark .border-gray-100     { border-color: #1E293B !important; }
    html.dark .border-slate-200,
    html.dark .border-gray-200     { border-color: #334155 !important; }

    /* ── Mentor portal preserves its own dark bg correctly ───── */
    /* (html.dark) so the mentor's bg-slate-900 cards stay dark — no override needed */

    /* ── Print Utility ───────────────────────────────────────── */
    @media print { .no-print { display: none !important; } }

    /* ── Decorative Blobs (pointer-events safe) ──────────────── */
    .blob { pointer-events: none; user-select: none; }

    /* ══════════════════════════════════════════════════════════════
       PHASE 1 — ELY-CARD UTILITY SYSTEM
       Canonical surface component for the Elysian Content Engine.
       Use .ely-card as the outer wrapper, then compose with
       .ely-card-header and .ely-card-body for structured layouts.
    ══════════════════════════════════════════════════════════════ */

    /* Base card surface */
    .ely-card {
      background: var(--surface-card);
      border: 1px solid var(--border-subtle);
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.04);
      overflow: hidden;
      transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    }
    html.dark .ely-card {
      background: var(--surface-card);  /* resolves to dark surface via :root cascade */
      border-color: var(--border-subtle);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -2px rgba(0, 0, 0, 0.2);
    }

    /* Hover lift — opt-in with .ely-card-hover */
    .ely-card-hover:hover {
      box-shadow: 0 10px 20px -4px rgba(0, 0, 0, 0.08), 0 4px 8px -4px rgba(0, 0, 0, 0.06);
      transform: translateY(-1px);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    html.dark .ely-card-hover:hover {
      box-shadow: 0 10px 20px -4px rgba(0, 0, 0, 0.45), 0 4px 8px -4px rgba(0, 0, 0, 0.3);
    }

    /* Card header strip */
    .ely-card-header {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border-subtle);
      display: flex;
      align-items: center;
      gap: 0.625rem;
      font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
      font-weight: 600;
      font-size: 0.875rem;
      color: var(--text-main);
      letter-spacing: -0.01em;
      background: transparent;
      transition: border-color 0.25s ease, color 0.25s ease;
    }
    html.dark .ely-card-header {
      border-bottom-color: var(--border-subtle);
    }

    /* Card body content area */
    .ely-card-body {
      padding: 1.25rem;
    }

    /* Compact variant */
    .ely-card-body-sm {
      padding: 0.875rem 1rem;
    }

    /* Full-bleed variant (removes padding for tables, media, etc.) */
    .ely-card-body-flush {
      padding: 0;
    }

    /* ── Input reset: ensure Phase-1 font stack applies to fields ── */
    input, textarea, select, button {
      font-family: inherit;
    }
    .ely-input {
      width: 100%;
      border-radius: 8px;
      border: 1px solid var(--border-subtle);
      padding: 0.625rem 0.875rem;
      font-size: 0.875rem;
      font-family: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;
      background: var(--surface-card);
      color: var(--text-main);
      outline: none;
      transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .ely-input::placeholder { color: var(--text-muted); opacity: 0.75; }
    .ely-input:focus {
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 3px var(--brand-primary-glow);
    }
    html.dark .ely-input {
      background: rgba(255,255,255,0.04);
      border-color: var(--border-subtle);
      color: var(--text-main);
    }
    html.dark .ely-input:focus {
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 3px var(--brand-primary-glow);
    }

    /* ── Brand-primary button variant ───────────────────────────── */
    .elysian-btn-brand {
      background-color: var(--brand-primary);
      color: #FFFFFF;
      box-shadow: 0 1px 3px rgba(79,70,229,0.25);
    }
    .elysian-btn-brand:hover:not(:disabled) {
      background-color: var(--brand-hover);
      box-shadow: 0 4px 14px rgba(79,70,229,0.35);
    }
  </style>
</head>

<!--
  APP SHELL BODY
  The body is the outer flex column container.
  header.php opens <body> + <header> + <main>.
  footer.php closes </main> + <footer> + </body> + </html>.
-->
<body class="font-sans">

  <!-- ══════════════════════════════════════════════════════════
       GLOBAL NAVIGATION HEADER
  ══════════════════════════════════════════════════════════ -->
  <header id="app-header" class="no-print block">
    <div class="h-full max-w-screen-xl mx-auto px-4 sm:px-6 flex items-center justify-between gap-4">

      <!-- Logo -->
      <a href="/index.php" class="flex items-center gap-2 flex-shrink-0 group" aria-label="Elysian Success Home">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
             style="background:var(--color-blue);">
          <span class="text-white font-black text-sm font-display">E</span>
        </div>
        <span class="font-black text-lg tracking-tight font-display hidden sm:block"
              style="color:var(--color-blue);">
          ELYSIAN<span style="color:var(--color-gold);">SUCCESS</span>
        </span>
      </a>

      <!-- Right nav cluster -->
      <nav class="flex items-center gap-2 sm:gap-3" aria-label="Header navigation">

        <?php if (isset($_SESSION['student_id'])): ?>
          <!-- Logged-in student -->
          <span class="hidden sm:flex items-center gap-1.5 text-xs font-medium"
                style="color:var(--color-text-muted);">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
            </svg>
            <span style="color:var(--color-gold); font-weight:700; font-family:'Courier New',monospace; letter-spacing:0.05em;">
              <?php echo htmlspecialchars($_SESSION['student_id']); ?>
            </span>
          </span>
          <a href="/logout.php" class="elysian-btn elysian-btn-ghost" style="padding:0.4rem 0.9rem; font-size:0.75rem;">
            Logout
          </a>

        <?php elseif (isset($_SESSION['mentor_logged_in']) && $_SESSION['mentor_logged_in'] === true): ?>
          <!-- Logged-in mentor -->
          <span class="hidden sm:flex items-center gap-1.5 text-xs font-semibold"
                style="color:var(--color-text-muted);">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
            <span style="color:var(--color-gold);">Elysian Mentor</span>
          </span>
          <a href="/logout.php" class="elysian-btn elysian-btn-ghost" style="padding:0.4rem 0.9rem; font-size:0.75rem;">
            Logout
          </a>

        <?php elseif ($is_mentor_route): ?>
          <!-- Guest on mentor route -->
          <a href="/index.php" class="text-xs font-semibold hover:underline"
             style="color:var(--color-gold);">Student Area</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php
  // ── Student progress stepper ──────────────────────────────────
  // Shown only on mentee pages (programs/payment/tunnel/completed all
  // fetch $student before including this file — PHP include shares
  // caller scope, so no extra query or param-passing is needed here).
  // Purely informational: steps are not links, students can't skip ahead.
  if (!$is_mentor_route && isset($student) && is_array($student) && isset($student['status'])):
      $ely_steps = [
          'program_selection' => 'Program',
          'payment_pending'   => 'Payment',
          'active'            => 'Assessment',
          'completed'         => 'Completed',
      ];
      $ely_step_keys = array_keys($ely_steps);
      $ely_current_idx = array_search($student['status'], $ely_step_keys, true);
      if ($ely_current_idx === false) $ely_current_idx = 0;
  ?>
    <div class="no-print" style="border-bottom:1px solid var(--color-border); background-color:var(--color-surface);">
      <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-2.5 flex items-center justify-center gap-1.5 sm:gap-2.5 overflow-x-auto">
        <?php foreach ($ely_step_keys as $ely_i => $ely_key):
            $ely_label  = $ely_steps[$ely_key];
            $ely_done   = $ely_i < $ely_current_idx;
            $ely_active = $ely_i === $ely_current_idx;
        ?>
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                  style="<?php
                      if ($ely_active) echo 'background:var(--color-cyan); color:#fff;';
                      elseif ($ely_done) echo 'background:var(--color-emerald); color:#fff;';
                      else echo 'background:var(--color-bg); border:1px solid var(--color-border); color:var(--color-text-muted);';
                  ?>">
              <?php echo $ely_done ? '✓' : ($ely_i + 1); ?>
            </span>
            <span class="text-xs font-semibold whitespace-nowrap"
                  style="color:<?php echo $ely_active ? 'var(--color-cyan)' : ($ely_done ? 'var(--color-text)' : 'var(--color-text-muted)'); ?>;">
              <?php echo htmlspecialchars($ely_label); ?>
            </span>
          </div>
          <?php if ($ely_i < count($ely_step_keys) - 1): ?>
            <span class="w-4 sm:w-8 h-px flex-shrink-0" style="background-color:var(--color-border);"></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════
       MAIN CONTENT SHELL — pages inject their content here
  ══════════════════════════════════════════════════════════ -->
  <main id="app-main">
