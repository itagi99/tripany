<?php
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<header class="top-navbar">
    <div class="navbar-left">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
            <i class="bi bi-list"></i>
        </button>
        <h4 class="page-title" style="margin:0;font-size:1.125rem;"><?= $pageTitle ?></h4>
    </div>
    <div class="navbar-right">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search anything... (⌘K)">
        </div>
        <button class="nav-btn" data-tooltip="Notifications">
            <i class="bi bi-bell"></i>
            <span class="badge-dot"></span>
        </button>
        <button class="nav-btn" data-tooltip="Messages">
            <i class="bi bi-chat-dots"></i>
        </button>
        <div class="admin-profile">
            <div class="admin-avatar">A</div>
            <div class="admin-info">
                <span class="admin-name">Admin</span>
                <span class="admin-role">Super Admin</span>
            </div>
        </div>
    </div>
</header>
