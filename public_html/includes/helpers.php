<?php
// includes/helpers.php — small pure utilities used by partials and APIs.
// Keep this file dependency-free; do not add logic that needs DB or session.

declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}
