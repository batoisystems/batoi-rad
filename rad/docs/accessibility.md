# Accessibility Release Gate

Batoi RAD targets WCAG 2.2 AA for the login screen and critical RAD Admin
navigation. The release browser test verifies one main landmark and page
heading, form labels, accessible names, skip navigation, keyboard focus, and a
visible focus indicator.

Before a stable release, exercise this short manual check against the exact
archive:

1. Sign in using only the keyboard.
2. Use the first focusable “Skip to main content” link.
3. Open and close navigation and account menus with Enter, Space, and Escape.
4. Confirm focus does not disappear behind a dialog and returns to its opener.
5. Confirm login errors and RAD Admin alerts are announced by VoiceOver or
   another screen reader.
6. Check login and dashboard text/control contrast at normal and 200% zoom.

Automated checks prevent common regressions but do not replace assistive-
technology review for newly introduced workflows.
