<footer class="site-footer" id="contact">
    <div class="container footer-grid retail-footer-grid">
        <div>
            <a class="retail-brand footer-logo" href="<?= esc($appConfig['base_url']); ?>">
                <span class="brand-mark"><img src="<?= esc(rtrim((string) $appConfig['base_url'], '/') . '/'); ?>images/logo-round.png" alt=""></span>
                <span class="brand-copy"><strong>SA Distribution</strong><small>Enterprise IT &amp; Business Solutions</small></span>
            </a>
            <p>Connecting South African businesses with trusted global technology—stock, price and service you can rely on.</p>
            <div class="footer-trust"><span><i class="bi bi-shield-check"></i> Secure shopping</span><span><i class="bi bi-truck"></i> Nationwide delivery</span></div>
        </div>
        <div><h2>Shop</h2><a href="products.php?search=laptop">Laptops</a><a href="products.php?search=network">Networking</a><a href="products.php?search=security">CCTV &amp; Security</a><a href="products.php?search=printer">Office &amp; Printing</a><a href="products.php?sort=featured">Latest deals</a></div>
        <div><h2>Customer service</h2><a href="account-dashboard.php">My account</a><a href="cart.php">Cart</a><a href="wishlist.php">Wishlist</a><a href="quote-request.php">Request a quote</a><a href="#contact">Delivery &amp; returns</a></div>
        <div>
            <h2>Contact</h2>
            <p><i class="bi bi-geo-alt"></i> Cape Town, South Africa</p>
            <p><a href="tel:+27651109824"><i class="bi bi-telephone"></i> +27 65 110 9824</a></p>
            <p><a href="https://wa.me/27651109824" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp sales</a></p>
            <p><a href="mailto:sales@sbdistibution.co.za"><i class="bi bi-envelope"></i> sales@sbdistibution.co.za</a></p>
            <p><i class="bi bi-clock"></i> Always open</p>
            <a href="https://www.facebook.com/profile.php?id=61584556474368" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
            <a class="footer-quote" href="quote-request.php">Get a business quote</a>
        </div>
    </div>
    <div class="container footer-bottom"><p>&copy; <?= date('Y'); ?> SA Business Distribution. All rights reserved.</p><p>Business technology, delivered.</p></div>
</footer>
