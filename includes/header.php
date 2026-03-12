<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Sistem Monitoring Kinerja' ?> — Ditjen Migas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --migas-yellow: #F5C400;
            --migas-yellow-dark: #F5C400;
            --migas-yellow-light: #FFF8E8;
            --migas-black: #1A1A1A;
            --migas-gray: #6B7280;
            --migas-light: #F3F4F6;
            --migas-white: #FFFFFF;
            --migas-border: #E5E7EB;
            --sidebar-w: 260px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--migas-light);
            color: var(--migas-black);
            margin: 0;
        }

        /* ── TOP MINI NAVBAR ── */
        .site-topbar {
            background: var(--migas-black);
            height: 40px;
            display: flex;
            align-items: center;
            padding: 0 0 0 var(--sidebar-w);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
        }

        .site-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex: 1;
            padding: 0 24px;
        }

        .site-topbar .ministry-label {
            font-size: 10px;
            color: rgba(255,255,255,.45);
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .site-topbar .ministry-label span { color: var(--migas-yellow); }

        .site-topbar .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 11px;
            color: rgba(255,255,255,.4);
        }

        /* ── YELLOW NAV BAR ── */
        .site-navbar {
            background: var(--migas-yellow);
            height: 46px;
            display: flex;
            align-items: center;
            padding: 0 0 0 var(--sidebar-w);
            position: fixed;
            top: 40px; left: 0; right: 0;
            z-index: 199;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }

        .site-navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex: 1;
            padding: 0 24px;
        }

        .site-navbar .nav-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--migas-black);
            letter-spacing: .01em;
        }

        .site-navbar .nav-title i { margin-right: 6px; }

        .site-navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .site-navbar .nav-links a {
            font-size: 12px;
            font-weight: 700;
            color: rgba(26,26,26,.65);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all .15s;
        }

        .site-navbar .nav-links a:hover,
        .site-navbar .nav-links a.active {
            background: rgba(26,26,26,.12);
            color: var(--migas-black);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--migas-black);
            display: flex;
            flex-direction: column;
            z-index: 201;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 16px 18px 14px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(245,166,35,.06);
        }

        .sidebar-brand img {
            width: 34px; height: 34px;
            object-fit: contain;
        }

        .sidebar-brand-text { line-height: 1.2; }

        .sidebar-brand-text span {
            display: block;
            font-size: 11px;
            color: var(--migas-yellow);
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .sidebar-brand-text small {
            display: block;
            font-size: 9.5px;
            color: rgba(255,255,255,.38);
            margin-top: 1px;
        }

        .sidebar-section {
            padding: 14px 14px 3px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(255,255,255,.25);
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            margin: 1px 8px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,.6);
            text-decoration: none;
            transition: all .15s;
        }

        .sidebar-nav a:hover {
            background: rgba(245,166,35,.1);
            color: var(--migas-yellow);
        }

        .sidebar-nav a.active {
            background: var(--migas-yellow);
            color: var(--migas-black);
            font-weight: 700;
        }

        .sidebar-nav a.active i { color: var(--migas-black); }
        .sidebar-nav a i { font-size: 15px; }

        .sidebar-footer {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            background: rgba(255,255,255,.04);
        }

        .sidebar-footer .avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: var(--migas-yellow);
            color: var(--migas-black);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .sidebar-footer .uname {
            font-size: 12px;
            font-weight: 600;
            color: var(--migas-white);
            line-height: 1.2;
        }

        .sidebar-footer .urole {
            font-size: 10px;
            color: var(--migas-yellow);
            font-weight: 600;
        }

        /* ── MAIN CONTENT ── */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding-top: 86px; /* topbar + navbar height */
        }

        .topbar {
            background: var(--migas-white);
            border-bottom: 1px solid var(--migas-border);
            padding: 0 24px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 86px;
            z-index: 50;
        }

        .topbar-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--migas-black);
        }

        .page-content { padding: 24px; }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--migas-white);
            border-radius: 10px;
            padding: 18px 20px;
            border: 1px solid var(--migas-border);
            display: flex;
            align-items: center;
            gap: 14px;
            border-top: 3px solid var(--migas-yellow);
        }

        .stat-icon {
            width: 46px; height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-icon.yellow { background: var(--migas-yellow); color: var(--migas-black); }
        .stat-icon.black  { background: var(--migas-black); color: var(--migas-yellow); }
        .stat-icon.green  { background: #D1FAE5; color: #059669; }
        .stat-icon.blue   { background: #DBEAFE; color: #2563EB; }

        .stat-value { font-size: 24px; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 11.5px; color: var(--migas-gray); font-weight: 500; margin-top: 3px; }

        /* ── TABLE ── */
        .data-table { font-size: 13px; }

        .data-table th {
            background: var(--migas-yellow);
            color: var(--migas-black);
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
            vertical-align: middle;
            padding: 11px 14px;
        }

        .data-table td {
            vertical-align: middle;
            padding: 10px 14px;
            border-bottom: 1px solid var(--migas-border);
        }

        .data-table tbody tr:hover { background: var(--migas-yellow-light); }

        .card-box {
            background: var(--migas-white);
            border-radius: 10px;
            border: 1px solid var(--migas-border);
            overflow: hidden;
        }

        .card-box-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--migas-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--migas-white);
            border-left: 4px solid var(--migas-yellow);
        }

        .card-box-header h6 {
            margin: 0;
            font-weight: 700;
            font-size: 13.5px;
        }

        /* ── BADGES ── */
        .badge-iksp  { background: #FEF3C7; color: #92400E; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
        .badge-iksk2 { background: #DBEAFE; color: #1D4ED8; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
        .badge-iksk3 { background: #D1FAE5; color: #065F46; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
        .badge-iksk4 { background: #EDE9FE; color: #5B21B6; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }

        .badge-pic {
            background: var(--migas-black);
            color: var(--migas-yellow);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'DM Mono', monospace;
        }

        /* ── PROGRESS ── */
        .tw-progress { height: 6px; border-radius: 3px; background: #E5E7EB; }

        /* ── BTN ── */
        .btn-migas {
            background: var(--migas-yellow);
            color: var(--migas-black);
            border: none;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 7px;
            transition: all .15s;
        }

        .btn-migas:hover {
            background: var(--migas-yellow-dark);
            color: var(--migas-black);
            transform: translateY(-1px);
        }

        .btn-migas-outline {
            background: transparent;
            color: var(--migas-black);
            border: 1.5px solid var(--migas-border);
            font-weight: 600;
            font-size: 13px;
            padding: 7px 17px;
            border-radius: 7px;
            transition: all .15s;
        }

        .btn-migas-outline:hover {
            border-color: var(--migas-yellow);
            background: var(--migas-yellow-light);
        }

        /* ── ALERTS ── */
        .alert { border-radius: 8px; font-size: 13px; font-weight: 500; }

        /* ── FORM ── */
        .form-control, .form-select {
            border-radius: 7px;
            border: 1.5px solid var(--migas-border);
            font-size: 13.5px;
            padding: 9px 12px;
            font-family: inherit;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--migas-yellow);
            box-shadow: 0 0 0 3px rgba(245,166,35,.15);
        }

        .form-label { font-size: 13px; font-weight: 600; margin-bottom: 5px; }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #F5C400; border-radius: 10px; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: .3s; }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            .site-topbar, .site-navbar { padding-left: 0; }
        }
        
    </style>
</head>
<body>

<!-- BLACK TOP BAR -->
<div class="site-topbar">
    <div class="site-topbar-inner">
        <span class="ministry-label">Direktorat Jendral Minyak & Gas Bumi — <span>Ditjen Migas</span></span>
        <div class="topbar-right">
            <i class="bi bi-calendar3"></i> <?= date('d M Y') ?>
            <span style="opacity:.3;">|</span>
            <i class="bi bi-shield-lock"></i> Sistem Internal
        </div>
    </div>
</div>

<!-- YELLOW NAV BAR -->
<div class="site-navbar bg-warning">
    <div class="site-navbar-inner">
        <span class="nav-title">
            Sistem Monitoring Indikator Kinerja
        </span>
    </div>
</div>
