<?php
/** @var App\Core\View $view */
$isEdit = $gift !== null;
$view->extend('layouts.admin', ['pageTitle' => $isEdit ? 'Edit gift' : 'Add gift']);
$view->start('content');
$old = $old ?? [];
$val = static function (string $key, mixed $fallback = '') use ($old, $gift) {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    if ($gift !== null) { return (string) ($gift[$key] ?? $fallback); }
    return (string) $fallback;
};
$action = $isEdit ? url('/admin/gifts/' . (int) $gift['id']) : url('/admin/gifts');
?>
<div class="page-head">
  <div><h1><?= $isEdit ? 'Edit gift' : 'Add gift' ?></h1></div>
  <a class="btn btn--ghost" href="<?= e(url('/admin/gifts')) ?>">← Back</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
  <div class="card">
    <div class="card__body">
      <div class="form-grid">
        <div class="field field--8">
          <label class="label" for="name">Gift name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required value="<?= e($val('name')) ?>">
        </div>
        <div class="field field--4">
          <label class="label" for="value_amount">Gift value</label>
          <input type="number" id="value_amount" name="value_amount" min="0" step="0.01" value="<?= e($val('value_amount', '0')) ?>">
        </div>
        <div class="field">
          <label class="label" for="description">Description</label>
          <textarea id="description" name="description" rows="2"><?= e($val('description')) ?></textarea>
        </div>
        <div class="field field--3">
          <label class="label" for="quantity_total">Quantity <span class="required">*</span></label>
          <input type="number" id="quantity_total" name="quantity_total" min="0" required value="<?= e($val('quantity_total', '1')) ?>">
          <?php if ($isEdit): ?><div class="field__help"><?= (int) $gift['quantity_used'] ?> already awarded.</div><?php endif; ?>
        </div>
        <div class="field field--3">
          <label class="label" for="serial_code">Serial / code</label>
          <input type="text" id="serial_code" name="serial_code" value="<?= e($val('serial_code')) ?>">
        </div>
        <div class="field field--3">
          <label class="label" for="status">Status</label>
          <select id="status" name="status" required>
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'out_of_stock' => 'Out of stock'] as $key => $label): ?>
              <option value="<?= $key ?>" <?= $val('status', 'active') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field field--3">
          <label class="label" for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" min="0" value="<?= e($val('sort_order', '0')) ?>">
        </div>
        <div class="field field--6">
          <label class="label" for="image">Gift image</label>
          <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" data-preview="#giftPreview">
          <img id="giftPreview" class="thumb thumb--lg mt-1"
               src="<?= e($isEdit && ($gift['image_path'] ?? '') !== '' ? upload_url($gift['image_path']) : '') ?>"
               style="<?= $isEdit && ($gift['image_path'] ?? '') !== '' ? '' : 'display:none' ?>" alt="">
          <?php if ($isEdit && ($gift['image_path'] ?? '') !== ''): ?>
            <label class="check mt-1"><input type="checkbox" name="remove_image" value="1"><span>Remove image</span></label>
          <?php endif; ?>
        </div>
        <div class="field field--6">
          <label class="label" for="prize_level_id">Attach to prize level</label>
          <select id="prize_level_id" name="prize_level_id">
            <option value="">Do not change</option>
            <?php foreach ($levels as $level): ?>
              <option value="<?= (int) $level['id'] ?>" <?= $isEdit && (int) $level['gift_id'] === (int) $gift['id'] ? 'selected' : '' ?>>
                Question <?= (int) $level['level_no'] ?> — <?= e(money($level['amount'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="field__help">You can also set this from the Prize ladder page.</div>
        </div>
      </div>
    </div>
    <div class="card__foot">
      <button type="submit" class="btn btn--primary btn--lg"><?= $isEdit ? 'Save changes' : 'Add gift' ?></button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/gifts')) ?>">Cancel</a>
    </div>
  </div>
</form>
<?php $view->stop(); ?>
