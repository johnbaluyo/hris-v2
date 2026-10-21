<script>
    try {
        const choice = localStorage.getItem('hris-theme') || localStorage.getItem('flux.appearance') || 'system';
        const theme = choice === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : choice;
        document.documentElement.dataset.bsTheme = theme;
        document.documentElement.classList.toggle('dark', theme === 'dark');
    } catch { document.documentElement.dataset.bsTheme = 'light'; }
</script>
