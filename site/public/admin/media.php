<?php
// site/public/admin/media.php — media library gallery (Phase 12).
//
// Server-renders the initial list of assets (most recent first, capped
// at 200 — same convention as forms.php). Inline JS handles uploads
// and deletes via /api/upload.php and /api/media.php. Each card shows
// a thumbnail (or a kind badge for video), the filename, size, kind,
// and a copy-URL button so admins can paste paths into content.php
// without needing the picker (the picker on /admin/content.php uses
// the same listing).

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();
$rows = $pdo->query(
    'SELECT id, filename, original_name, mime_type, size_bytes, kind, uploaded_at,
            alt_text, caption, original_width, original_height,
            processed, processing_error
     FROM media_assets ORDER BY uploaded_at DESC, id DESC LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

// v2 Stage 7: pre-fetch variant counts so the cards can show a badge.
$variant_counts = [];
foreach ($pdo->query('SELECT media_id, COUNT(*) AS n FROM media_variants GROUP BY media_id') as $vc) {
    $variant_counts[(int)$vc['media_id']] = (int)$vc['n'];
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn();

function media_format_bytes(int $b): string
{
    if ($b < 1024)         return $b . ' B';
    if ($b < 1024 * 1024)  return round($b / 1024) . ' KB';
    return round($b / 1024 / 1024, 1) . ' MB';
}

admin_head('Media', 'media');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Media library</h1>
        <span class="text-sm text-ink-500">
            <?php if ($total > count($rows)): ?>
                Showing latest <?= count($rows) ?> of <?= $total ?>
            <?php else: ?>
                <?= $total ?> item<?= $total === 1 ? '' : 's' ?>
            <?php endif; ?>
        </span>
    </div>
    <p class="mt-2 text-ink-600">
        Upload images and videos used across the site. Limits:
        images ≤ <?= number_format(GUA_MAX_IMAGE_BYTES / 1048576, 1) ?> MB,
        videos ≤ <?= number_format(GUA_MAX_VIDEO_BYTES / 1048576, 0) ?> MB.
        SVG, JPG, PNG, WebP, GIF for images; MP4 and WebM for video.
    </p>

    <section class="mt-6 rounded-2xl border border-dashed border-ink-200 bg-white p-5">
        <form id="media-upload" class="flex flex-wrap items-center gap-3 text-sm">
            <input type="file" name="file" required
                   aria-label="Choose media file to upload"
                   accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm"
                   class="block text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md bg-ink-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-ink-800 disabled:bg-ink-300">
                Upload
            </button>
            <span id="media-upload-status" class="text-ink-500"></span>
        </form>
    </section>

    <?php if ($rows === []): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 bg-white/60 p-10 text-center">
            <h2 class="text-base font-medium text-ink-700">No media yet</h2>
            <p class="mt-1 text-sm text-ink-500">Upload your first asset above. Once uploaded, files live at <code>/uploads/&lt;name&gt;</code> and can be referenced from any content key.</p>
        </div>
    <?php else: ?>
        <div id="media-grid" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($rows as $r):
                $url = '/uploads/' . $r['filename'];
            ?>
                <article class="media-card group overflow-hidden rounded-2xl border border-ink-100 bg-white"
                         data-id="<?= (int)$r['id'] ?>" data-url="<?= e($url) ?>" data-kind="<?= e((string)$r['kind']) ?>">
                    <div class="aspect-square w-full overflow-hidden bg-ink-50">
                        <?php if ($r['kind'] === 'image'): ?>
                            <img src="<?= e($url) ?>" alt="<?= e((string)$r['original_name']) ?>" loading="lazy"
                                 class="h-full w-full object-cover">
                        <?php else: ?>
                            <div class="grid h-full w-full place-items-center text-ink-500">
                                <div class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto h-10 w-10"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                                    <div class="mt-1 text-xs uppercase tracking-wider"><?= e((string)$r['kind']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1 px-3 py-2 text-xs">
                        <div class="truncate font-medium text-ink-800" title="<?= e((string)$r['original_name']) ?>"><?= e((string)$r['original_name']) ?></div>
                        <div class="flex items-center justify-between text-ink-500">
                            <span><?= e(media_format_bytes((int)$r['size_bytes'])) ?> · <?= e((string)$r['mime_type']) ?></span>
                            <?php
                            $vc = $variant_counts[(int)$r['id']] ?? 0;
                            if ($r['kind'] === 'image'):
                                if ((int)$r['processed'] === 1 && $vc > 0):
                            ?>
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-emerald-800"><?= $vc ?>v</span>
                            <?php   elseif (!empty($r['processing_error'])): ?>
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-rose-800" title="<?= e((string)$r['processing_error']) ?>">err</span>
                            <?php   else: ?>
                                <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-ink-500">raw</span>
                            <?php   endif;
                            endif; ?>
                        </div>
                        <?php if (!empty($r['original_width'])): ?>
                            <div class="text-ink-500"><?= (int)$r['original_width'] ?> × <?= (int)$r['original_height'] ?></div>
                        <?php endif; ?>
                        <?php if ($r['kind'] === 'image'): ?>
                            <input type="text" class="media-alt mt-1 w-full rounded-md border border-ink-200 bg-white px-2 py-1 text-xs"
                                   placeholder="Alt text" value="<?= e((string)($r['alt_text'] ?? '')) ?>"
                                   aria-label="Alt text for <?= e((string)$r['original_name']) ?>">
                        <?php endif; ?>
                        <div class="flex items-center gap-1 pt-1">
                            <button type="button" class="media-copy rounded-md border border-ink-200 bg-white px-2 py-1 text-[11px] text-ink-700 hover:border-brand-300 hover:bg-brand-50">Copy URL</button>
                            <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="rounded-md border border-ink-200 bg-white px-2 py-1 text-[11px] text-ink-700 hover:border-brand-300 hover:bg-brand-50">Open</a>
                            <button type="button" class="media-delete ml-auto rounded-md border border-ink-200 bg-white px-2 py-1 text-[11px] text-ink-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700">Delete</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
    (function () {
        'use strict';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function toast(msg, kind) {
            let stack = document.getElementById('toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'toast-stack';
                stack.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
                document.body.appendChild(stack);
            }
            const t = document.createElement('div');
            const ok = kind === 'ok';
            t.style.cssText = [
                'padding:.6rem 1rem','border-radius:.5rem','font-size:.875rem',
                'box-shadow:0 4px 16px rgba(0,0,0,.08)',
                'background:' + (ok ? '#ecfdf5' : '#fef2f2'),
                'color:' + (ok ? '#047857' : '#b91c1c'),
                'border:1px solid ' + (ok ? '#a7f3d0' : '#fecaca'),
            ].join(';');
            t.textContent = msg;
            stack.appendChild(t);
            setTimeout(() => t.remove(), 2400);
        }

        // ---- Upload ----
        const upForm  = document.getElementById('media-upload');
        const upState = document.getElementById('media-upload-status');
        upForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(upForm);
            fd.append('csrf', csrf);
            const btn = upForm.querySelector('button[type="submit"]');
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Uploading…';
            upState.textContent = '';
            try {
                const res = await fetch('/api/upload.php', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                });
                const body = await res.json().catch(() => null);
                if (res.ok && body && body.ok) {
                    toast('Uploaded — reloading…', 'ok');
                    setTimeout(() => location.reload(), 400);
                } else {
                    upState.textContent = (body && body.error) || ('HTTP ' + res.status);
                    upState.style.color = '#b91c1c';
                    btn.disabled = false; btn.textContent = orig;
                }
            } catch (err) {
                upState.textContent = 'Network error: ' + err.message;
                upState.style.color = '#b91c1c';
                btn.disabled = false; btn.textContent = orig;
            }
        });

        // ---- Copy URL ----
        document.querySelectorAll('.media-copy').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const card = btn.closest('.media-card');
                const url = card?.getAttribute('data-url');
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                    toast('Copied ' + url, 'ok');
                } catch (_) {
                    // Fallback: select via temp textarea
                    const ta = document.createElement('textarea');
                    ta.value = url; document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); toast('Copied ' + url, 'ok'); }
                    catch (_) { toast('Copy failed', 'err'); }
                    finally { ta.remove(); }
                }
            });
        });

        // ---- Alt text inline save (debounced on blur) ----
        document.querySelectorAll('.media-alt').forEach((input) => {
            input.addEventListener('blur', async () => {
                const card = input.closest('.media-card');
                const id = card?.getAttribute('data-id');
                if (!id) return;
                try {
                    const res = await fetch('/api/media.php?_method=PATCH', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: Number(id), alt_text: input.value }),
                    });
                    if (res.ok) toast('Saved alt', 'ok');
                    else toast('Save failed', 'err');
                } catch (_) { toast('Network error', 'err'); }
            });
        });

        // ---- Delete ----
        document.querySelectorAll('.media-delete').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const card = btn.closest('.media-card');
                const id = card?.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Delete this asset? Pages still referencing it will show a broken image.')) return;
                btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Deleting…';
                try {
                    const res = await fetch('/api/media.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: Number(id) }),
                    });
                    const body = await res.json().catch(() => null);
                    if (res.ok && body && body.ok) {
                        card.remove();
                        toast('Deleted', 'ok');
                    } else {
                        toast((body && body.error) || ('HTTP ' + res.status), 'err');
                        btn.disabled = false; btn.textContent = orig;
                    }
                } catch (err) {
                    toast('Network error', 'err');
                    btn.disabled = false; btn.textContent = orig;
                }
            });
        });
    })();
    </script>
<?php
admin_foot();
