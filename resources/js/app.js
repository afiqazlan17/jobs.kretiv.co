import Alpine from 'alpinejs';
import Quill from 'quill';

window.Alpine = Alpine;

// Cross-component state for the job detail page's Action dropdown (header)
// and its corresponding form panels (main content) — two separate DOM
// subtrees under one Blade layout, so a shared store is simpler than
// threading state through x-data props. Harmless no-op on every other page.
Alpine.store('jobActions', { panel: null });

// The job detail page's "New Note" composer. Quill only shapes what a
// well-behaved browser sends — the actual security boundary is server-side
// (App\Support\NoteSanitizer), since the hidden `note` input's value can be
// edited directly before submit regardless of what the editor produces.
window.noteComposer = function () {
    return {
        note: '',
        mount(el) {
            this.quill = new Quill(el, {
                theme: 'snow',
                placeholder: 'Add a note about this job...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ color: [] }],
                        ['link'],
                        ['clean'],
                    ],
                },
            });
        },
        sync() {
            this.note = this.quill.getText().trim() ? this.quill.root.innerHTML : '';
        },
        reset() {
            this.quill.setText('');
            this.note = '';
        },
    };
};

Alpine.start();
