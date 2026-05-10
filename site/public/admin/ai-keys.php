<?php
// site/public/admin/ai-keys.php — manage stored provider API keys.
//
// Lists existing keys (provider, label, when added, when last used) and
// offers an "Add key" form. Plaintext keys never round-trip back to the
// page after being stored. Master-key health is summarised at the top
// so admins know whether key storage is functional before they paste
// anything.
//
// Submissions go via fetch to /api/ai/keys.php; the page reloads on
// success so the new row shows up.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/ai/keys.php';

auth_require_login();

// Master-key health check: surface any config error in a banner, not a 500.
$master_key_ok = false;
$master_key_err = null;
try {
    require_once __DIR__ . '/../../../core/lib/crypto.php';
    crypto_master_key();
    $master_key_ok = true;
} catch (Throwable $e) {
    $master_key_err = $e->getMessage();
}

$rows = $master_key_ok ? ai_keys_list() : [];

admin_head('AI keys', 'ai_keys');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">AI provider keys</h1>
        <span class="text-sm text-ink-500"><?= count($rows) ?> stored</span>
    </div>
    <p class="mt-2 text-ink-600">
        Bring-your-own keys for Gemini and OpenRouter. Stored encrypted with libsodium.
        The site decrypts in memory on each call and never echoes plaintext back to the browser.
    </p>

    <?php if (!$master_key_ok): ?>
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="font-medium">Master key not configured</div>
            <div class="mt-1"><?= e((string)$master_key_err) ?></div>
            <div class="mt-2 text-red-700">
                Generate one and add to <code>.env</code> as <code>AI_KEYS_MASTER_KEY</code>:
                <pre class="mt-1 overflow-x-auto rounded bg-white/60 p-2 text-xs">php -r 'echo base64_encode(random_bytes(32)), "\n";'</pre>
                Once set, every stored key is encrypted with it. <strong>Losing this value makes existing keys unrecoverable.</strong>
            </div>
        </div>
    <?php else: ?>
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2 rounded-2xl border border-ink-100 bg-white">
                <header class="border-b border-ink-100 px-4 py-3">
                    <h2 class="text-sm font-medium text-ink-700">Stored keys</h2>
                </header>
                <?php if ($rows === []): ?>
                    <div class="px-4 py-10 text-center text-sm text-ink-500">
                        No keys yet. Add one on the right to enable AI features.
                    </div>
                <?php else: ?>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-50/60 text-xs uppercase tracking-wider text-ink-500">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Provider</th>
                                <th class="px-4 py-2.5 font-medium">Label</th>
                                <th class="px-4 py-2.5 font-medium">Added</th>
                                <th class="px-4 py-2.5 font-medium">Last used</th>
                                <th class="px-4 py-2.5 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <?php foreach ($rows as $r): ?>
                                <tr class="ai-key-row align-middle hover:bg-ink-50/40" data-id="<?= (int)$r['id'] ?>">
                                    <td class="px-4 py-3 font-medium text-ink-900"><?= e((string)$r['provider']) ?></td>
                                    <td class="px-4 py-3 text-ink-700"><?= e((string)($r['label'] ?? '—')) ?></td>
                                    <td class="px-4 py-3 text-ink-500 whitespace-nowrap"><?= e((string)$r['created_at']) ?></td>
                                    <td class="px-4 py-3 text-ink-500 whitespace-nowrap"><?= e((string)($r['last_used_at'] ?? '—')) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button"
                                                class="ai-key-delete inline-flex items-center gap-1 rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-ink-100 bg-white">
                <header class="border-b border-ink-100 px-4 py-3">
                    <h2 class="text-sm font-medium text-ink-700">Add a key</h2>
                </header>
                <form id="ai-key-add" class="space-y-3 p-4">
                    <label class="block text-xs font-medium text-ink-600">
                        Provider
                        <select name="provider" required
                                class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-2.5 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200">
                            <?php foreach (GUA_AI_PROVIDERS as $p): ?>
                                <option value="<?= e($p) ?>"><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-ink-600">
                        Label (optional)
                        <input type="text" name="label" maxlength="100" placeholder="e.g. personal-free"
                               class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-2.5 py-2 text-sm placeholder:text-ink-300 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200">
                        <span class="mt-1 block text-[11px] font-normal text-ink-400">Helps when you keep more than one key per provider.</span>
                    </label>
                    <label class="block text-xs font-medium text-ink-600">
                        API key
                        <input type="password" name="api_key" required autocomplete="off" spellcheck="false"
                               class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-2.5 py-2 font-mono text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200">
                        <span class="mt-1 block text-[11px] font-normal text-ink-400">Sent over HTTPS, encrypted with the master key, never echoed back.</span>
                    </label>
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:bg-ink-300">
                        Save key
                    </button>
                </form>
            </section>
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

        const addForm = document.getElementById('ai-key-add');
        if (addForm) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(addForm);
                const payload = {
                    provider: fd.get('provider'),
                    label:    fd.get('label') || null,
                    api_key:  fd.get('api_key'),
                };
                const btn = addForm.querySelector('button[type="submit"]');
                btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Saving…';
                try {
                    const res = await fetch('/api/ai/keys.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    const body = await res.json().catch(() => null);
                    if (res.ok && body && body.ok) {
                        toast('Key saved', 'ok');
                        setTimeout(() => location.reload(), 400);
                    } else {
                        toast((body && body.error) || ('HTTP ' + res.status), 'err');
                    }
                } catch (err) {
                    toast('Network error', 'err');
                } finally {
                    btn.disabled = false; btn.textContent = orig;
                }
            });
        }

        document.querySelectorAll('.ai-key-delete').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('.ai-key-row');
                const id = row?.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Delete this key? Any AI features using it will stop working until a replacement is added.')) return;
                btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Deleting…';
                try {
                    const res = await fetch('/api/ai/keys.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: Number(id) }),
                    });
                    const body = await res.json().catch(() => null);
                    if (res.ok && body && body.ok) {
                        row.remove();
                        toast('Key deleted', 'ok');
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
