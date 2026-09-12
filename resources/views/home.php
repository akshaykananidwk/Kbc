<?php /** @var App\Core\View $view */ ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'gu')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?><?= $siteTagline !== '' ? ' — ' . e($siteTagline) : '' ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($siteName) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php if ($ganpatiImage !== ''): ?><meta property="og:image" content="<?= e($ganpatiImage) ?>"><?php endif; ?>
<?php if (($favicon ?? '') !== ''): ?><link rel="icon" href="<?= e(upload_url($favicon)) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<style>
:root{--brand-primary:<?= e($theme['primary']) ?>;--brand-secondary:<?= e($theme['secondary']) ?>;--brand-accent:<?= e($theme['accent']) ?>;}
.hero{min-height:100vh;display:grid;place-items:center;padding:2rem 1.2rem;text-align:center;
  background:radial-gradient(1200px 520px at 50% -160px,rgba(245,166,35,.34),transparent 65%),
             linear-gradient(160deg,#7d0d11 0%,#4c080b 100%);color:#fff;}
.hero__inner{max-width:640px;width:100%;}
.hero__mark{width:112px;height:112px;margin:0 auto 1.2rem;border-radius:30px;overflow:hidden;
  background:linear-gradient(140deg,var(--brand-secondary),var(--brand-accent));color:#6d0b10;
  display:grid;place-items:center;font-size:3rem;font-weight:800;box-shadow:0 18px 48px rgba(0,0,0,.34);}
.hero__mark img{width:100%;height:100%;object-fit:cover;}
.hero h1{font-size:clamp(1.9rem,5.5vw,3rem);margin-bottom:.4rem;text-shadow:0 3px 14px rgba(0,0,0,.35);}
.hero p{opacity:.88;font-size:1.02rem;}
.hero__links{display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;margin-top:1.6rem;}
.hero__contact{margin-top:1.6rem;font-size:.88rem;opacity:.78;}
</style>
</head>
<body>
<div class="hero">
  <div class="hero__inner">
    <div class="hero__mark">
      <?php if ($ganpatiImage !== ''): ?><img src="<?= e($ganpatiImage) ?>" alt="">
      <?php elseif (($siteLogo ?? '') !== ''): ?><img src="<?= e(upload_url($siteLogo)) ?>" alt="">
      <?php else: ?>ॐ<?php endif; ?>
    </div>
    <h1><?= e($siteName) ?></h1>
    <p><?= e($siteTagline !== '' ? $siteTagline : $metaDescription) ?></p>
    <div class="hero__links">
      <a class="btn btn--gold btn--lg" href="<?= e(url('/display')) ?>">Open display screen</a>
      <a class="btn btn--ghost btn--lg" style="color:#fff;border-color:rgba(255,255,255,.4)" href="<?= e(url('/admin')) ?>">Admin &amp; operator</a>
    </div>
    <?php if ($contactPhone !== '' || $contactEmail !== ''): ?>
    <div class="hero__contact">
      <?php if ($contactPhone !== ''): ?><span><?= e($contactPhone) ?></span><?php endif; ?>
      <?php if ($contactPhone !== '' && $contactEmail !== ''): ?> &middot; <?php endif; ?>
      <?php if ($contactEmail !== ''): ?><a style="color:var(--brand-accent)" href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hero__contact"><?= e($footerText) ?></div>
  </div>
</div>
</body>
</html>
