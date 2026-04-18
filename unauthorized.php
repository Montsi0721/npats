<?php
require_once __DIR__ . '/includes/config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unauthorized — NPATS</title>
<script>(function(){var t=localStorage.getItem('npats_theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}else{document.documentElement.removeAttribute('data-theme');}})()</script>
<link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);">
  <div class="card" style="max-width:460px;text-align:center;padding:3rem;">
    <i class="fa fa-ban" style="font-size:4rem;color:var(--danger);margin-bottom:1rem;"></i>
    <h1 style="color:var(--danger);margin-bottom:.5rem;">Access Denied</h1>
    <p style="color:var(--muted);margin-bottom:1.5rem;">You do not have permission to view this page.</p>
    <a href="<?= APP_URL ?>/index.php" class="btn btn-primary"><i class="fa fa-home"></i> Return to Login</a>
  </div>
</div>
</body></html>
