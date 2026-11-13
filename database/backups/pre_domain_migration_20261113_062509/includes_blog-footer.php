<?php
/**
 * MY UGC VIDEO - Public Blog Footer Partial
 * 
 * Reusable footer matching the exact brand styling and structure of MY UGC VIDEO.
 */

if (!defined('CMS_ROOT')) {
    define('CMS_ROOT', dirname(__DIR__));
}
?>
  <!-- GLOBAL FOOTER -->
  <footer class="site-footer" role="contentinfo">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <a href="<?= SITE_URL ?>/index.html" class="brand-logo" aria-label="MY UGC VIDEO Home">
            <span class="brand-title">MY UGC VIDEO</span>
            <span class="brand-subtitle">UGC Creative Agency</span>
          </a>
          <p>
            A modern creative agency engineering authentic, scroll-stopping UGC video production, creator-led content, and performance marketing video ads for ambitious brands.
          </p>
          <div class="social-links">
            <a href="https://instagram.com" class="social-link" target="_blank" rel="noopener noreferrer">Instagram</a>
            <a href="https://tiktok.com" class="social-link" target="_blank" rel="noopener noreferrer">TikTok</a>
            <a href="https://linkedin.com" class="social-link" target="_blank" rel="noopener noreferrer">LinkedIn</a>
          </div>
        </div>

        <div>
          <h4 class="footer-title">Navigation</h4>
          <ul class="footer-links">
            <li><a href="<?= SITE_URL ?>/" class="footer-link">Home</a></li>
            <li><a href="<?= SITE_URL ?>/about" class="footer-link">About Studio</a></li>
            <li><a href="<?= SITE_URL ?>/services" class="footer-link">Services</a></li>
            <li><a href="<?= SITE_URL ?>/blogs" class="footer-link">Blogs</a></li>
            <li><a href="<?= SITE_URL ?>/contact" class="footer-link">Contact Us</a></li>
          </ul>
        </div>

        <div>
          <h4 class="footer-title">Our Services</h4>
          <ul class="footer-links">
            <li><a href="<?= SITE_URL ?>/services/ugc-video-creation-company" class="footer-link">UGC Video Creation Services</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-video-ads-company" class="footer-link">UGC Video Ads</a></li>
            <li><a href="<?= SITE_URL ?>/services/ai-ugc-videos-company" class="footer-link">AI UGC Video Creation</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-product-videos-company" class="footer-link">UGC Product Video</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-product-reviews-videos-company" class="footer-link">UGC Product Reviews Videos</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-video-service-in-delhi-company" class="footer-link">UGC Video Company in Delhi</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-unboxing-videos-company" class="footer-link">UGC Unboxing Videos</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-testimonial-videos-company" class="footer-link">UGC Testimonial Videos</a></li>
            <li><a href="<?= SITE_URL ?>/services/ugc-video-editing-service-company" class="footer-link">UGC Video Editing Service</a></li>
          </ul>
        </div>

        <div class="footer-newsletter">
          <h4 class="footer-title">Studio Dispatches</h4>
          <p>Join brand marketing leaders receiving our bi-weekly breakdown on UGC video performance, creative frameworks, and algorithmic updates.</p>
          <form class="newsletter-form" action="#" method="POST" novalidate>
            <div class="newsletter-input-wrap">
              <label for="newsletterEmailBlog" class="sr-only">Email address</label>
              <input type="email" id="newsletterEmailBlog" class="newsletter-input" placeholder="Your work email" required>
            </div>
            <button type="submit" class="newsletter-submit">Subscribe</button>
            <div class="newsletter-feedback" aria-live="polite"></div>
          </form>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="copyright">
          &copy; <?= date('Y') ?> MY UGC VIDEO Creative Agency. All rights reserved.
        </div>
        <div class="contact-placeholder-strip">
          <span class="tag-mono" style="background: rgba(0, 34, 30, 0.6); border-color: var(--border-medium); color: var(--text-secondary);">
            Inquiries: hello@myugcvideo.example.com
          </span>
        </div>
      </div>
    </div>
  </footer>

  <script src="<?= SITE_URL ?>/js/main.js"></script>
</body>
</html>

