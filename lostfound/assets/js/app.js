// assets/js/app.js

// ---- Dark Mode ----
// NOTE: Initial dark class is applied inline in header.php <head>
// This only handles the toggle button click
document.addEventListener('DOMContentLoaded', function () {

    // Dark mode toggle button
    const toggle = document.getElementById('darkToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            const html   = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    }

    // Auto-dismiss flash message after 4s
    const flash = document.getElementById('flash-msg');
    if (flash) {
        setTimeout(() => { if (flash) flash.remove(); }, 4000);
    }

    // Live search filter — works on any table with rows having data-search attribute
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('tr[data-search]').forEach(row => {
                row.style.display = (!q || row.dataset.search.toLowerCase().includes(q)) ? '' : 'none';
            });
        });
    }

    // Confirm dialogs on data-confirm elements (links AND buttons)
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    });

    // Modal open
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = document.getElementById(this.dataset.modalOpen);
            if (modal) {
                modal.classList.remove('hidden');
                // Focus first input inside modal
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) setTimeout(() => firstInput.focus(), 50);
            }
        });
    });

    // Modal close buttons
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = document.getElementById(this.dataset.modalClose);
            if (modal) modal.classList.add('hidden');
        });
    });

    // Close modal on backdrop click
    document.querySelectorAll('[data-modal-backdrop]').forEach(backdrop => {
        backdrop.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-modal-backdrop]:not(.hidden)').forEach(m => {
                m.classList.add('hidden');
            });
        }
    });

    // Image preview on file input
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function () {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

});
