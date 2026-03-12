    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar   = document.querySelector('.sidebar');
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        }

        // Auto-hide alerts
        document.querySelectorAll('.alert-auto-hide').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity .4s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 400);
            }, 3500);
        });
    </script>
</body>
</html>
