        <!-- Page content from other files (like index.php, reports.php) ends here -->
    </div> <!-- This is the closing tag for <div class="container main-content-area" style="padding-top: 20px; padding-bottom: 20px;"> from header.php -->

    <footer class="site-footer-container">
        <div class="footer-content-wrapper">
            <div class="footer-section about-us">
                <h4>VirtualOplossing TimeTracker</h4>
                <p class="time-value-quote">
                    "Lost time is never found again." – Benjamin Franklin. <br>
                    Track your moments, master your productivity, and make every second count.
                </p>
            </div>

            <div class="footer-section quick-links">
                <h4>Quick Links</h4>
                <ul>
                    <?php if (isLoggedIn()): // Assumes isLoggedIn() is available via header.php -> functions.php ?>
                        <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> My Reports</a></li>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="admin_reports.php"><i class="fas fa-user-shield"></i> Admin Reports</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-section contact-info">
                <h4>Contact Us</h4>
                <p><i class="fas fa-phone-alt"></i> <a href="tel:+917986152007">+91 7986152007</a></p>
                <p><i class="fas fa-envelope"></i> <a href="mailto:karanbir@virtualoplossing.com">karanbir@virtualoplossing.com</a></p>
                <p style="display:none;"><i class="fas fa-envelope"></i> <a href="mailto:xibok40918@asimarif.com">xibok40918@asimarif.com</a></p>
                <!-- Add address or social media if needed -->
                
                <p><i class="fas fa-map-marker-alt"></i>160072, Mohali,Punjab, India </p>
                <div class="social-links">
                    <a href="https://www.google.com/search?q=karanbirsinghdhiman&rlz=1C1CHBD_enIN1158IN1158&oq=karanbirsinghdhiman&gs_lcrp=EgZjaHJvbWUyBggAEEUYOTIKCAEQABiABBiiBDIKCAIQABiABBiiBDIHCAMQABjvBTIKCAQQABiABBiiBDIHCAUQABjvBdIBCDM5NDJqMGo0qAIAsAIA&sourceid=chrome&ie=UTF-8" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://x.com/Karan__S1ngh" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="https://in.linkedin.com/in/karanbir-73b3b3192?trk=public_profile_browsemap" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
               
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date("Y") ?> VirtualOplossing. All Rights Reserved. Designed with <i class="fas fa-heart" style="color: #e25555;"></i>.</p>
        </div>
    </footer>

    <!-- JavaScript for header (time display, mobile menu) and any other site-wide scripts -->
<script src="js/script.js"></script> 
</body>
</html>