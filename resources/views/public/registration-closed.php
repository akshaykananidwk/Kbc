<?php /** Shown when public registration has been switched off. */ ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>નોંધણી બંધ</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/public.css')) ?>">
</head>
<body>
<div class="p-shell">
  <header class="p-head">
    <div class="p-head__mark">ॐ</div>
    <h1 class="p-head__title"><?= e(setting('site_name', 'Ganpati Bapa Quiz Show')) ?></h1>
  </header>
  <div class="p-card">
    <h2 class="p-card__title">નોંધણી હાલ બંધ છે</h2>
    <p class="p-card__sub">કૃપા કરીને કાર્યક્રમના સંચાલકનો સંપર્ક કરો.</p>
  </div>
</div>
</body>
</html>
