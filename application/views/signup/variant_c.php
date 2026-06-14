<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= html_escape($title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tiro+Bangla:ital@0;1&family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php $this->load->view('signup/_styles'); ?>
<script>window.BD_BASE_URL = <?= json_encode(base_url()); ?>;</script>
</head>
<body>
<div class="bd-shell bd-shell--tilt">
  <?php $this->load->view('signup/_scripts'); ?>
  <div class="bd-wrap">
    <div class="bd-brand">
      SmartSchool.bd
      <small>Pick a plan and get your school online today</small>
    </div>

    <div class="bd-card">
      <h1 lang="bn">ডোমেইন এবং ওয়েবসাইট সেবার নিবন্ধন ফরম</h1>
      <p class="bd-warning" lang="bn">সতর্কতাঃ নিচের ফরমটি সম্পূর্ণ ইংরেজিতে পূরণ আবশ্যক</p>
      <?= form_open(base_url('signup/c')); ?>
        <?php $this->load->view('signup/_form_fields'); ?>
      <?= form_close(); ?>
    </div>

    <p class="bd-footer">Already on SmartSchool? <a href="<?= base_url('login'); ?>">Sign in</a>.</p>
  </div>
</div>
</body>
</html>
