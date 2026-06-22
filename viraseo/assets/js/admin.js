(function($){
'use strict';
const V = window.VS || {};
if (!V.ajax) return;
const N = V.nonce;

// === HELPERS ===
function post(action, data, cb) {
    data = data || {};
    data.action = action;
    data.nonce = N;
    $.post(V.ajax, data, cb);
}
function toast(msg, type) {
    const el = $('<div class="vs-toast vs-toast-'+( type||'info')+'">'+msg+'</div>').appendTo('.vs-wrap');
    setTimeout(()=> el.addClass('show'), 10);
    setTimeout(()=> el.remove(), 4000);
}

// === TABS ===
$(document).on('click', '.vs-tab', function(e){
    e.preventDefault();
    const t = $(this).data('tab');
    $(this).addClass('active').siblings().removeClass('active');
    $(this).closest('.vs-wrap').find('.vs-tab-panel').removeClass('active');
    $('#panel-'+t).addClass('active');
});

// === SETTINGS: Test n8n ===
$(document).on('click', '#vs-test-n8n', function(){
    const $s = $('#vs-n8n-status').text('...').removeClass('ok err');
    post('viraseo_test_n8n', {}, r => {
        if (r.success) $s.text(r.data).addClass('ok');
        else $s.text(r.data).addClass('err');
    });
});


// === GSC OAuth ===
$(document).on('click', '#vs-gsc-connect', function(){
    post('viraseo_gsc_auth_url', {}, r => {
        if (r.success) window.location.href = r.data.url;
        else toast(r.data, 'err');
    });
});
$(document).on('click', '#vs-gsc-disconnect', function(){
    if (!confirm('قطع اتصال؟')) return;
    post('viraseo_gsc_disconnect', {}, ()=> location.reload());
});
$(document).on('click', '#vs-gsc-sync', function(){
    const $b = $(this).prop('disabled',true);
    const $s = $('#vs-sync-status').text('در حال همگام‌سازی...');
    post('viraseo_gsc_fetch', {days: 28}, r => {
        $b.prop('disabled',false);
        if (r.success) { $s.text(r.data.message); toast(r.data.message,'success'); loadKeywords(); }
        else $s.text(r.data||'خطا');
    });
});

// === KEYWORDS ===
function loadKeywords(search, page) {
    post('viraseo_get_keywords', {search: search||$('#vs-kw-search').val()||'', page: page||1}, r => {
        if (!r.success) return;
        const $t = $('#vs-kw-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">داده‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(k => {
            $t.append(`<tr>
                <td>${k.keyword}${k.is_striking?' <span class="vs-badge vs-badge-orange">⭐</span>':''}</td>
                <td>${k.clicks}</td><td>${k.impressions}</td><td>${k.ctr}</td><td>${k.position}</td>
                <td><a href="${k.page_url}" target="_blank" class="vs-btn vs-btn-sm vs-btn-secondary">↗</a></td>
            </tr>`);
        });
    });
}
$(document).on('keyup', '#vs-kw-search', function(){ loadKeywords(); });
$(document).on('click', '#vs-detect-cannibal', function(){
    post('viraseo_detect_cannibal', {}, r => {
        if (r.success) { toast(`${r.data.detected} تعارض شناسایی شد.`,'success'); loadCannibal(); }
    });
});


// === STRIKING ===
function loadStriking() {
    post('viraseo_get_striking', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-striking-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">فرصتی یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(k => {
            $t.append(`<tr><td>${k.keyword}</td><td>${k.impressions}</td><td>${k.clicks}</td><td>${k.position}</td><td><a href="${k.page_url}" target="_blank">↗</a></td></tr>`);
        });
    });
}

// === CANNIBALIZATION ===
function loadCannibal() {
    post('viraseo_get_cannibal', {}, r => {
        if (!r.success) return;
        const $c = $('#vs-cannibal-list').empty();
        if (!r.data.rows.length) { $c.html('<div class="vs-empty">تعارضی شناسایی نشده.</div>'); return; }
        r.data.rows.forEach(c => {
            $c.append(`<div class="vs-conflict ${c.severity}">
                <div class="vs-conflict-head"><span class="vs-badge vs-badge-${c.severity==='critical'?'red':c.severity==='warning'?'orange':'blue'}">${c.severity}</span><span class="vs-conflict-kw">${c.keyword}</span></div>
                <div class="vs-conflict-pages"><div>صفحه ۱: <a href="${c.page_1.url}" target="_blank">${c.page_1.url.substring(0,50)}</a><br>جایگاه: ${c.page_1.pos}</div><div class="vs-conflict-vs">⚡</div><div>صفحه ۲: <a href="${c.page_2.url}" target="_blank">${c.page_2.url.substring(0,50)}</a><br>جایگاه: ${c.page_2.pos}</div></div>
                <div class="vs-conflict-foot"><span>💡 ${c.recommendation}</span><button class="vs-btn vs-btn-sm vs-btn-success vs-resolve" data-id="${c.id}">حل شد</button></div>
            </div>`);
        });
    });
}
$(document).on('click', '.vs-resolve', function(){
    post('viraseo_resolve_cannibal', {id:$(this).data('id'), status:'resolved'}, ()=> loadCannibal());
});

// === SERP ANALYSIS ===
$(document).on('click', '#vs-serp-start', function(){
    const kw = $('#vs-serp-kw').val().trim();
    if (!kw) return;
    $(this).prop('disabled',true);
    $('#vs-serp-progress').show();
    post('viraseo_start_serp', {keyword:kw}, r => {
        if (!r.success) { $('#vs-serp-progress').hide(); $(this).prop('disabled',false); toast(r.data,'err'); return; }
        pollSerp(r.data.analysis_id);
    });
});
function pollSerp(id) {
    const iv = setInterval(()=>{
        post('viraseo_serp_status', {analysis_id:id}, r => {
            if (!r.success) return;
            if (r.data.status==='completed') { clearInterval(iv); loadSerpResults(id); }
            else if (r.data.status==='failed') { clearInterval(iv); $('#vs-serp-progress').hide(); $('#vs-serp-start').prop('disabled',false); toast('خطا در تحلیل','err'); }
        });
    }, 4000);
}
function loadSerpResults(id) {
    post('viraseo_serp_results', {analysis_id:id}, r => {
        $('#vs-serp-progress').hide(); $('#vs-serp-start').prop('disabled',false);
        if (!r.success || r.data.status!=='completed') return;
        const d = r.data;
        $('#vs-serp-results').show();
        $('#vs-serp-stats').html(`<div class="vs-stat"><div class="vs-stat-icon"><span class="dashicons dashicons-editor-textcolor"></span></div><div><span class="vs-stat-num">${d.avg_words}</span><span class="vs-stat-label">میانگین کلمات</span></div></div><div class="vs-stat"><div class="vs-stat-icon green"><span class="dashicons dashicons-heading"></span></div><div><span class="vs-stat-num">${d.avg_headings}</span><span class="vs-stat-label">هدینگ</span></div></div><div class="vs-stat"><div class="vs-stat-icon cyan"><span class="dashicons dashicons-groups"></span></div><div><span class="vs-stat-num">${d.competitors.length}</span><span class="vs-stat-label">رقیب</span></div></div>`);
        const $t = $('#vs-serp-tbody').empty();
        d.competitors.forEach(c => { $t.append(`<tr><td>${c.pos}</td><td>${c.domain}</td><td>${c.title||'-'}</td><td>${c.words}</td><td>${c.h1}/${c.h2}/${c.h3}</td><td>${c.images}</td></tr>`); });
        const $l = $('#vs-lsi-tags').empty();
        (d.lsi||[]).forEach(w => $l.append(`<span class="vs-tag">${w}</span>`));
        const $g = $('#vs-gap-list').empty();
        (d.gap||[]).forEach(g => $g.append(`<li>${g}</li>`));
    });
}


// === INTERNAL LINKS ===
$(document).on('click', '#vs-scan-links', function(){
    const $s = $('#vs-scan-status').text('اسکن...');
    post('viraseo_trigger_scan', {}, r => {
        $s.text(r.success? r.data.message : 'خطا');
        if (r.success) { loadOrphans(); loadSuggestions(); }
    });
});
function loadOrphans() {
    post('viraseo_get_orphans', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-orphans-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">صفحه یتیمی یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $t.append(`<tr><td><a href="${o.url}" target="_blank">${o.title}</a></td><td>${o.type}</td><td>${o.inlinks}</td><td>${o.outlinks}</td><td><a href="${o.edit}" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>`);
        });
    });
}
function loadSuggestions() {
    post('viraseo_get_suggestions', {}, r => {
        if (!r.success) return;
        const $c = $('#vs-suggestions-list').empty();
        if (!r.data.rows.length) { $c.html('<div class="vs-empty">پیشنهادی نیست.</div>'); return; }
        r.data.rows.forEach(s => {
            $c.append(`<div class="vs-suggestion"><div class="vs-suggestion-score"><div class="vs-suggestion-score-fill" style="width:${s.score}%"></div></div><div class="vs-suggestion-link"><span class="dashicons dashicons-arrow-left-alt"></span><strong>${s.source}</strong> → <strong>${s.target}</strong></div><div>انکر: <span class="vs-suggestion-anchor">${s.anchor}</span> (${Math.round(s.score)}%)</div><div class="vs-row"><button class="vs-btn vs-btn-sm vs-btn-success vs-accept-link" data-id="${s.id}">✓ پذیرش</button><button class="vs-btn vs-btn-sm vs-btn-danger vs-reject-link" data-id="${s.id}">✗ رد</button></div></div>`);
        });
    });
}
$(document).on('click', '.vs-accept-link', function(){ post('viraseo_accept_link',{id:$(this).data('id')},()=>loadSuggestions()); });
$(document).on('click', '.vs-reject-link', function(){ post('viraseo_reject_link',{id:$(this).data('id')},()=>loadSuggestions()); });

// === BACKLINKS ===
function loadBacklinks() {
    post('viraseo_get_backlinks', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-bl-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="7" class="vs-empty">بک‌لینکی ثبت نشده.</td></tr>'); return; }
        r.data.rows.forEach(b => {
            $t.append(`<tr><td>${b.domain}</td><td>${b.anchor||'-'}</td><td>${b.type}</td><td>${b.da}</td><td>${b.cost}</td><td><span class="vs-badge vs-badge-${b.status==='live'?'green':b.status==='dead'?'red':'orange'}">${b.status}</span></td><td><button class="vs-btn vs-btn-sm vs-btn-danger vs-del-bl" data-id="${b.id}">×</button></td></tr>`);
        });
    });
}
$(document).on('click', '.vs-del-bl', function(){
    if (!confirm('حذف؟')) return;
    post('viraseo_del_backlink', {id:$(this).data('id')}, ()=>loadBacklinks());
});
$(document).on('submit', '#vs-bl-form', function(e){
    e.preventDefault();
    const d = {};
    $(this).serializeArray().forEach(f=>d[f.name]=f.value);
    d.dofollow = $('#vs-bl-dofollow').is(':checked') ? 1 : 0;
    post('viraseo_add_backlink', d, r => {
        if (r.success) { toast('ثبت شد','success'); loadBacklinks(); $(this)[0].reset(); }
        else toast(r.data,'err');
    });
});


// === DISAVOW ===
function loadDisavow() {
    post('viraseo_get_disavow', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-disavow-tbody').empty();
        r.data.rows.forEach(e => { $t.append(`<tr><td dir="ltr">${e.entry}</td><td>${e.type}</td><td>${e.reason||'-'}</td></tr>`); });
    });
}
$(document).on('click', '#vs-add-disavow', function(){
    post('viraseo_add_disavow', {entry:$('#vs-disavow-entry').val(), type:$('#vs-disavow-type').val(), reason:$('#vs-disavow-reason').val()}, r => {
        if (r.success) { $('#vs-disavow-entry').val(''); loadDisavow(); }
        else toast(r.data,'err');
    });
});
$(document).on('click', '#vs-gen-disavow', function(){
    post('viraseo_gen_disavow', {}, r => {
        if (r.success) { $('#vs-disavow-preview').show().find('pre').text(r.data.content); toast('فایل تولید شد','success'); }
        else toast(r.data,'err');
    });
});

// === TRAFFIC FORECAST ===
$(document).on('click', '#vs-fc-calc', function(){
    const $t = $('#vs-fc-tbody').html('<tr><td colspan="6" class="vs-empty">محاسبه...</td></tr>');
    post('viraseo_forecast', {target: $('#vs-fc-target').val()}, r => {
        if (!r.success) return;
        const $tb = $('#vs-fc-tbody').empty();
        $('#vs-fc-total').text('+' + r.data.total_growth);
        r.data.rows.forEach(f => {
            $tb.append(`<tr><td>${f.keyword}</td><td>${f.position}</td><td>${f.impressions}</td><td>${f.clicks}</td><td>${f.potential}</td><td style="color:var(--vs-success);font-weight:700">+${f.growth}</td></tr>`);
        });
    });
});

// === KEYWORD DISCOVERY ===
$(document).on('click', '#vs-disc-start', function(){
    const seed = $('#vs-disc-seed').val().trim();
    if (!seed) return;
    const $s = $('#vs-disc-status').text('جستجو شروع شد...');
    $(this).prop('disabled',true);
    post('viraseo_discover', {seed}, r => {
        if (!r.success) { $s.text(r.data); $(this).prop('disabled',false); return; }
        $s.text(r.data.message);
        window._vsDiscId = r.data.discovery_id;
        pollDiscovery();
    });
});
function pollDiscovery() {
    const iv = setInterval(()=>{
        post('viraseo_disc_ideas', {discovery_id: window._vsDiscId}, r => {
            if (!r.success) return;
            if (r.data.status==='completed') { clearInterval(iv); showIdeas(r.data); }
        });
    }, 3500);
}
function showIdeas(d) {
    $('#vs-disc-status').text('');
    $('#vs-disc-start').prop('disabled',false);
    $('#vs-disc-results').show();
    const $t = $('#vs-disc-tbody').empty();
    d.rows.forEach(i => {
        $t.append(`<tr><td><input type="checkbox" class="vs-disc-cb" value="${i.id}"></td><td>${i.keyword}</td><td><span class="vs-badge vs-badge-blue">${i.source}</span></td><td>${i.relevance}%</td><td>${i.question?'؟':''}</td></tr>`);
    });
}
$(document).on('change','#vs-disc-all',function(){ $('.vs-disc-cb').prop('checked',$(this).is(':checked')); updateBrief(); });
$(document).on('change','.vs-disc-cb', updateBrief);
function updateBrief(){ $('#vs-disc-brief').prop('disabled', !$('.vs-disc-cb:checked').length); }
$(document).on('click','#vs-disc-brief',function(){
    const ids=[]; $('.vs-disc-cb:checked').each(function(){ids.push($(this).val());});
    post('viraseo_disc_brief', {ids}, r => {
        if (r.success) toast(r.data.message + ' <a href="'+r.data.edit_url+'" target="_blank">ویرایش</a>','success');
        else toast(r.data,'err');
    });
});


// === WORKFLOW MANAGER ===
function loadWorkflows() {
    post('viraseo_wf_list', {}, r => {
        if (!r.success) return;
        const $g = $('#vs-wf-grid').empty();
        if (!r.data.workflows.length) { $g.html('<div class="vs-empty">ورکفلویی یافت نشد.</div>'); return; }
        r.data.workflows.forEach((w,i) => {
            $g.append(`<div class="vs-wf-card" data-idx="${i}">
                <div class="vs-wf-head"><span class="vs-wf-name">${w.name}</span><span class="vs-badge vs-badge-blue">${w.nodes} نود</span></div>
                <div class="vs-wf-meta">📄 ${w.filename} · ${w.size}</div>
                <div class="vs-wf-actions">
                    <button class="vs-btn vs-btn-sm vs-btn-secondary vs-wf-view" data-idx="${i}">مشاهده</button>
                    <button class="vs-btn vs-btn-sm vs-btn-primary vs-wf-config" data-fn="${w.filename}">⚙️ پیکربندی</button>
                    <button class="vs-btn vs-btn-sm vs-btn-secondary vs-wf-copy" data-idx="${i}">📋 کپی</button>
                    <button class="vs-btn vs-btn-sm vs-btn-secondary vs-wf-dl" data-idx="${i}">↓ دانلود</button>
                </div>
            </div>`);
        });
        window._vsWFs = r.data.workflows;
    });
}

$(document).on('click', '.vs-wf-view', function(){
    const w = window._vsWFs[$(this).data('idx')];
    $('#vs-wf-modal-title').text(w.name);
    $('#vs-wf-editor').val(JSON.stringify(JSON.parse(w.content),null,2)).prop('readonly',false);
    $('#vs-wf-save').data('fn', w.filename).show();
    $('#vs-wf-modal').show();
});
$(document).on('click', '.vs-wf-config', function(){
    const fn = $(this).data('fn');
    post('viraseo_wf_configure', {filename:fn}, r => {
        if (!r.success) { toast(r.data,'err'); return; }
        $('#vs-wf-modal-title').text('ورکفلو پیکربندی‌شده (آماده Import)');
        $('#vs-wf-editor').val(r.data.configured_json).prop('readonly',true);
        $('#vs-wf-save').hide();
        $('#vs-wf-modal').show();
        toast(r.data.message, 'success');
    });
});
$(document).on('click', '.vs-wf-copy', function(){
    const w = window._vsWFs[$(this).data('idx')];
    copyText(w.content);
    toast('JSON کپی شد','success');
});
$(document).on('click', '.vs-wf-dl', function(){
    const w = window._vsWFs[$(this).data('idx')];
    downloadFile(w.filename, w.content);
});
$(document).on('click', '#vs-wf-save', function(){
    const fn = $(this).data('fn');
    post('viraseo_wf_save', {filename:fn, content:$('#vs-wf-editor').val()}, r => {
        if (r.success) { toast(r.data.message,'success'); $('#vs-wf-modal').hide(); loadWorkflows(); }
        else toast(r.data,'err');
    });
});
$(document).on('click', '#vs-wf-copy-btn', function(){ copyText($('#vs-wf-editor').val()); toast('کپی شد','success'); });
$(document).on('click', '#vs-wf-dl-btn', function(){ downloadFile('workflow.json', $('#vs-wf-editor').val()); });

// Close modals
$(document).on('click', '.vs-modal-close, .vs-modal-bg', function(){ $(this).closest('.vs-modal').hide(); });

// === FACETED NAV ===
function loadFaceted() {
    post('viraseo_faceted_get', {}, r => {
        if (!r.success) return;
        const s = r.data;
        $('#vs-fac-enabled').prop('checked',s.enabled);
        $('#vs-fac-max').val(s.max_params);
        $('#vs-fac-filters').val(s.filters_text);
        $('#vs-fac-safe').val(s.safe_text);
        $('#vs-fac-prefix').val(s.prefix);
        $('#vs-fac-sort').prop('checked',s.noindex_sort);
    });
}
$(document).on('submit','#vs-faceted-form', function(e){
    e.preventDefault();
    const d = {};
    $(this).serializeArray().forEach(f=>d[f.name]=f.value);
    d.enabled = $('#vs-fac-enabled').is(':checked') ? 1 : '';
    d.noindex_sort = $('#vs-fac-sort').is(':checked') ? 1 : '';
    post('viraseo_faceted_save', d, r => {
        if (r.success) toast(r.data.message,'success');
        else toast(r.data,'err');
    });
});

// OOS
function loadOOS() {
    post('viraseo_get_oos', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-oos-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="4" class="vs-empty">محصول ناموجودی نیست.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $t.append(`<tr><td>${o.title}</td><td><span class="vs-badge vs-badge-${o.traffic?'green':'red'}">${o.traffic?'دارد ✓':'ندارد ✗'}</span></td><td>${o.action}</td><td>${o.date}</td></tr>`);
        });
    });
}


// === INIT ON PAGE LOAD ===
$(function(){
    // GSC page
    if ($('#vs-kw-tbody').length) { loadKeywords(); loadStriking(); loadCannibal(); }
    // Links page
    if ($('#vs-orphans-tbody').length) { loadOrphans(); loadSuggestions(); }
    // Backlinks page
    if ($('#vs-bl-tbody').length) { loadBacklinks(); loadDisavow(); }
    // Workflows page
    if ($('#vs-wf-grid').length) loadWorkflows();
    // WooCommerce page
    if ($('#vs-oos-tbody').length) { loadOOS(); loadFaceted(); }
});

// === UTILITIES ===
function copyText(t) {
    if (navigator.clipboard) { navigator.clipboard.writeText(t); return; }
    const $a = $('<textarea>').val(t).appendTo('body').select();
    document.execCommand('copy');
    $a.remove();
}
function downloadFile(name, content) {
    const blob = new Blob([content], {type:'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

})(jQuery);

/* Toast CSS injected via JS */
(function(){
    const style = document.createElement('style');
    style.textContent = `.vs-toast{position:fixed;bottom:24px;left:24px;padding:14px 24px;border-radius:10px;font-size:13px;z-index:99999;opacity:0;transform:translateY(10px);transition:all .3s;font-family:var(--vs-font);max-width:400px;direction:rtl;}.vs-toast.show{opacity:1;transform:none;}.vs-toast-success{background:#065f46;color:#6ee7b7;border:1px solid #10b981;}.vs-toast-err{background:#7f1d1d;color:#fca5a5;border:1px solid #ef4444;}.vs-toast-info{background:#1e3a5f;color:#7dd3fc;border:1px solid #0ea5e9;}`;
    document.head.appendChild(style);
})();
