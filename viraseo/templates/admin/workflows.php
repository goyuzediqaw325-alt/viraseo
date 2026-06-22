<?php defined('ABSPATH') || exit; ?>
<div class="vs-wrap" dir="rtl">
  <div class="vs-header">
    <h1 class="vs-title">ورکفلوهای n8n</h1>
    <span class="vs-badge vs-badge-blue">🔵 نیازمند n8n</span>
  </div>
  <div class="vs-alert vs-alert-info">
    <p>ورکفلوهای آماده برای وارد کردن در n8n. هر ورکفلو را می‌توانید کپی یا دانلود کنید و در پنل n8n خود Import نمایید.</p>
  </div>
  <div class="vs-wf-grid" id="vs-wf-grid"></div>
  <div class="vs-modal" id="vs-wf-modal" style="display:none">
    <div class="vs-modal-bg"></div>
    <div class="vs-modal-box">
      <div class="vs-modal-head">
        <span id="vs-wf-modal-title">ورکفلو</span>
        <button class="vs-modal-close">&times;</button>
      </div>
      <div class="vs-modal-body">
        <textarea class="vs-textarea vs-code" id="vs-wf-editor" rows="12"></textarea>
      </div>
      <div class="vs-modal-foot">
        <button class="vs-btn vs-btn-success" id="vs-wf-save">ذخیره</button>
        <button class="vs-btn vs-btn-secondary" id="vs-wf-copy-btn">کپی</button>
        <button class="vs-btn vs-btn-primary" id="vs-wf-dl-btn">دانلود</button>
      </div>
    </div>
  </div>
</div>
