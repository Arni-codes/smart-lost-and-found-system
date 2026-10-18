    </main>

    <!-- Global Site Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Column 1: Brand & Mission -->
                <div>
                    <div class="footer-brand">
                        <img src="<?= url('assets/images/logo.svg') ?>" alt="SCLFS Logo" width="28" height="28">
                        <span><?= e(APP_NAME) ?></span>
                    </div>
                    <p class="footer-desc">
                        A centralized, secure lost-and-found tracking system for our college campus. 
                        Facilitating quick recovery through smart matching and verified desk handovers.
                    </p>
                </div>

                <!-- Column 2: Quick Links -->
                <div>
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('index.php') ?>">Home</a></li>
                        <li><a href="<?= url('items.php') ?>">Browse Items</a></li>
                        <li><a href="<?= url('report-lost.php') ?>">Report Lost Item</a></li>
                        <li><a href="<?= url('report-found.php') ?>">Report Found Item</a></li>
                    </ul>
                </div>

                <!-- Column 3: Campus Support -->
                <div>
                    <h3 class="footer-title">Campus Support</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('test_db.php') ?>">Database Connection Status</a></li>
                        <li><a href="<?= url('login.php') ?>">Student / Staff Login</a></li>
                        <li><a href="<?= url('register.php') ?>">Register Account</a></li>
                        <li><span style="font-size:0.875rem; color:#94a3b8;">Security Desk: Ground Floor Admin Block</span></li>
                    </ul>
                </div>

                <!-- Column 4: System Information -->
                <div>
                    <h3 class="footer-title">System Specs</h3>
                    <p class="footer-desc" style="font-size: 0.8125rem;">
                        <strong>Environment:</strong> Local XAMPP<br>
                        <strong>Engine:</strong> Plain PHP (PDO) &amp; MySQL<br>
                        <strong>Version:</strong> <?= e(APP_VERSION) ?><br>
                        <strong>Shared Foundation:</strong> Active &amp; Verified
                    </p>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span>Local XAMPP Foundation Operational</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global Client Scripts -->
    <script src="<?= url('assets/js/app.js') ?>"></script>
    <?php if (!empty($extraScripts) && is_array($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= url($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
