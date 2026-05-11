// core/build/tailwind.config.js — config for the Tailwind v3 CLI standalone build.
//
// Compiled by core/build/build-css.sh into site/public/assets/css/styles.css.
// The compiled output is committed to the repo so deploys stay
// build-tool-free; only re-run the build when content paths or theme
// tokens change.
//
// Theme tokens here MUST match what was previously inlined in
// site/layout.php and site/public/admin/_layout.php (the brand/ink
// scales and the InterVariable font stack) so visual parity is
// preserved as the CDN is removed.

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './site/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"InterVariable"', '"Inter"', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
                brand: {
                    50:  '#f5f3ff',
                    100: '#ede9fe',
                    200: '#ddd6fe',
                    300: '#c4b5fd',
                    400: '#a78bfa',
                    500: '#8b5cf6',
                    600: '#7c3aed',
                    700: '#6d28d9',
                    800: '#5b21b6',
                    900: '#4c1d95',
                },
                ink: {
                    50:  '#f9fafb',
                    100: '#f3f4f6',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    800: '#1f2937',
                    900: '#111827',
                },
            },
        },
    },
    plugins: [],
};
