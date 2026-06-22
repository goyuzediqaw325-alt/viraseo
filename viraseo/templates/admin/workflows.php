<?php
defined('ABSPATH') || exit;
$workflows_dir = VIRASEO_DIR . 'n8n-workflows/';
$workflows = [];
if (is_dir($workflows_dir)) {
    foreach (glob($workflows_dir . '*.json') as $file) {
        $workflows[] = [
            'filename' => basename($file),
            'name' => basename($file, '.json'),
            'size' => filesize($file),
            'modified' => filemtime($file),
            'content' => file_get_contents($file),
        ];
    }
}
?>
<div class="wrap viraseo-wrap" dir="rtl">
    <h1 class="viraseo-page-title">
        <span class="dashicons dashicons-networking"></span>
        مدیریت ورکفلوهای n8n
    </h1>

    <p class="viraseo-page-desc">
        ورکفلوهای n8n را مشاهده، ویرایش و دانلود کنید.
        هر ورکفلو یک فایل JSON است که مستقیماً در n8n قابل Import است.
    </p>

    <div class="viraseo-toolbar">
        <button type="button" id="viraseo-wf-add-new" class="button button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            افزودن ورکفلو جدید
        </button>
        <button type="button" id="viraseo-wf-download-all" class="button button-secondary">
            <span class="dashicons dashicons-download"></span>
            دانلود همه (ZIP)
        </button>
    </div>


    <?php if (empty($workflows)): ?>
        <div class="viraseo-empty-state-box">
            <span class="dashicons dashicons-info-outline"></span>
            <p>هیچ ورکفلویی یافت نشد. فایل‌های JSON را در پوشه <code>n8n-workflows/</code> قرار دهید.</p>
        </div>
    <?php else: ?>

    <div class="viraseo-workflows-grid">
        <?php foreach ($workflows as $index => $wf):
            $json_data = json_decode($wf['content'], true);
            $wf_name = $json_data['name'] ?? $wf['name'];
            $node_count = isset($json_data['nodes']) ? count($json_data['nodes']) : 0;
        ?>
        <div class="viraseo-workflow-card" data-index="<?php echo $index; ?>">
            <div class="viraseo-wf-header">
                <h3 class="viraseo-wf-title">
                    <span class="dashicons dashicons-randomize"></span>
                    <?php echo esc_html($wf_name); ?>
                </h3>
                <span class="viraseo-badge info"><?php echo $node_count; ?> نود</span>
            </div>
            <div class="viraseo-wf-meta">
                <span>📄 <?php echo esc_html($wf['filename']); ?></span>
                <span>📦 <?php echo size_format($wf['size']); ?></span>
            </div>
            <div class="viraseo-wf-actions">
                <button type="button" class="button viraseo-wf-view" data-index="<?php echo $index; ?>">
                    <span class="dashicons dashicons-visibility"></span> مشاهده
                </button>
                <button type="button" class="button viraseo-wf-edit" data-index="<?php echo $index; ?>">
                    <span class="dashicons dashicons-edit"></span> ویرایش
                </button>
                <button type="button" class="button viraseo-wf-download" data-filename="<?php echo esc_attr($wf['filename']); ?>">
                    <span class="dashicons dashicons-download"></span> دانلود
                </button>
                <button type="button" class="button viraseo-wf-copy" data-index="<?php echo $index; ?>">
                    <span class="dashicons dashicons-clipboard"></span> کپی JSON
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>


    <!-- Modal: View/Edit Workflow -->
    <div id="viraseo-wf-modal" class="viraseo-modal" style="display:none;">
        <div class="viraseo-modal-overlay"></div>
        <div class="viraseo-modal-content">
            <div class="viraseo-modal-header">
                <h2 id="viraseo-wf-modal-title">ورکفلو</h2>
                <button type="button" class="viraseo-modal-close">&times;</button>
            </div>
            <div class="viraseo-modal-body">
                <div class="viraseo-wf-info-bar">
                    <span id="viraseo-wf-modal-filename"></span>
                    <span id="viraseo-wf-modal-nodes"></span>
                </div>
                <div class="viraseo-wf-editor-wrapper">
                    <label for="viraseo-wf-editor">محتوای JSON ورکفلو:</label>
                    <textarea id="viraseo-wf-editor" class="viraseo-code-editor" dir="ltr" rows="20"></textarea>
                </div>
                <div class="viraseo-wf-tips">
                    <p><strong>💡 راهنما:</strong> این JSON را مستقیماً در n8n از طریق Workflows → Import from File وارد کنید.</p>
                    <p>⚠️ قبل از Import، مقدار <code>VIRASEO_SECRET</code> را در Environment Variables تنظیم کنید.</p>
                </div>
            </div>
            <div class="viraseo-modal-footer">
                <button type="button" id="viraseo-wf-save-btn" class="button button-primary" style="display:none;">
                    <span class="dashicons dashicons-saved"></span> ذخیره تغییرات
                </button>
                <button type="button" id="viraseo-wf-copy-btn" class="button button-secondary">
                    <span class="dashicons dashicons-clipboard"></span> کپی در کلیپ‌بورد
                </button>
                <button type="button" id="viraseo-wf-download-btn" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span> دانلود فایل
                </button>
                <button type="button" class="button viraseo-modal-close-btn">بستن</button>
            </div>
        </div>
    </div>

    <!-- Modal: Add New Workflow -->
    <div id="viraseo-wf-add-modal" class="viraseo-modal" style="display:none;">
        <div class="viraseo-modal-overlay"></div>
        <div class="viraseo-modal-content">
            <div class="viraseo-modal-header">
                <h2>افزودن ورکفلو جدید</h2>
                <button type="button" class="viraseo-modal-close">&times;</button>
            </div>
            <div class="viraseo-modal-body">
                <div class="viraseo-form-row">
                    <label for="viraseo-wf-new-name">نام فایل (بدون .json):</label>
                    <input type="text" id="viraseo-wf-new-name" class="regular-text" dir="ltr" placeholder="04-my-workflow" />
                </div>
                <div class="viraseo-form-row">
                    <label for="viraseo-wf-new-content">محتوای JSON:</label>
                    <textarea id="viraseo-wf-new-content" class="viraseo-code-editor" dir="ltr" rows="15" placeholder='{"name": "My Workflow", "nodes": [], "connections": {}}'></textarea>
                </div>
            </div>
            <div class="viraseo-modal-footer">
                <button type="button" id="viraseo-wf-create-btn" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> ایجاد ورکفلو
                </button>
                <button type="button" class="button viraseo-modal-close-btn">انصراف</button>
            </div>
        </div>
    </div>

    <!-- Store workflow data for JS -->
    <script type="text/javascript">
    var viraseoWorkflows = <?php echo wp_json_encode(array_map(function($wf) {
        return [
            'filename' => $wf['filename'],
            'content' => $wf['content'],
        ];
    }, $workflows)); ?>;
    </script>
</div>
