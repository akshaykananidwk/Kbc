<?php
/** @var App\Core\View $view */
$view->extend('layouts.admin', ['pageTitle' => 'Categories']);
$view->start('content');
?>
<div class="page-head">
  <div><h1>Question categories</h1><p class="page-head__sub">Group questions by subject. Fully configurable.</p></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card__head"><h2 class="card__title">Add a category</h2></div>
    <form method="post" action="<?= e(url('/admin/categories')) ?>">
      <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
      <div class="card__body">
        <div class="field mb-2">
          <label class="label" for="name">Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required placeholder="e.g. Ganpati Bapa">
        </div>
        <div class="field mb-2">
          <label class="label" for="description">Description</label>
          <input type="text" id="description" name="description">
        </div>
        <div class="form-grid">
          <div class="field field--6">
            <label class="label" for="colour">Colour</label>
            <input type="color" id="colour" name="colour" value="#d97706">
          </div>
          <div class="field field--6">
            <label class="label" for="sort_order">Sort order</label>
            <input type="number" id="sort_order" name="sort_order" value="0" min="0">
          </div>
        </div>
        <input type="hidden" name="status" value="active">
        <label class="check mb-2">
          <input type="checkbox" name="in_rotation" value="1" checked>
          <span>Use in balanced games
            <small>A balanced show deals its questions out over the categories in
            rotation — five categories and a ten-level ladder means two from each.</small>
          </span>
        </label>
      </div>
      <div class="card__foot"><button type="submit" class="btn btn--primary">Add category</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__title"><?= count($categories) ?> categories</h2></div>
    <div class="card__body card__body--flush">
      <?php if ($categories === []): ?>
        <div class="empty"><div class="empty__icon">⊞</div><p>No categories yet.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Name</th><th class="num">Questions</th><th>Status</th><th>In rotation</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $category): ?>
            <tr>
              <td>
                <form method="post" action="<?= e(url('/admin/categories/' . (int) $category['id'])) ?>" class="flex" style="gap:.4rem">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <input type="color" name="colour" value="<?= e($category['colour']) ?>" style="width:44px;flex:0 0 44px;padding:2px">
                  <input type="text" name="name" value="<?= e($category['name']) ?>" required style="flex:1;min-width:120px">
                  <input type="hidden" name="description" value="<?= e($category['description'] ?? '') ?>">
                  <input type="hidden" name="sort_order" value="<?= (int) $category['sort_order'] ?>">
                  <label class="check check--inline" title="Deal questions from this category in a balanced game">
                    <input type="checkbox" name="in_rotation" value="1" <?= (int) ($category['in_rotation'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>rotation</span>
                  </label>
                  <select name="status" style="width:auto;min-width:96px">
                    <option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                  <button type="submit" class="btn btn--ghost btn--sm">Save</button>
                </form>
              </td>
              <td class="num"><?= (int) $category['question_count'] ?></td>
              <td><span class="badge badge--<?= e(status_badge((string) $category['status'])) ?>"><?= e($category['status']) ?></span></td>
              <td>
                <?php if ((int) ($category['in_rotation'] ?? 1) === 1): ?>
                  <span class="badge badge--ok">↻ in rotation</span>
                <?php else: ?>
                  <span class="badge">—</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <form method="post" action="<?= e(url('/admin/categories/' . (int) $category['id'] . '/delete')) ?>"
                      data-confirm="Delete this category? Its questions will become uncategorised but will not be deleted.">
                  <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                  <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
