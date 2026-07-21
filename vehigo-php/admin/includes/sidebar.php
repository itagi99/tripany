<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="bg-glow bg-glow-1"></div>
<div class="bg-glow bg-glow-2"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">T</div>
        <div class="sidebar-brand">
            <span class="sidebar-brand-name">TripAny</span>
            <span class="sidebar-brand-sub">Fleet Admin</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <div class="sidebar-section-label">Main</div>
            <a href="index.php" class="nav-item nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-label">Fleet</div>
            <a href="vehicles.php" class="nav-item nav-link <?= $currentPage === 'vehicles' ? 'active' : '' ?>">
                <i class="bi bi-car-front-fill"></i><span>Vehicles</span>
            </a>
            <a href="drivers.php" class="nav-item nav-link <?= $currentPage === 'drivers' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i><span>Drivers</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-label">Operations</div>
            <a href="bookings.php" class="nav-item nav-link <?= $currentPage === 'bookings' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-check-fill"></i><span>Bookings</span>
            </a>
            <a href="pricing.php" class="nav-item nav-link <?= $currentPage === 'pricing' ? 'active' : '' ?>">
                <i class="bi bi-currency-rupee"></i><span>Pricing</span>
            </a>
            <a href="tours.php" class="nav-item nav-link <?= $currentPage === 'tours' ? 'active' : '' ?>">
                <i class="bi bi-suitcase-lg-fill"></i><span>Tour Packages</span>
            </a>
            <a href="addons.php" class="nav-item nav-link <?= $currentPage === 'addons' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i><span>Tour Add-ons</span>
            </a>
            <a href="sos.php" class="nav-item nav-link <?= $currentPage === 'sos' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i><span>SOS Alerts</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-label">Marketing</div>
            <a href="coupons.php" class="nav-item nav-link <?= $currentPage === 'coupons' ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated-fill"></i><span>Coupons</span>
            </a>
            <a href="banners.php" class="nav-item nav-link <?= $currentPage === 'banners' ? 'active' : '' ?>">
                <i class="bi bi-image-fill"></i><span>Banners</span>
            </a>
            <a href="offers.php" class="nav-item nav-link <?= $currentPage === 'offers' ? 'active' : '' ?>">
                <i class="bi bi-percent"></i><span>Offers</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link">
            <i class="bi bi-box-arrow-left"></i><span>Logout</span>
        </a>
    </div>
</aside>
