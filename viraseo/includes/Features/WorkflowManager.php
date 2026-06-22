<?php
namespace ViraSEO\Features;
defined('ABSPATH') || exit;

/** Workflow Manager — CRUD for n8n JSON files + configurator */
class WorkflowManager {
    public function __construct() {
        add_action('wp_ajax_viraseo_wf_list', [$this, 'ajax_list']);
        add_action('wp_ajax_viraseo_wf_save', [$this, 'ajax_save']);
        add_action('wp_ajax_viraseo_wf_create', [$this, 'ajax_create']);
        add_action('wp_ajax_viraseo_wf_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_viraseo_wf_configure', [$this, 'ajax_configure']);
    }

    private function dir(): string { return VIRASEO_DIR.'n8n-workflows/'; }

    public function ajax_list(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $files = glob($this->dir().'*.json');
        $list = [];
        foreach ($files as $f) {
            $content = file_get_contents($f);
            $json = json_decode($content, true);
            $list[] = [
                'filename'=>basename($f),
                'name'=>$json['name']??basename($f,'.json'),
                'nodes'=>isset($json['nodes'])?count($json['nodes']):0,
                'size'=>size_format(filesize($f)),
                'content'=>$content,
            ];
        }
        wp_send_json_success(['workflows'=>$list]);
    }

    public function ajax_save(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $fn = sanitize_file_name($_POST['filename']??'');
        $content = wp_unslash($_POST['content']??'');
        if (!$fn || !$content) wp_send_json_error('داده ناقص.');
        $decoded = json_decode($content);
        if (!$decoded) wp_send_json_error('JSON نامعتبر: '.json_last_error_msg());
        $path = $this->dir().$fn;
        if (!file_exists($path)) wp_send_json_error('فایل نیست.');
        file_put_contents($path, wp_json_encode($decoded, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        wp_send_json_success(['message'=>'ذخیره شد.']);
    }

    public function ajax_create(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $name = sanitize_file_name($_POST['name']??'');
        if (!$name) wp_send_json_error('نام الزامی.');
        if (pathinfo($name,PATHINFO_EXTENSION)!=='json') $name .= '.json';
        $path = $this->dir().$name;
        if (file_exists($path)) wp_send_json_error('موجود است.');
        $content = wp_unslash($_POST['content']??'');
        if (!$content) $content = '{"name":"'.str_replace(['-','_','.json'],' ',$name).'","nodes":[],"connections":{}}';
        file_put_contents($path, $content);
        wp_send_json_success(['message'=>'ایجاد شد.']);
    }

    public function ajax_delete(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $fn = sanitize_file_name($_POST['filename']??'');
        $path = $this->dir().$fn;
        if (file_exists($path)) unlink($path);
        wp_send_json_success();
    }

    /**
     * Configure: Takes user fields and injects into workflow JSON template
     * Fields: n8n_url, secret, site_url, gsc_property
     */
    public function ajax_configure(): void {
        check_ajax_referer('viraseo_nonce','nonce');
        $fn = sanitize_file_name($_POST['filename']??'');
        $path = $this->dir().$fn;
        if (!file_exists($path)) wp_send_json_error('فایل یافت نشد.');

        $content = file_get_contents($path);
        $site = get_site_url();
        $secret = \ViraSEO\Admin\Dashboard::get('n8n_secret');
        $callback_base = rest_url('viraseo/v1/');

        // Replace placeholder values in JSON
        $content = str_replace([
            '{{SITE_URL}}','{{SECRET}}','{{CALLBACK_BASE}}',
            '{{GSC_CALLBACK}}','{{SERP_CALLBACK}}','{{IDEAS_CALLBACK}}',
            '{{CANNIBAL_CALLBACK}}',
        ], [
            $site, $secret, $callback_base,
            $callback_base.'gsc-data',
            $callback_base.'serp-results',
            $callback_base.'keyword-ideas',
            $callback_base.'cannibalization',
        ], $content);

        wp_send_json_success([
            'configured_json'=>$content,
            'message'=>'ورکفلو با تنظیمات سایت شما پیکربندی شد. کپی کنید و در n8n وارد کنید.',
        ]);
    }
}
