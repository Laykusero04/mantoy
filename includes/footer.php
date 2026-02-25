            </div><!-- /.container-fluid -->
        </div><!-- /#main-content -->
    </div><!-- /#wrapper -->

    <!-- Footer -->
    <footer class="text-center text-muted py-3 border-top bg-light" id="main-footer">
        <small><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </footer>

    <!-- JS -->
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/chart.umd.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.getElementById('main-content')?.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
    <?php if (isset($pageScripts)): ?>
        <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>
