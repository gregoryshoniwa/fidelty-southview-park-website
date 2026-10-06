{{-- Backup for the ⌘K / Ctrl+K search shortcut: listens first (capture phase) so other page handlers can't swallow it. --}}
<script>
    window.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K')) {
            var input = document.querySelector('.fi-global-search-field input');
            if (!input) return;
            e.preventDefault();
            input.focus();
            input.select();
        }
    }, true);
</script>
