  </main>
  <!-- /#app-main -->

  <!-- ══════════════════════════════════════════════════════════
       GLOBAL FOOTER
  ══════════════════════════════════════════════════════════ -->
  <footer class="no-print flex-shrink-0" id="app-footer"
          style="border-top:1px solid var(--color-border);
                 background-color:var(--color-surface);
                 padding: 0 1.5rem;
                 height: 44px;
                 display:flex;
                 align-items:center;
                 flex-shrink:0;
                 transition:background-color 0.25s ease, border-color 0.25s ease;">
    <div class="max-w-screen-xl mx-auto w-full flex items-center justify-between gap-4">
      <span style="font-size:0.7rem; color:var(--color-text-muted);">
        &copy; <?php echo date('Y'); ?> Elysian Success. All rights reserved.
      </span>
      <div class="flex items-center gap-4" style="font-size:0.7rem; color:var(--color-text-muted);">
        <a href="/privacy.php" style="color:inherit;" onmouseover="this.style.color='var(--color-gold)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Privacy Policy</a>
        <a href="/terms.php" style="color:inherit;" onmouseover="this.style.color='var(--color-gold)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Terms of Service</a>
        <a href="/support.php" style="color:inherit;" onmouseover="this.style.color='var(--color-cyan)'"
           onmouseout="this.style.color='var(--color-text-muted)'">Support</a>
        <a href="/mentor/index.php" style="color:inherit; opacity:0.55;" onmouseover="this.style.opacity='1'"
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
          // A small delay so client-side validation can fire first
          setTimeout(function () {
            var btn = form.querySelector('[type="submit"]');
            if (!btn || btn.disabled) return;

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
          }, 0);
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
