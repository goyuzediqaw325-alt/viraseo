<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <h3 class="vs-card-title">محصولات جایگزین</h3>
  <?php if (!empty($alts)) : ?>
  <div class="vs-grid-2">
    <?php foreach ($alts as $alt) : ?>
    <div class="vs-card">
      <?php if (!empty($alt['image'])) : ?>
        <img src="<?php echo esc_url($alt['image']); ?>" alt="<?php echo esc_attr($alt['name']); ?>">
      <?php endif; ?>
      <h4><?php echo esc_html($alt['name']); ?></h4>
      <p><?php echo esc_html($alt['price']); ?> تومان</p>
      <span class="vs-badge vs-badge-green">موجود</span>
      <a href="<?php echo esc_url($alt['url']); ?>" class="vs-btn vs-btn-primary vs-btn-sm">مشاهده</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <p class="vs-empty">محصول جایگزینی یافت نشد.</p>
  <?php endif; ?>
</div>
