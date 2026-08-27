  </main>
  <!-- /#app-main -->

  <!-- ══════════════════════════════════════════════════════════
       GLOBAL FOOTER
  ══════════════════════════════════════════════════════════ -->
  <footer class="no-print flex-shrink-0" id="app-footer"
          style="border-top:1px solid var(--color-border);
                 background-color:var(--color-surface);
                 padding: 0.75rem 1.5rem;
                 min-height: 44px;
                 display:flex;
                 align-items:center;
                 flex-shrink:0;
                 transition:background-color 0.25s ease, border-color 0.25s ease;">
    <div class="max-w-screen-xl mx-auto w-full flex flex-col sm:flex-row items-center sm:justify-between gap-2 sm:gap-4">
      <span style="font-size:0.7rem; color:var(--color-text-muted);" class="text-center sm:text-left">
        &copy; <?php echo date('Y'); ?> Elysian Success. All rights reserved.
      </span>
      <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5" style="font-size:0.7rem; color:var(--color-text-muted);">
        <a href="/privacy.php" style="color:inherit; padding:0.25rem 0;" onmouseover="this.style.color='var(--color-gold)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Privacy Policy</a>
        <a href="/terms.php" style="color:inherit; padding:0.25rem 0;" onmouseover="this.style.color='var(--color-gold)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Terms of Service</a>
        <a href="/support.php" style="color:inherit; padding:0.25rem 0;" onmouseover="this.style.color='var(--color-cyan)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Support</a>
        <a href="/mentor/index.php" style="color:inherit; opacity:0.55; padding:0.25rem 0;" onmouseover="this.style.opacity='1'"
           onmouseout="this.style.opacity='0.55'">Staff Access</a>
      </div>
    </div>
  </footer>

  <!-- ══════════════════════════════════════════════════════════
       GLOBAL JAVASCRIPT — Theme toggle + Button loading states
  ══════════════════════════════════════════════════════════ -->
  <script>
  (function () {
    // ─────────────────────────────────────────────────────────
    // 1. BUTTON LOADING STATE — prevents double submissions
    //    Hooks into ALL <form> elements on the page.
    //    When a form submits, its [type=submit] button is:
    //      • Disabled
    //      • Text replaced with a spinner + "Please wait..."
    //      • Re-enabled after 8s (failsafe in case redirect fails)
    // ─────────────────────────────────────────────────────────
    var SPINNER_SVG = '<svg class="animate-spin" style="display:inline-block;width:14px;height:14px;margin-right:6px;vertical-align:middle;" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>';

    function attachFormLoadingStates() {
      document.querySelectorAll('form').forEach(function (form) {
        // Skip forms that have opted out via data attribute
        if (form.dataset.noLoading) return;
        // Skip AJAX forms (they manage their own state)
        if (form.dataset.ajax) return;

        form.addEventListener('submit', function (e) {
          // Disable synchronously, in the same tick the submit event fires —
          // a deferred (setTimeout) disable leaves a brief window where a
          // fast double-click on Save fires two submissions before the first
          // one has visibly locked the button, which can create two rows
          // (e.g. IDs generated from time() can differ across the two
          // requests if they land a second apart).
          var btn = form.querySelector('[type="submit"]');
          if (!btn || btn.disabled) {
            if (btn && btn.disabled) e.preventDefault();
            return;
          }

          var originalHTML = btn.innerHTML;
          var originalWidth = btn.offsetWidth;

          // Lock button width to prevent layout shift
          btn.style.minWidth = originalWidth + 'px';
          btn.disabled = true;
          btn.innerHTML = SPINNER_SVG + 'Please wait…';

          // Failsafe: re-enable after 8 seconds
          setTimeout(function () {
            btn.disabled  = false;
            btn.innerHTML = originalHTML;
            btn.style.minWidth = '';
          }, 8000);
        });
      });
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', attachFormLoadingStates);
    } else {
      attachFormLoadingStates();
    }

    // ─────────────────────────────────────────────────────────
    // 3. AUTO-SCROLL CHAT — utility exposed globally
    // ─────────────────────────────────────────────────────────
    window.scrollChatToBottom = function (containerId) {
      var el = document.getElementById(containerId || 'chat-messages-container');
      if (el) el.scrollTop = el.scrollHeight;
    };

    // ─────────────────────────────────────────────────────────
    // 4. HTML ESCAPE UTILITY — available globally for inline JS
    // ─────────────────────────────────────────────────────────
    window.escapeHtml = function (text) {
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };

  })();
  </script>

</body>
</html>
