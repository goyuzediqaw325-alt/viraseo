<?php
namespace ViraSEO\Features;

defined('ABSPATH') || exit;

/**
 * n8n Workflow Manager — AJAX handlers for CRUD operations on workflow JSON files
 */
class WorkflowManager {

    public function __construct() {
        add_action('wp_ajax_viraseo_wf_save', [$this, 'ajax_save']);
        add_action('wp_ajax_viraseo_wf_create', [$this, 'ajax_create']);
        add_action('wp_ajax_viraseo_wf_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_viraseo_wf_download_all', [$this, 'ajax_download_all']);
    }

    private function get_dir(): string {
        return VIRASEO_DIR . 'n8n-workflows/';
    }

    /**
     * Save edits to an existing workflow JSON file
     */
    public function ajax_save(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $filename = sanitize_file_name($_POST['filename'] ?? '');
        $content = wp_unslash($_POST['content'] ?? '');

        if (!$filename || !$content) {
            wp_send_json_error('نام فایل و محتوا الزامی است.');
        }

        // Validate JSON
        $decoded = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('JSON نامعتبر: ' . json_last_error_msg());
        }

        $filepath = $this->get_dir() . $filename;
        if (!file_exists($filepath)) {
            wp_send_json_error('فایل یافت نشد: ' . $filename);
        }

        // Pretty-print JSON before saving
        $pretty = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $written = file_put_contents($filepath, $pretty);

        if ($written === false) {
            wp_send_json_error('خطا در نوشتن فایل. مجوزها را بررسی کنید.');
        }

        wp_send_json_success([
            'message' => 'ورکفلو با موفقیت ذخیره شد.',
            'size' => size_format(strlen($pretty)),
        ]);
    }

    /**
     * Create a new workflow JSON file
     */
    public function ajax_create(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $name = sanitize_file_name($_POST['name'] ?? '');
        $content = wp_unslash($_POST['content'] ?? '');

        if (!$name) {
            wp_send_json_error('نام فایل الزامی است.');
        }

        // Ensure .json extension
        if (pathinfo($name, PATHINFO_EXTENSION) !== 'json') {
            $name .= '.json';
        }

        $filepath = $this->get_dir() . $name;
        if (file_exists($filepath)) {
            wp_send_json_error('فایلی با این نام وجود دارد: ' . $name);
        }

        // Default content if empty
        if (empty($content)) {
            $content = wp_json_encode([
                'name' => str_replace(['-', '_', '.json'], [' ', ' ', ''], $name),
                'nodes' => [],
                'connections' => new \stdClass(),
                'settings' => ['executionTimeout' => 300],
                'tags' => [['name' => 'ViraSEO']],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            // Validate provided JSON
            $decoded = json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('JSON نامعتبر: ' . json_last_error_msg());
            }
            $content = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $written = file_put_contents($filepath, $content);
        if ($written === false) {
            wp_send_json_error('خطا در ایجاد فایل.');
        }

        wp_send_json_success([
            'message' => 'ورکفلو جدید ایجاد شد: ' . $name,
            'filename' => $name,
        ]);
    }

    /**
     * Delete a workflow file
     */
    public function ajax_delete(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $filename = sanitize_file_name($_POST['filename'] ?? '');
        if (!$filename) {
            wp_send_json_error('نام فایل الزامی.');
        }

        $filepath = $this->get_dir() . $filename;
        if (!file_exists($filepath)) {
            wp_send_json_error('فایل یافت نشد.');
        }

        if (!unlink($filepath)) {
            wp_send_json_error('خطا در حذف فایل.');
        }

        wp_send_json_success(['message' => 'ورکفلو حذف شد.']);
    }

    /**
     * Generate a ZIP of all workflow files for download
     */
    public function ajax_download_all(): void {
        check_ajax_referer('viraseo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز.');
        }

        $dir = $this->get_dir();
        $files = glob($dir . '*.json');

        if (empty($files)) {
            wp_send_json_error('هیچ ورکفلویی یافت نشد.');
        }

        $upload_dir = wp_upload_dir();
        $zip_path = $upload_dir['basedir'] . '/viraseo-workflows.zip';
        $zip_url = $upload_dir['baseurl'] . '/viraseo-workflows.zip';

        if (!class_exists('ZipArchive')) {
            // Fallback: return list of download URLs
            $urls = [];
            foreach ($files as $f) {
                $urls[] = VIRASEO_URL . 'n8n-workflows/' . basename($f);
            }
            wp_send_json_success(['urls' => $urls, 'zip' => false]);
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            wp_send_json_error('خطا در ایجاد فایل ZIP.');
        }

        foreach ($files as $f) {
            $zip->addFile($f, basename($f));
        }
        $zip->close();

        wp_send_json_success([
            'zip' => true,
            'url' => $zip_url,
            'count' => count($files),
            'message' => count($files) . ' ورکفلو در فایل ZIP آماده دانلود است.',
        ]);
    }
}
