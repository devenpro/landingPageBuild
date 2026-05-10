<?php
// core/lib/pages_data_driven_placeholder.php — emitted when render_page()
// hits a data-driven page (is_file_based=0). Phase 8 replaces this with
// the real renderer that walks $page['sections_json'] and includes each
// referenced section partial. For now, just tell the developer where to
// look.

declare(strict_types=1);

http_response_code(503);
?><!DOCTYPE html>
<meta charset="utf-8">
<title>Data-driven page (Phase 8)</title>
<body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 4rem auto; padding: 0 1rem; color: #1f2937;">
<h1>Data-driven page (not yet implemented)</h1>
<p>This page row has <code>is_file_based=0</code> and a <code>sections_json</code> blob. Rendering data-driven pages lands in Phase 8 alongside the admin page CRUD UI.</p>
<p>Until then, mark this page <code>status='draft'</code> in the <code>pages</code> table or convert it to file-based by setting <code>is_file_based=1</code> and pointing <code>file_path</code> at a PHP file in <code>site/pages/</code>.</p>
</body>
