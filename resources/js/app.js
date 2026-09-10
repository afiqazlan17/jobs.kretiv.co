

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Cross-component state for the job detail page's Action dropdown (header)
// and its corresponding form panels (main content) — two separate DOM
// subtrees under one Blade layout, so a shared store is simpler than
// threading state through x-data props. Harmless no-op on every other page.
Alpine.store('jobActions', { panel: null });

Alpine.start();
