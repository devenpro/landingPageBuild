// site/public/assets/js/reveal.js — scroll-driven reveal animation (v2 Stage 9).
//
// Public-page polish: applies `.gua-revealed` to every `.gua-reveal` element
// when it scrolls into view, triggering the opacity/translate transition
// defined in styles.css. Honours prefers-reduced-motion (the global CSS
// override clamps transition-duration so the elements jump to their final
// state without animating).
//
// Auto-tagging: in addition to elements that already have `.gua-reveal`,
// we add the class to every <section> on the page so existing markup
// reveals on scroll without each section having to opt in. The hero
// section (first <section> or <section> with class containing 'hero')
// is excluded because it's above the fold and should be visible
// immediately — animating it would cause a jarring flash on load.

(function () {
    'use strict';

    if (!('IntersectionObserver' in window)) {
        // Old browser — flatten the reveal class so content isn't stuck hidden.
        document.querySelectorAll('.gua-reveal').forEach(function (el) {
            el.classList.add('gua-revealed');
        });
        return;
    }

    var sections = Array.from(document.querySelectorAll('main section, body > section'));
    // Skip the first section so above-the-fold content isn't held back.
    sections.slice(1).forEach(function (sec) {
        // Don't touch sections in edit-mode wrappers — admin doesn't need
        // animations interfering with the toolbar / drag UX.
        if (sec.closest('.gua-section')) return;
        sec.classList.add('gua-reveal');
    });

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('gua-revealed');
                io.unobserve(entry.target);
            }
        });
    }, {
        // Trigger slightly before the section enters the viewport
        // so the animation finishes around the time the user sees it.
        rootMargin: '0px 0px -64px 0px',
        threshold: 0.05,
    });

    document.querySelectorAll('.gua-reveal').forEach(function (el) {
        io.observe(el);
    });
})();
