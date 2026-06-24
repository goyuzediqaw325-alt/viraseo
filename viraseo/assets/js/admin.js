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
// Generic client-side pagination over already-rendered <tr> rows.
function vsRowPaginate($tbody, $pager, perPage) {
    perPage = perPage || 25;
    if (!$tbody.length || !$pager.length) return;
    const $rows = $tbody.children('tr').not('.vs-empty').not('.vs-onpage-detail').not('.vs-fc-detail');
    const total = $rows.length, pages = Math.ceil(total / perPage) || 1;
    function show(p) {
        p = Math.min(Math.max(1, p), pages);
        // collapse any open detail rows on page change
        $tbody.children('.vs-onpage-detail, .vs-fc-detail').hide();
        $rows.hide().slice((p-1)*perPage, p*perPage).show();
        let h = '';
        if (pages > 1) {
            if (p > 1) h += '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-cpage" data-p="'+(p-1)+'">‹ قبلی</button>';
            h += '<span class="vs-pager-info">صفحه '+p+' از '+pages+' ('+total+' مورد)</span>';
            if (p < pages) h += '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-cpage" data-p="'+(p+1)+'">بعدی ›</button>';
        }
        $pager.html(h).data('show', show);
    }
    show(1);
}
$(document).on('click', '.vs-cpager .vs-cpage', function(){
    const fn = $(this).closest('.vs-cpager').data('show');
    if (fn) { fn(parseInt($(this).data('p'),10)); $('html,body').animate({scrollTop: $(this).closest('table').offset().top - 80}, 200); }
});

// === TABS ===
$(document).on('click', '.vs-tab', function(e){
    e.preventDefault();
    const t = $(this).data('tab');
    const $wrap = $(this).closest('.vs-wrap');
    $(this).addClass('active').siblings().removeClass('active');
    $wrap.find('.vs-tab-panel').removeClass('active');
    // Support BOTH conventions: id="panel-X" AND data-panel="X"
    var $panel = $('#panel-' + t);
    if (!$panel.length) $panel = $wrap.find('.vs-tab-panel[data-panel="' + t + '"]');
    $panel.addClass('active');
});

// === ACTION PLAN (dashboard) ===
$(function(){
    if (!$('#vs-action-list').length) return;
    post('viraseo_action_plan', {}, r => {
        if (!r.success) { $('#vs-action-list').html('<div class="vs-empty">'+(r.data||'خطا')+'</div>'); return; }
        const sc = r.data.score;
        const scColor = sc >= 75 ? '#10b981' : (sc >= 45 ? '#f59e0b' : '#ef4444');
        $('#vs-health').html('<span class="vs-health-label">سلامت سئو</span><span class="vs-health-score" style="color:'+scColor+'">'+sc+'/۱۰۰</span>');
        const $l = $('#vs-action-list').empty();
        if (r.data.done) { $l.html('<div class="vs-empty">🎉 عالی! در حال حاضر کار فوری مهمی نیست. داده‌ها را به‌روز نگه دارید.</div>'); return; }
        const sevColor = {critical:'red', high:'orange', warn:'orange', normal:'blue'};
        r.data.tasks.forEach((t, i) => {
            $l.append('<div class="vs-task vs-task-'+(sevColor[t.severity]||'blue')+'">'
                + '<div class="vs-task-num">'+(i+1)+'</div>'
                + '<div class="vs-task-icon">'+t.icon+'</div>'
                + '<div class="vs-task-body"><div class="vs-task-title">'+t.title+'</div><div class="vs-task-desc">'+t.desc+'</div></div>'
                + '<a class="vs-btn vs-btn-sm vs-btn-primary" href="'+t.url+'">'+t.btn+' ›</a>'
                + '</div>');
        });
    });
});
// Action-plan customization: choose which task categories appear
$(document).on('click', '#vs-ap-gear', function(){
    const $p = $('#vs-ap-prefs');
    if ($p.is(':visible')) { $p.slideUp(150); return; }
    post('viraseo_ap_prefs', {}, r => {
        if (!r.success) return;
        const hidden = r.data.hidden || [];
        const $list = $('#vs-ap-prefs-list').empty();
        Object.keys(r.data.categories).forEach(k => {
            const checked = hidden.indexOf(k) === -1 ? 'checked' : '';
            $list.append('<label class="vs-ap-pref"><input type="checkbox" class="vs-ap-cb" value="'+k+'" '+checked+'> '+r.data.categories[k]+'</label>');
        });
        $p.slideDown(150);
    });
});
$(document).on('click', '#vs-ap-save', function(){
    const hidden = [];
    $('#vs-ap-prefs-list .vs-ap-cb').each(function(){ if (!$(this).is(':checked')) hidden.push($(this).val()); });
    const $b = $(this).prop('disabled', true);
    post('viraseo_ap_prefs', {save:1, hidden:hidden}, r => {
        $b.prop('disabled', false);
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        toast('ذخیره شد. در حال به‌روزرسانی برنامه...','success');
        $('#vs-ap-prefs').slideUp(150);
        // Re-render the action plan with new prefs
        post('viraseo_action_plan', {}, rr => {
            if (!rr.success) return;
            const $l = $('#vs-action-list').empty();
            if (rr.data.done) { $l.html('<div class="vs-empty">🎉 عالی! در حال حاضر کار فوری مهمی نیست.</div>'); return; }
            const sevColor = {critical:'red', high:'orange', warn:'orange', normal:'blue'};
            const sc = rr.data.score, scColor = sc >= 75 ? '#10b981' : (sc >= 45 ? '#f59e0b' : '#ef4444');
            $('#vs-health').html('<span class="vs-health-label">سلامت سئو</span><span class="vs-health-score" style="color:'+scColor+'">'+sc+'/۱۰۰</span>');
            rr.data.tasks.forEach((t, i) => {
                $l.append('<div class="vs-task vs-task-'+(sevColor[t.severity]||'blue')+'">'
                    + '<div class="vs-task-num">'+(i+1)+'</div><div class="vs-task-icon">'+t.icon+'</div>'
                    + '<div class="vs-task-body"><div class="vs-task-title">'+t.title+'</div><div class="vs-task-desc">'+t.desc+'</div></div>'
                    + '<a class="vs-btn vs-btn-sm vs-btn-primary" href="'+t.url+'">'+t.btn+' ›</a></div>');
            });
        });
    });
});

// === AI SAVED OUTPUTS ===
function loadAiSaved() {
    if (!$('#vs-saved-list').length) return;
    $('#vs-saved-list').html('<div class="vs-empty">در حال بارگذاری...</div>');
    post('viraseo_ai_saved', {}, r => {
        const $l = $('#vs-saved-list').empty();
        if (!r.success || !r.data.rows.length) { $l.html('<div class="vs-empty">موردی ذخیره نشده.</div>'); return; }
        r.data.rows.forEach(o => {
            $l.append('<div class="vs-ai-output" style="margin-bottom:12px"><div class="vs-ai-head">'+o.title+' <span class="vs-badge vs-badge-blue">'+o.kind+'</span> <span class="vs-hint">'+o.date+'</span> <button class="vs-btn vs-btn-sm vs-btn-danger vs-saved-del" data-id="'+o.id+'" style="margin-right:auto">حذف</button></div><div class="vs-ai-body">'+o.content+'</div></div>');
        });
    });
}
$(document).on('click', '#vs-saved-reload', loadAiSaved);
$(document).on('click', '.vs-saved-del', function(){
    if (!confirm('حذف این مورد؟')) return;
    post('viraseo_ai_saved_delete', {id:$(this).data('id')}, ()=>loadAiSaved());
});

// === KEYWORD STRATEGY / PLAN ===
function loadPlan() {
    if (!$('#vs-stg-list').length) return;
    $('#vs-stg-list').html('<div class="vs-empty">در حال بارگذاری...</div>');
    post('viraseo_plan_list', {}, r => {
        const $l = $('#vs-stg-list').empty();
        if (!r.success) { $l.html('<div class="vs-empty">'+(r.data||'خطا')+'</div>'); return; }
        if (!r.data.clusters.length) { $l.html('<div class="vs-empty">هنوز کلمه‌ای در برنامه نیست. با AI یا دستی اضافه کنید.</div>'); return; }
        r.data.clusters.forEach(c => {
            let rows = c.items.map(it => {
                const stColor = it.status==='done'?'green':(it.status==='in_progress'?'orange':'blue');
                const postCell = it.post ? '<a href="'+it.post.edit+'" target="_blank">'+(it.post.title||'پیش‌نویس')+'</a>' : '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-plan-draft" data-id="'+it.id+'">📝 ساخت پیش‌نویس</button>';
                return '<tr><td><strong>'+it.keyword+'</strong></td><td>'+it.intent+'</td>'
                    + '<td><select class="vs-input vs-plan-status" data-id="'+it.id+'"><option value="planned"'+(it.status==='planned'?' selected':'')+'>برنامه‌ریزی‌شده</option><option value="in_progress"'+(it.status==='in_progress'?' selected':'')+'>در حال تولید</option><option value="done"'+(it.status==='done'?' selected':'')+'>انجام‌شده</option></select></td>'
                    + '<td>'+postCell+'</td>'
                    + '<td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-plan-ai" data-kw="'+escAttr(it.keyword)+'" title="طرح محتوا با AI">🤖</button> <button class="vs-btn vs-btn-sm vs-btn-danger vs-plan-del" data-id="'+it.id+'">×</button></td></tr>';
            }).join('');
            $l.append('<div class="vs-cluster"><div class="vs-cluster-head"><span class="vs-badge vs-badge-blue">'+c.cluster+'</span> <span class="vs-cluster-count">'+c.count+' کلمه</span></div>'
                + '<table class="vs-table"><thead><tr><th>کلمه</th><th>هدف</th><th>وضعیت</th><th>محتوا</th><th>عملیات</th></tr></thead><tbody>'+rows+'</tbody></table>'
                + '<div class="vs-plan-ai-box"></div></div>');
        });
    });
}
$(document).on('click', '#vs-stg-reload', loadPlan);
$(document).on('click', '#vs-stg-add', function(){
    const kws = $('#vs-stg-kws').val().trim();
    if (!kws) { toast('کلمات را وارد کنید.','err'); return; }
    post('viraseo_plan_add', {keywords:kws, cluster:$('#vs-stg-cluster').val(), intent:$('#vs-stg-intent').val()}, r => {
        if (r.success) { toast(r.data.message,'success'); $('#vs-stg-kws').val(''); loadPlan(); } else toast(r.data,'err');
    });
});
$(document).on('click', '#vs-stg-ai', function(){
    const seed = $('#vs-stg-seed').val().trim();
    if (!seed) { toast('موضوع را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-stg-ai-status').text('🤖 در حال ساخت استراتژی... (تا یک دقیقه)');
    post('viraseo_plan_ai', {seed:seed, business:$('#vs-stg-biz').val()}, r => {
        $b.prop('disabled', false);
        $('#vs-stg-ai-status').text(r.success ? r.data.message : (r.data||'خطا'));
        if (r.success) { toast(r.data.message,'success'); loadPlan(); } else toast(r.data,'err');
    });
});
$(document).on('change', '.vs-plan-status', function(){
    post('viraseo_plan_update', {id:$(this).data('id'), field:'status', value:$(this).val()}, ()=>{});
});
$(document).on('click', '.vs-plan-del', function(){
    if (!confirm('حذف این کلمه؟')) return;
    post('viraseo_plan_delete', {id:$(this).data('id')}, ()=>loadPlan());
});
$(document).on('click', '.vs-plan-draft', function(){
    const $b = $(this).prop('disabled', true).text('...');
    const withAi = confirm('محتوای اولیه با هوش مصنوعی هم ساخته شود؟ (OK = بله)');
    post('viraseo_plan_draft', {id:$(this).data('id'), with_ai: withAi?1:0}, r => {
        if (r.success) { toast('پیش‌نویس ساخته شد','success'); loadPlan(); window.open(r.data.edit, '_blank'); }
        else { toast(r.data,'err'); $b.prop('disabled',false).text('📝 ساخت پیش‌نویس'); }
    });
});
$(document).on('click', '.vs-plan-ai', function(){
    const $box = $(this).closest('.vs-cluster').find('.vs-plan-ai-box').html('<div class="vs-empty">🤖 در حال تهیه طرح محتوا...</div>');
    post('viraseo_ai_content', {keyword:$(this).data('kw'), mode:'outline'}, r => {
        if (!r.success) { $box.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $box.html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 طرح محتوا <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div><button class="vs-btn vs-btn-sm vs-btn-success vs-ai-save" data-kind="content">💾 ذخیره</button></div>');
    });
});

// === INDEX STATUS (GSC URL Inspection) ===
function vsIdxRow(o) {
    const badge = o.indexed ? '<span class="vs-badge vs-badge-green">ایندکس‌شده</span>' : '<span class="vs-badge vs-badge-red">ایندکس نشده</span>';
    const probs = (o.problems && o.problems.length) ? o.problems.map(p=>'<span class="vs-badge vs-badge-orange">'+p+'</span>').join(' ') : '<span class="vs-chk-ok">بدون مشکل</span>';
    const title = o.title ? '<a href="'+(o.edit||o.url)+'" target="_blank">'+o.title+'</a>' : '<span dir="ltr">'+o.url+'</span>';
    const reqBtn = '<button class="vs-btn vs-btn-sm vs-btn-success vs-idx-req" data-url="'+escAttr(o.url)+'">📤 درخواست ایندکس</button>';
    return '<tr><td>'+title+'</td><td>'+badge+'</td><td>'+o.coverage+'</td><td>'+o.last_crawl+'</td><td>'+probs+'<div style="margin-top:6px">'+reqBtn+'</div></td></tr>';
}
$(document).on('click', '.vs-idx-req', function(){
    const url = $(this).data('url');
    const $b = $(this).prop('disabled', true).text('در حال ارسال...');
    post('viraseo_index_request', {url:url}, r => {
        $b.prop('disabled', false).text('📤 درخواست ایندکس');
        toast(r.success ? r.data.message : (r.data||'خطا'), r.success ? 'success' : 'err');
    });
});
$(document).on('click', '#vs-idx-request-one', function(){
    const url = $('#vs-idx-url').val().trim();
    if (!url) { toast('آدرس را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true).text('در حال ارسال...');
    post('viraseo_index_request', {url:url}, r => {
        $b.prop('disabled', false).text('📤 درخواست ایندکس');
        toast(r.success ? r.data.message : (r.data||'خطا'), r.success ? 'success' : 'err');
    });
});
$(document).on('click', '#vs-idx-one', function(){
    const url = $('#vs-idx-url').val().trim();
    if (!url) { toast('آدرس را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-idx-one-box').html('<div class="vs-empty">در حال بررسی در سرچ کنسول...</div>');
    post('viraseo_index_inspect', {url:url}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-idx-one-box').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        $('#vs-idx-one-box').html('<table class="vs-table"><thead><tr><th>صفحه</th><th>وضعیت</th><th>پوشش</th><th>آخرین خزش</th><th>مشکلات</th></tr></thead><tbody>'+vsIdxRow(r.data)+'</tbody></table>');
    });
});
$(document).on('click', '#vs-idx-batch', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-idx-tbody').html('<tr><td colspan="5" class="vs-empty">در حال بررسی دسته‌ای (ممکن است کمی طول بکشد)...</td></tr>');
    post('viraseo_index_batch', {limit: $('#vs-idx-limit').val()||15}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-idx-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="5" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        $('#vs-idx-summary').text('ایندکس‌شده: '+r.data.indexed+' از '+r.data.total+' · دارای مشکل: '+r.data.issues);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">موردی نیست.</td></tr>'); return; }
        r.data.rows.forEach(o => $t.append(vsIdxRow(o)));
    });
});

// === AI TOOLS PAGE ===
function vsAiBox(sel, r, kind) {
    if (!r.success) { $(sel).html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
    const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
    $(sel).html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 خروجی هوش مصنوعی <span class="vs-hint">هزینه: $'+(r.data.cost||0)+' · '+(r.data.tokens||0)+' توکن</span></div><div class="vs-ai-body">'+html+'</div><button class="vs-btn vs-btn-sm vs-btn-secondary vs-ai-copy">📋 کپی</button> <button class="vs-btn vs-btn-sm vs-btn-success vs-ai-save" data-kind="'+(kind||'general')+'">💾 ذخیره</button></div>');
}
$(document).on('click', '.vs-ai-save', function(){
    const $out = $(this).closest('.vs-ai-output');
    const content = $out.find('.vs-ai-body').html();
    const $b = $(this).prop('disabled', true).text('در حال ذخیره...');
    post('viraseo_ai_save', {content: content, kind: $(this).data('kind')||'general'}, r => {
        $b.prop('disabled', false).text('💾 ذخیره');
        toast(r.success ? (r.data.message||'ذخیره شد') : (r.data||'خطا'), r.success?'success':'err');
    });
});
$(document).on('click', '#vs-aikw-go', function(){
    const seed = $('#vs-aikw-seed').val().trim();
    if (!seed) { toast('موضوع را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-aikw-box').html('<div class="vs-empty">🤖 در حال تحقیق کلمات...</div>');
    post('viraseo_ai_keywords', {seed:seed, business:$('#vs-aikw-biz').val()}, r => { $b.prop('disabled',false); vsAiBox('#vs-aikw-box', r, 'keywords'); });
});
$(document).on('click', '#vs-airev-go', function(){
    const pid = $('#vs-airev-post').val();
    if (!pid) { toast('صفحه را انتخاب کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-airev-box').html('<div class="vs-empty">🤖 در حال بازبینی محتوا...</div>');
    post('viraseo_ai_review', {post_id:pid}, r => { $b.prop('disabled',false); vsAiBox('#vs-airev-box', r, 'review'); });
});
$(document).on('click', '#vs-aifaq-go', function(){
    const pid = $('#vs-aifaq-post').val();
    const kw = $('#vs-aifaq-kw').val().trim();
    if (!pid && !kw) { toast('صفحه یا کلمه را مشخص کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-aifaq-box').html('<div class="vs-empty">🤖 در حال تولید FAQ...</div>');
    post('viraseo_ai_faq', {post_id:pid, keyword:kw}, r => { $b.prop('disabled',false); vsAiBox('#vs-aifaq-box', r, 'faq'); });
});

// === SETTINGS: AI models (OpenRouter) ===
$(document).on('click', '#vs-ai-load-models', function(){
    const $b = $(this).prop('disabled', true).text('در حال بارگذاری...');
    post('viraseo_ai_models', {force:1}, r => {
        $b.prop('disabled', false).text('بارگذاری مدل‌ها + هزینه');
        if (!r.success) { toast(r.data||'خطا', 'err'); return; }
        const cur = r.data.current;
        const $sel = $('#vs-ai-model').empty();
        r.data.models.forEach(m => {
            const cost = m.free ? 'رایگان' : ('$'+m.in+' / $'+m.out+' در میلیون توکن');
            $sel.append('<option value="'+m.id+'" data-cost="'+cost+'"'+(m.id===cur?' selected':'')+'>'+m.name+' — '+cost+'</option>');
        });
        $('#vs-ai-model-cost').text('قیمت‌ها برای ورودی/خروجی هر یک میلیون توکن است.');
        toast(r.data.models.length+' مدل بارگذاری شد','success');
    });
});
$(document).on('change', '#vs-ai-model', function(){
    $('#vs-ai-model-cost').text('هزینه: ' + ($(this).find(':selected').data('cost')||'-'));
});

// === SETTINGS: Test n8n ===
$(document).on('click', '#vs-test-n8n', function(){
    const $s = $('#vs-n8n-status').text('...').removeClass('ok err');
    post('viraseo_test_n8n', {}, r => {
        if (r.success) $s.text(r.data).addClass('ok');
        else $s.text(r.data).addClass('err');
    });
});
// === SETTINGS: Test cURL proxy ===
$(document).on('click', '#vs-test-proxy', function(){
    const $s = $('#vs-proxy-result').text('در حال تست...');
    post('viraseo_test_proxy', {}, r => {
        $s.text(r.success ? r.data : r.data).css('color', r.success ? '#10b981' : '#ef4444');
    });
});


// === GSC OAuth ===
$(document).on('click', '#vs-gsc-connect', function(){
    const $btn = $(this).prop('disabled', true).text('در حال اتصال...');
    post('viraseo_gsc_connect', {}, r => {
        if (r.success && r.data.redirect_url) {
            // Redirect browser to Google consent page
            window.location.href = r.data.redirect_url;
        } else {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-google"></span> اتصال به سرچ کنسول گوگل');
            toast(r.data || 'خطا در اتصال', 'err');
        }
    });
});
$(document).on('click', '#vs-gsc-disconnect', function(){
    if (!confirm('قطع اتصال؟')) return;
    post('viraseo_gsc_disconnect', {}, ()=> location.reload());
});
$(document).on('click', '#vs-gsc-sync', function(){
    const $b = $(this).prop('disabled',true);
    const $s = $('#vs-sync-status').text('در حال همگام‌سازی...');
    const site = $('#vs-gsc-site').val();
    const days = parseInt($('#vs-gsc-days').val(), 10) || 28;
    post('viraseo_gsc_fetch', {days: days, site_url: site}, r => {
        $b.prop('disabled',false);
        if (r.success) { $s.text(r.data.message); toast(r.data.message,'success'); loadKeywords(); loadStriking(); loadDaily(); }
        else { $s.text(r.data||'خطا'); toast(r.data||'خطا','err'); }
    });
});

// Load GSC sites dropdown on page load
if ($('#vs-gsc-site').length) {
    var siteTimeout = setTimeout(function(){
        $('#vs-gsc-site').empty().append('<option value="">خطا در بارگذاری — از «دریافت داده‌ها» استفاده کنید</option>');
    }, 8000);
    
    post('viraseo_gsc_sites', {}, function(r) {
        clearTimeout(siteTimeout);
        var $sel = $('#vs-gsc-site').empty();
        if (r.success && r.data.sites && r.data.sites.length) {
            r.data.sites.forEach(function(s){ $sel.append('<option value="'+s+'">'+s+'</option>'); });
        } else {
            $sel.append('<option value="' + window.location.hostname + '">'+window.location.hostname+' (پیش‌فرض)</option>');
        }
    });
}

// === KEYWORDS ===
var vsKwSort = {orderby: 'impressions', order: 'desc'};
var vsKwPage = 1;
function loadKeywords(search, page) {
    if (typeof page === 'number') vsKwPage = page;
    post('viraseo_get_keywords', {search: (typeof search==='string'?search:$('#vs-kw-search').val())||'', page: vsKwPage, orderby: vsKwSort.orderby, order: vsKwSort.order}, r => {
        if (!r.success) return;
        // Overview totals
        if (r.data.totals) {
            $('#vs-gsc-overview').show();
            $('#vs-gsc-t-clicks').text(r.data.totals.clicks);
            $('#vs-gsc-t-impr').text(r.data.totals.impressions);
            $('#vs-gsc-t-pos').text(r.data.totals.avg_position);
            $('#vs-gsc-t-count').text(r.data.totals.count);
        }
        // Sort arrows
        $('.vs-sort .vs-sort-ar').text('');
        $('.vs-sort[data-sort="'+vsKwSort.orderby+'"] .vs-sort-ar').text(vsKwSort.order === 'asc' ? '▲' : '▼');
        const $t = $('#vs-kw-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">داده‌ای یافت نشد.</td></tr>'); $('#vs-kw-pager').empty(); return; }
        r.data.rows.forEach(k => {
            $t.append(`<tr>
                <td>${k.keyword}${k.is_striking?' <span class="vs-badge vs-badge-orange">⭐</span>':''}</td>
                <td>${k.clicks}</td><td>${k.impressions}</td><td>${k.ctr}</td><td>${k.position}</td>
                <td><a href="${k.page_url}" target="_blank" class="vs-btn vs-btn-sm vs-btn-secondary">↗</a></td>
            </tr>`);
        });
        // Pager
        const pages = r.data.pages || 1, cur = r.data.page || vsKwPage;
        const $p = $('#vs-kw-pager').empty();
        if (pages > 1) {
            if (cur > 1) $p.append('<button class="vs-btn vs-btn-sm vs-btn-secondary vs-kw-page" data-p="'+(cur-1)+'">‹ قبلی</button>');
            $p.append('<span class="vs-pager-info">صفحه '+cur+' از '+pages+' (مجموع '+(r.data.total||0)+' کلمه)</span>');
            if (cur < pages) $p.append('<button class="vs-btn vs-btn-sm vs-btn-secondary vs-kw-page" data-p="'+(cur+1)+'">بعدی ›</button>');
        }
    });
}
$(document).on('click', '.vs-kw-page', function(){ loadKeywords(undefined, parseInt($(this).data('p'),10)||1); $('html,body').animate({scrollTop:0},200); });
$(document).on('click', '.vs-sort', function(){
    const col = $(this).data('sort');
    if (vsKwSort.orderby === col) vsKwSort.order = (vsKwSort.order === 'asc' ? 'desc' : 'asc');
    else { vsKwSort.orderby = col; vsKwSort.order = 'desc'; }
    vsKwPage = 1;
    loadKeywords();
});
// Auto-assign target keywords to pages from GSC top queries
$(document).on('click', '#vs-assign-targets', function(){
    if (!confirm('برای صفحاتی که کلمه هدف ندارند، پرکلیک‌ترین کوئری سرچ کنسول به‌عنوان کلمه هدف تنظیم می‌شود. ادامه؟')) return;
    const $b = $(this).prop('disabled', true);
    $('#vs-assign-status').text('در حال تخصیص...');
    post('viraseo_suggest_targets_gsc', {}, r => {
        $b.prop('disabled', false);
        $('#vs-assign-status').text(r.success ? r.data.message : (r.data||'خطا'));
        toast(r.success ? r.data.message : (r.data||'خطا'), r.success ? 'success' : 'err');
    });
});
// GSC daily timeline
function loadDaily() {
    if (!$('#vs-gsc-daily-tbody').length) return;
    post('viraseo_gsc_daily', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-gsc-daily-tbody').empty();
        if (!r.data.rows || !r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">داده‌ای نیست. ابتدا همگام‌سازی کنید.</td></tr>'); return; }
        r.data.rows.forEach(d => {
            $t.append(`<tr><td>${d.date}</td><td>${d.clicks}</td><td>${d.impressions}</td><td>${d.ctr}</td><td>${d.position}</td></tr>`);
        });
    });
}
$(document).on('keyup', '#vs-kw-search', function(){ vsKwPage = 1; loadKeywords(); });
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
                <div class="vs-conflict-foot"><span>💡 ${c.recommendation}</span><button class="vs-btn vs-btn-sm vs-btn-secondary vs-cannibal-ai" data-kw="${c.keyword}" data-u1="${c.page_1.url}" data-u2="${c.page_2.url}" data-p1="${c.page_1.pos}" data-p2="${c.page_2.pos}">🤖 راه‌حل AI</button><button class="vs-btn vs-btn-sm vs-btn-success vs-resolve" data-id="${c.id}">حل شد</button></div>
                <div class="vs-cannibal-ai-box"></div>
            </div>`);
        });
    });
}
$(document).on('click', '.vs-resolve', function(){
    post('viraseo_resolve_cannibal', {id:$(this).data('id'), status:'resolved'}, ()=> loadCannibal());
});
$(document).on('click', '.vs-cannibal-ai', function(){
    const $box = $(this).closest('.vs-conflict').find('.vs-cannibal-ai-box').html('<div class="vs-empty">🤖 در حال تحلیل راه‌حل...</div>');
    post('viraseo_ai_cannibal', {keyword:$(this).data('kw'), url1:$(this).data('u1'), url2:$(this).data('u2'), pos1:$(this).data('p1'), pos2:$(this).data('p2')}, r => {
        if (!r.success) { $box.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $box.html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 راه‌حل هوش مصنوعی <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div></div>');
    });
});

// === SERP ANALYSIS ===
$(document).on('click', '#vs-serp-start', function(){
    const kw = $('#vs-serp-kw').val().trim();
    if (!kw) { toast('کلمه کلیدی وارد کنید.', 'err'); return; }
    $(this).prop('disabled',true);
    $('#vs-serp-progress').show();
    $('#vs-serp-error').hide();
    $('#vs-serp-results').hide();
    post('viraseo_start_serp', {keyword:kw, post_id: window._vsSerpPost||0}, r => {
        if (!r.success) {
            $('#vs-serp-progress').hide();
            $('#vs-serp-start').prop('disabled',false);
            $('#vs-serp-error').show();
            $('#vs-serp-error-text').html(r.data);
            toast(r.data,'err');
            return;
        }
        toast(r.data.message, 'success');
        pollSerp(r.data.analysis_id);
    });
});
function pollSerp(id) {
    var attempts = 0;
    const maxAttempts = 15; // 15 * 4s = 60 seconds max
    const iv = setInterval(()=>{
        attempts++;
        post('viraseo_serp_status', {analysis_id:id}, r => {
            if (!r.success) return;
            if (r.data.status==='completed') { clearInterval(iv); loadSerpResults(id); }
            else if (r.data.status==='failed') { clearInterval(iv); $('#vs-serp-progress').hide(); $('#vs-serp-start').prop('disabled',false); $('#vs-serp-error').show(); $('#vs-serp-error-text').html('❌ تحلیل ناموفق بود. ممکنه n8n در اجرای ورکفلو خطا داده باشه. لاگ n8n رو بررسی کنید.'); }
            else if (attempts >= maxAttempts) {
                clearInterval(iv);
                $('#vs-serp-progress').hide();
                $('#vs-serp-start').prop('disabled',false);
                $('#vs-serp-error').show();
                $('#vs-serp-error-text').html('⏱️ Timeout — بعد از ۶۰ ثانیه هنوز نتیجه‌ای از n8n دریافت نشد.<br><br>دلایل ممکن:<br>• ورکفلو n8n اجرا شده ولی callback URL اشتباهه<br>• n8n نمی‌تونه به <code>' + window.VS.rest + 'serp-results</code> دسترسی پیدا کنه<br>• Secret خالیه (در تنظیمات مقدار Secret Webhook رو پر کنید)<br><br>REST URL: <code>' + window.VS.rest + '</code>');
            }
        });
    }, 4000);
}
function loadSerpHistory() {
    if (!$('#vs-serp-history').length) return;
    post('viraseo_serp_history', {}, r => {
        if (!r.success) return;
        const $h = $('#vs-serp-history').empty();
        if (!r.data.rows.length) { $h.html('<span class="vs-empty">هنوز تحلیلی انجام نشده.</span>'); return; }
        r.data.rows.forEach(a => {
            const badge = a.intent ? '<span class="vs-badge vs-badge-blue">'+a.intent+'</span>' : '';
            const cls = a.status === 'completed' ? 'vs-hist-done' : 'vs-hist-pending';
            $h.append('<button class="vs-hist-item '+cls+'" data-id="'+a.id+'" data-status="'+a.status+'"><strong>'+a.keyword+'</strong> '+badge+' <small>'+a.date+'</small></button>');
        });
    });
}
$(document).on('click', '.vs-hist-item', function(){
    const id = $(this).data('id');
    if ($(this).data('status') !== 'completed') { toast('این تحلیل کامل نشده است.','info'); return; }
    $('#vs-serp-error').hide();
    loadSerpResults(id);
    $('html,body').animate({scrollTop: $('#vs-serp-results').offset() ? $('#vs-serp-results').offset().top - 60 : 0}, 300);
});
function loadSerpResults(id) {
    window._vsSerpId = id;
    post('viraseo_serp_results', {analysis_id:id}, r => {
        $('#vs-serp-progress').hide(); $('#vs-serp-start').prop('disabled',false);
        if (!r.success || r.data.status!=='completed') return;
        const d = r.data;
        // If n8n returned an error or no competitors, show a clear reason instead of an empty table
        if (d.error || !d.competitors || d.competitors.length === 0) {
            $('#vs-serp-results').hide();
            $('#vs-serp-error').show();
            var reason = d.error
                ? ('❌ خطای n8n/Serper: <code>' + d.error + '</code>')
                : '⚠️ هیچ نتیجه‌ای برگردانده نشد.';
            $('#vs-serp-error-text').html(
                reason +
                '<br><br>🔑 برای تحلیل SERP باید کلید رایگان Serper.dev را در تنظیمات افزونه وارد کنید:' +
                '<br>۱. به <a href="https://serper.dev" target="_blank">serper.dev</a> بروید و ثبت‌نام کنید (۲۵۰۰ جستجوی رایگان).' +
                '<br>۲. کلید API را کپی کنید.' +
                '<br>۳. در «تنظیمات» افزونه، فیلد «کلید Serper API» را پر کرده و ذخیره کنید.' +
                '<br>۴. ورکفلو <code>01-serp-analyzer.json</code> را دوباره در n8n Import و Active کنید.' +
                (d.debug ? ('<br><br><small>اطلاعات فنی: ' + d.debug + '</small>') : '')
            );
            return;
        }
        $('#vs-serp-results').show();
        // Search intent
        if (d.intent && d.intent.dominant) {
            $('#vs-serp-intent').show();
            const it = d.intent;
            const icon = it.dominant === 'product' ? '🛒' : (it.dominant === 'article' ? '📝' : '🛠️');
            $('#vs-intent-body').html(
                '<div style="font-size:18px;font-weight:800;margin-bottom:10px">'+icon+' '+it.label+'</div>'
                + '<div class="vs-intent-bars">'
                +   '<div class="vs-intent-bar"><span>📝 مقاله‌ای</span><div class="vs-intent-track"><div class="vs-intent-fill" style="width:'+it.dist.article+'%;background:#0ea5e9"></div></div><b>'+it.dist.article+'%</b></div>'
                +   '<div class="vs-intent-bar"><span>🛒 محصول</span><div class="vs-intent-track"><div class="vs-intent-fill" style="width:'+it.dist.product+'%;background:#10b981"></div></div><b>'+it.dist.product+'%</b></div>'
                +   '<div class="vs-intent-bar"><span>🛠️ خدماتی</span><div class="vs-intent-track"><div class="vs-intent-fill" style="width:'+it.dist.service+'%;background:#f59e0b"></div></div><b>'+it.dist.service+'%</b></div>'
                + '</div>'
                + '<div class="vs-alert vs-alert-info" style="margin-top:12px"><span class="dashicons dashicons-lightbulb"></span><p>'+it.recommendation+'</p></div>'
            );
        } else { $('#vs-serp-intent').hide(); }
        if (d.saved_for_post) toast('✅ نتیجه و نوع صفحه برای کلمه هدف این صفحه ذخیره شد.', 'success');
        loadSerpHistory();
        $('#vs-serp-stats').html(`<div class="vs-stat"><div class="vs-stat-icon"><span class="dashicons dashicons-editor-textcolor"></span></div><div><span class="vs-stat-num">${d.avg_words}</span><span class="vs-stat-label">میانگین کلمات</span></div></div><div class="vs-stat"><div class="vs-stat-icon green"><span class="dashicons dashicons-heading"></span></div><div><span class="vs-stat-num">${d.avg_headings}</span><span class="vs-stat-label">هدینگ</span></div></div><div class="vs-stat"><div class="vs-stat-icon cyan"><span class="dashicons dashicons-groups"></span></div><div><span class="vs-stat-num">${d.competitors.length}</span><span class="vs-stat-label">رقیب</span></div></div>`);
        const $t = $('#vs-serp-tbody').empty();
        d.competitors.forEach(c => { $t.append(`<tr class="vs-serp-row" data-url="${c.url}" title="برای تحلیل دقیق این صفحه کلیک کنید"><td>${c.pos}</td><td>${c.domain}</td><td>${c.title||'-'}</td><td class="vs-c-words">${c.words>0?c.words:'<span class="vs-snippet-note">— (دکمه آنالیز دقیق)</span>'}</td><td class="vs-c-head">${c.h1}/${c.h2}/${c.h3}</td><td class="vs-c-img">${c.images||'-'}</td></tr>`); });
        const $l = $('#vs-lsi-tags').empty();
        (d.lsi||[]).forEach(w => $l.append(`<span class="vs-tag">${w}</span>`));
        const $g = $('#vs-gap-list').empty();
        (d.gap||[]).forEach(g => $g.append(`<li>${g}</li>`));
    });
}


// === SERP: AI competitor-beating strategy ===
function vsAiRender(boxSel, r, kind) {
    const html = (r.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
    $(boxSel).show().html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 خروجی هوش مصنوعی <span class="vs-hint">هزینه تقریبی: $'+(r.cost||0)+' · '+(r.tokens||0)+' توکن</span></div><div class="vs-ai-body">'+html+'</div><button class="vs-btn vs-btn-sm vs-btn-secondary vs-ai-copy">📋 کپی</button> <button class="vs-btn vs-btn-sm vs-btn-success vs-ai-save" data-kind="'+(kind||'general')+'">💾 ذخیره</button></div>');
}
$(document).on('click', '#vs-serp-ai', function(){
    if (!window._vsSerpId) { toast('ابتدا یک تحلیل را باز کنید.','err'); return; }
    const $b = $(this).prop('disabled', true).text('🤖 در حال تحلیل با AI...');
    $('#vs-serp-ai-box').show().html('<div class="vs-empty">هوش مصنوعی در حال تدوین استراتژی و طرح نگارش است... (ممکن است تا یک دقیقه طول بکشد)</div>');
    post('viraseo_ai_serp_strategy', {analysis_id: window._vsSerpId}, r => {
        $b.prop('disabled', false).text('🤖 استراتژی هوش مصنوعی (شکست رقبا)');
        if (!r.success) { $('#vs-serp-ai-box').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        vsAiRender('#vs-serp-ai-box', r.data, 'serp');
    });
});
$(document).on('click', '.vs-ai-copy', function(){ copyText($(this).siblings('.vs-ai-body').text()); toast('کپی شد','success'); });

// === SERP: deep-analyze all 10 results (real data basis) ===
$(document).on('click', '#vs-serp-deep', function(){
    const $rows = $('#vs-serp-tbody .vs-serp-row');
    if (!$rows.length) return;
    const $b = $(this).prop('disabled', true);
    const $st = $('#vs-serp-deep-status');
    const items = [];
    let idx = 0;
    function next(){
        if (idx >= $rows.length) {
            $b.prop('disabled', false);
            $st.text('ذخیره و نتیجه‌گیری...');
            post('viraseo_serp_deep_save', {analysis_id: window._vsSerpId, items: JSON.stringify(items)}, r => {
                $st.text('✅ آنالیز دقیق کامل شد.');
                if (r.success) {
                    $('#vs-serp-conclusion').show().html('<div class="vs-alert vs-alert-info"><span class="dashicons dashicons-awards"></span><div><strong>نتیجه‌گیری (بر اساس داده واقعی):</strong><br>میانگین کلمات رقبا: '+r.data.avg_words+' | بلندترین رقیب: '+r.data.max_words+' | میانگین هدینگ: '+r.data.avg_headings+'<br>🎯 '+r.data.recommendation+'</div></div>');
                }
            });
            return;
        }
        const $row = $rows.eq(idx);
        const url = $row.data('url');
        $st.text('در حال آنالیز دقیق صفحه '+(idx+1)+' از '+$rows.length+'...');
        post('viraseo_serp_inspect', {url: url}, r => {
            if (r.success) {
                const d = r.data;
                $row.find('.vs-c-words').html(d.word_count_fa || d.word_count || 0);
                $row.find('.vs-c-head').text(d.h1+'/'+d.h2+'/'+d.h3);
                $row.find('.vs-c-img').text(d.images);
                items.push({url:url, word_count:d.word_count, h1:d.h1, h2:d.h2, h3:d.h3, images:d.images});
            } else {
                $row.find('.vs-c-words').html('<span class="vs-snippet-note">نشد</span>');
            }
            idx++; next();
        });
    }
    next();
});

// === SERP: Batch competitor analysis (all enhanced metrics at once) ===
$(document).on('click', '#vs-serp-batch-deep', function(){
    if (!window._vsSerpId) { toast('ابتدا یک تحلیل را باز کنید.','err'); return; }
    const $b = $(this).prop('disabled', true).text('📊 در حال تحلیل جامع...');
    const $st = $('#vs-serp-deep-status');
    $st.text('تحلیل جامع همه رقبا در حال انجام است... (ممکن است ۱-۲ دقیقه طول بکشد)');
    post('viraseo_serp_competitor_analysis', {analysis_id: window._vsSerpId}, r => {
        $b.prop('disabled', false).html('<span class="dashicons dashicons-chart-area"></span> 📊 تحلیل جامع رقبا (همه متریک‌ها)');
        if (!r.success) { $st.text(''); toast(r.data||'خطا','err'); return; }
        $st.text('✅ تحلیل جامع کامل شد.');
        $('#vs-serp-conclusion').show().html('<div class="vs-alert vs-alert-info"><span class="dashicons dashicons-awards"></span><div><strong>نتیجه‌گیری (بر اساس داده واقعی):</strong><br>میانگین کلمات رقبا: '+r.data.avg_words+' | بلندترین رقیب: '+r.data.max_words+' | میانگین هدینگ: '+r.data.avg_headings+'<br>🎯 '+r.data.recommendation+'</div></div>');
        // Update table rows with real data
        if (r.data.results) {
            const $rows = $('#vs-serp-tbody .vs-serp-row');
            r.data.results.forEach(function(res, i){
                if (res.error || !res.word_count) return;
                const $row = $rows.eq(i);
                $row.find('.vs-c-words').html(res.word_count_fa || res.word_count || 0);
                $row.find('.vs-c-head').text((res.h1||0)+'/'+(res.h2||0)+'/'+(res.h3||0));
                $row.find('.vs-c-img').text(res.images||'-');
            });
        }
        toast('تحلیل جامع رقبا با موفقیت انجام شد.', 'success');
    });
});

// === SERP: Dedicated Competitor Analysis ===
$(document).on('click', '#vs-comp-analyze', function(){
    const url = $('#vs-comp-url').val().trim();
    const kw = $('#vs-comp-keyword').val().trim();
    if (!url) { toast('آدرس صفحه رقیب را وارد کنید.','err'); return; }
    if (!kw) { toast('کلمه کلیدی هدف را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true).text('در حال تحلیل...');
    const $res = $('#vs-comp-result').html('<div class="vs-empty">⏳ در حال دریافت و تحلیل صفحه رقیب...</div>');
    post('viraseo_serp_inspect_full', {url: url, keyword: kw}, r => {
        $b.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> تحلیل اختصاصی');
        if (!r.success) { $res.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const d = r.data;
        const ka = d.keyword_analysis || {};
        const topKw = (d.top_keywords||[]).map(function(k){ return '<span class="vs-tag">'+k.word+' ('+k.count+')</span>'; }).join('');
        const sections = (d.section_words||[]).map(function(s){ return '<li><strong>'+s.heading+'</strong>: '+s.words+' کلمه</li>'; }).join('');
        const recs = (ka.recommendations||[]).map(function(r){ return '<li>'+r+'</li>'; }).join('');
        const jsNote = d.note ? '<div class="vs-alert vs-alert-warning" style="margin-top:10px"><span class="dashicons dashicons-warning"></span><p>'+d.note+'</p></div>' : '';
        $res.html(
            '<div class="vs-comp-full-result">'
            + jsNote
            + '<div class="vs-inspect-grid vs-comp-grid">'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.word_count_fa||d.word_count||0)+'</span><span class="vs-im-lbl">تعداد کلمات</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.h1||0)+'/'+(d.h2||0)+'/'+(d.h3||0)+'</span><span class="vs-im-lbl">H1/H2/H3</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.images||0)+'</span><span class="vs-im-lbl">تصاویر ('+(d.images_no_alt||0)+' بدون alt)</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.internal_links||0)+'/'+(d.external_links||0)+'</span><span class="vs-im-lbl">لینک داخلی/خارجی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.response_time||0)+'ms</span><span class="vs-im-lbl">زمان پاسخ</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.reading_level||0)+'</span><span class="vs-im-lbl">سطح خوانایی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.tables||0)+'</span><span class="vs-im-lbl">جدول</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.videos||0)+'</span><span class="vs-im-lbl">ویدیو</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.has_faq?'بله':'خیر')+'</span><span class="vs-im-lbl">بخش FAQ</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.content_type||'-')+'</span><span class="vs-im-lbl">نوع محتوا</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.keyword_density||0)+'%</span><span class="vs-im-lbl">تراکم کلمه کلیدی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.word_count_score||0)+'/۱۰۰</span><span class="vs-im-lbl">امتیاز محتوا</span></div>'
            + '</div>'
            + '<div class="vs-comp-details">'
            +   '<div class="vs-comp-section">'
            +     '<h4>تحلیل کلمه کلیدی:</h4>'
            +     '<div class="vs-comp-kw-grid">'
            +       '<span class="vs-kw-check '+(ka.in_title?'vs-kw-yes':'vs-kw-no')+'">عنوان: '+(ka.in_title?'✓':'✗')+'</span>'
            +       '<span class="vs-kw-check '+(ka.in_h1?'vs-kw-yes':'vs-kw-no')+'">H1: '+(ka.in_h1?'✓':'✗')+'</span>'
            +       '<span class="vs-kw-check '+(ka.in_h2?'vs-kw-yes':'vs-kw-no')+'">H2: '+(ka.in_h2?'✓':'✗')+'</span>'
            +       '<span class="vs-kw-check '+(ka.in_meta?'vs-kw-yes':'vs-kw-no')+'">متا: '+(ka.in_meta?'✓':'✗')+'</span>'
            +       '<span class="vs-kw-check '+(ka.in_url?'vs-kw-yes':'vs-kw-no')+'">URL: '+(ka.in_url?'✓':'✗')+'</span>'
            +       '<span class="vs-kw-prominence">برجستگی: '+(ka.prominence||0)+'/۱۰۰</span>'
            +     '</div>'
            +     (recs ? '<ul class="vs-comp-recs">'+recs+'</ul>' : '')
            +   '</div>'
            +   '<div class="vs-comp-section">'
            +     '<h4>کلمات پرتکرار:</h4>'
            +     '<div class="vs-tags">'+topKw+'</div>'
            +   '</div>'
            +   (sections ? '<div class="vs-comp-section"><h4>تعداد کلمات هر بخش:</h4><ul class="vs-comp-sections">'+sections+'</ul></div>' : '')
            +   '<div class="vs-comp-section">'
            +     '<h4>عنوان صفحه:</h4><p>'+(d.title||'-')+'</p>'
            +     '<h4>توضیحات متا:</h4><p>'+(d.meta_desc||'-')+'</p>'
            +     '<h4>OG Title:</h4><p>'+(d.og_title||'-')+'</p>'
            +     '<h4>Canonical:</h4><p style="direction:ltr;text-align:left">'+(d.canonical_url||'-')+'</p>'
            +     '<h4>Robots:</h4><p>'+(d.robots_meta||'-')+'</p>'
            +   '</div>'
            + '</div></div>'
        );
    });
});

// === SERP DEEP INSPECT (on-demand per result) ===
$(document).on('click', '.vs-serp-row', function(){
    const $row = $(this);
    const url = $row.data('url');
    if (!url) return;
    // Toggle: if detail already open, close it
    const $next = $row.next('.vs-serp-detail');
    if ($next.length) { $next.remove(); return; }
    const colspan = $row.children('td').length;
    const $detail = $('<tr class="vs-serp-detail"><td colspan="'+colspan+'"><div class="vs-inspect-loading">⏳ در حال دریافت و تحلیل صفحه...</div></td></tr>');
    $row.after($detail);
    post('viraseo_serp_inspect', {url: url}, r => {
        if (!r.success) { $detail.find('td').html('<div class="vs-inspect-err">❌ '+(r.data||'خطا در تحلیل')+'</div>'); return; }
        const d = r.data;
        let h2list = (d.h2_texts||[]).map(t=>'<li>'+t+'</li>').join('') || '<li class="vs-empty">-</li>';
        let schema = (d.schema||[]).length ? (d.schema||[]).map(s=>'<span class="vs-tag">'+s+'</span>').join('') : '<span class="vs-empty">ندارد</span>';
        let topKw = (d.top_keywords||[]).map(function(k){ return '<span class="vs-tag">'+k.word+' ('+k.count+')</span>'; }).join('') || '<span class="vs-empty">-</span>';
        let jsNote = d.note ? '<div class="vs-alert vs-alert-warning" style="margin:8px 0"><span class="dashicons dashicons-warning"></span><p>'+d.note+'</p></div>' : '';
        let wordZeroNote = (d.word_count === 0) ? '<div class="vs-alert vs-alert-warning" style="margin:8px 0"><span class="dashicons dashicons-info"></span><p>تعداد کلمات صفر است. احتمالا این صفحه محتوا را با JavaScript رندر می‌کند و از سمت سرور قابل خواندن نیست.</p></div>' : '';
        $detail.find('td').html(
            '<div class="vs-inspect">'
            + wordZeroNote + jsNote
            + '<div class="vs-inspect-grid">'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.word_count_fa||d.word_count||0)+'</span><span class="vs-im-lbl">تعداد دقیق کلمات</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.h1+'/'+d.h2+'/'+d.h3+'</span><span class="vs-im-lbl">H1/H2/H3</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.images+'</span><span class="vs-im-lbl">تصاویر ('+d.images_no_alt+' بدون alt)</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.internal_links+'/'+d.external_links+'</span><span class="vs-im-lbl">لینک داخلی/خارجی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.paragraphs+'</span><span class="vs-im-lbl">پاراگراف</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.word_count_score||0)+'/۱۰۰</span><span class="vs-im-lbl">امتیاز محتوا</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.response_time||0)+'ms</span><span class="vs-im-lbl">زمان پاسخ</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.reading_level||0)+'</span><span class="vs-im-lbl">سطح خوانایی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.tables||0)+'</span><span class="vs-im-lbl">جدول</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.videos||0)+'</span><span class="vs-im-lbl">ویدیو</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.has_faq?'بله':'خیر')+'</span><span class="vs-im-lbl">بخش FAQ</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+(d.content_type||'-')+'</span><span class="vs-im-lbl">نوع محتوا</span></div>'
            + '</div>'
            + '<div class="vs-inspect-cols">'
            +   '<div><h4>ساختار هدینگ‌ها (H2):</h4><ul class="vs-inspect-h2">'+h2list+'</ul></div>'
            +   '<div><h4>عنوان صفحه (Title):</h4><p class="vs-inspect-title">'+(d.title||'-')+'</p>'
            +       '<h4>توضیحات متا:</h4><p class="vs-inspect-desc">'+(d.meta_desc||'-')+'</p>'
            +       '<h4>اسکیما (Schema):</h4><div class="vs-tags">'+schema+'</div>'
            +       '<h4>کلمات پرتکرار:</h4><div class="vs-tags">'+topKw+'</div>'
            +       (d.keyword_density ? '<h4>تراکم کلمه کلیدی:</h4><p>'+d.keyword_density+'%</p>' : '')
            +       '<h4>Canonical:</h4><p style="direction:ltr;text-align:left;font-size:11px">'+(d.canonical_url||'-')+'</p>'
            +       '<h4>Robots:</h4><p>'+(d.robots_meta||'-')+'</p></div>'
            + '</div></div>'
        );
    });
});

// === RANK MONITOR ===
function loadRankAlerts() {
    if (!$('#vs-rank-alerts').length) return;
    post('viraseo_rank_alerts', {}, r => {
        if (!r.success || !r.data.rows.length) { $('#vs-rank-alerts').hide(); return; }
        let items = r.data.rows.slice(0, 8).map(a =>
            '<li>📉 <strong>'+a.keyword+'</strong>: از رتبه '+a.from+' به '+a.to+' افت کرد <span style="color:var(--vs-text-muted);font-size:11px">('+a.time+')</span></li>'
        ).join('');
        $('#vs-rank-alerts').show().html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-warning"></span><div><strong>هشدارهای افت رتبه اخیر:</strong><ul style="margin:6px 0 0;padding-right:18px">'+items+'</ul></div></div>');
    });
}
function loadRanks() {
    post('viraseo_rank_list', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-rank-tbody').empty();
        if (!r.data.rows.length) { $t.html('<tr><td colspan="10" class="vs-empty">هنوز کلمه‌ای اضافه نشده. از فرم بالا اضافه کنید.</td></tr>'); return; }
        r.data.rows.forEach(k => {
            let chg = '<span class="vs-rank-flat">—</span>';
            if (k.change > 0) chg = '<span class="vs-rank-up">▲ '+k.change+'</span>';
            else if (k.change < 0) chg = '<span class="vs-rank-down">▼ '+Math.abs(k.change)+'</span>';
            let spark = rankSpark(k.history);
            let urlCell = k.found_url ? '<a href="'+k.found_url+'" target="_blank">↗</a>' : '<span class="vs-empty">خارج از نتایج</span>';
            let pagesCell = '<input type="number" class="vs-rank-pages-edit" data-id="'+k.id+'" min="1" max="10" value="'+k.pages+'" style="width:52px;padding:4px;text-align:center;" title="تعداد صفحات بررسی این کلمه">';
            $t.append('<tr><td><strong>'+k.keyword+'</strong></td><td><span class="vs-rank-badge">'+k.current+'</span></td><td>'+chg+'</td><td>'+k.best+'</td><td>'+spark+'</td><td>'+urlCell+'</td><td>'+pagesCell+'</td><td>'+k.freq+'</td><td style="font-size:11px;color:var(--vs-text-muted)">'+k.last+'</td><td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-rank-check" data-id="'+k.id+'" title="بررسی الان">⟳</button> <button class="vs-btn vs-btn-sm vs-btn-danger vs-rank-del" data-id="'+k.id+'">×</button></td></tr>');
        });
    });
}
function rankSpark(history) {
    if (!history || !history.length) return '<span class="vs-empty">—</span>';
    let bars = history.map(h => {
        let r = h.r;
        if (r === null || r === undefined) return '<span class="vs-spark-bar vs-spark-miss" title="'+h.d+': خارج از ۵۰"></span>';
        let pct = Math.max(8, 100 - (r * 2)); // rank 1 = tall, rank 50 = short
        let color = r <= 3 ? '#10b981' : (r <= 10 ? '#0ea5e9' : (r <= 20 ? '#f59e0b' : '#ef4444'));
        return '<span class="vs-spark-bar" style="height:'+pct+'%;background:'+color+'" title="'+h.d+': رتبه '+r+'"></span>';
    }).join('');
    return '<span class="vs-spark">'+bars+'</span>';
}
$(document).on('click', '#vs-rank-add', function(){
    const kw = $('#vs-rank-kw').val().trim();
    if (!kw) { toast('کلمه کلیدی وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    post('viraseo_rank_add', {keyword:kw, frequency:$('#vs-rank-freq').val(), max_pages:$('#vs-rank-pages').val()}, r => {
        $b.prop('disabled', false);
        if (r.success) { toast(r.data.message,'success'); $('#vs-rank-kw').val(''); loadRanks(); }
        else toast(r.data,'err');
    });
});
// Edit per-keyword page count inline
$(document).on('change', '.vs-rank-pages-edit', function(){
    post('viraseo_rank_pages', {id:$(this).data('id'), max_pages:$(this).val()}, r => {
        if (r.success) toast(r.data.message, 'success'); else toast(r.data, 'err');
    });
});
$(document).on('click', '#vs-rank-checkall', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-rank-tbody').html('<tr><td colspan="9" class="vs-empty">⏳ در حال بررسی همه کلمات...</td></tr>');
    post('viraseo_rank_check', {}, r => {
        $b.prop('disabled', false);
        if (r.success) { toast(r.data.message,'success'); loadRanks(); } else toast(r.data,'err');
    });
});
$(document).on('click', '.vs-rank-check', function(){
    const $b = $(this).prop('disabled', true).text('...');
    post('viraseo_rank_check', {id:$(this).data('id')}, r => {
        if (r.success) {
            const m = r.data.message || 'به‌روزرسانی شد';
            const notFound = m.indexOf('⚠️') === 0;
            $('#vs-rank-msg').show().html('<div class="vs-alert vs-alert-'+(notFound?'warning':'info')+'"><span class="dashicons dashicons-'+(notFound?'warning':'yes')+'"></span><p>'+m+'</p></div>');
            toast(notFound ? 'سایت در نتایج پیدا نشد — جزئیات بالای جدول' : 'رتبه به‌روزرسانی شد', notFound?'info':'success');
            loadRanks();
        }
        else { toast(r.data,'err'); $b.prop('disabled',false).text('⟳'); }
    });
});
$(document).on('click', '.vs-rank-del', function(){
    if (!confirm('حذف این کلمه از رصد؟')) return;
    post('viraseo_rank_remove', {id:$(this).data('id')}, ()=>loadRanks());
});

// === INTERNAL LINKS ===
$(document).on('click', '#vs-scan-links', function(){
    const $s = $('#vs-scan-status').text('اسکن...');
    post('viraseo_trigger_scan', {}, r => {
        $s.text(r.success? r.data.message : 'خطا');
        if (r.success) { loadOrphans(); loadSuggestions(); loadLinkPower(); }
    });
});
function loadOrphans() {
    post('viraseo_get_orphans', {post_type: $('#vs-orphan-type').val()||'all'}, r => {
        if (!r.success) return;
        const $t = $('#vs-orphans-tbody').empty();
        vsFillTypes('#vs-orphan-type', r.data.types);
        if (!r.data.rows || !r.data.rows.length) {
            $t.html('<tr class="vs-empty"><td colspan="5" class="vs-empty">🎉 عالی! هیچ صفحه یتیمی (با این فیلتر) یافت نشد.</td></tr>');
            $('#vs-orphan-count').text('');
            $('#vs-orphans-pager').empty();
            return;
        }
        r.data.rows.forEach(o => {
            $t.append(`<tr><td><a href="${o.url}" target="_blank">${o.title}</a></td><td>${o.type}</td><td>${o.inlinks}</td><td>${o.outlinks}</td><td><a href="${o.edit}" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>`);
        });
        $('#vs-orphan-count').text(r.data.total + ' صفحه یتیم');
        vsRowPaginate($('#vs-orphans-tbody'), $('#vs-orphans-pager'), 25);
    });
}
$(document).on('change', '#vs-orphan-type', loadOrphans);
function loadSuggestions() {
    post('viraseo_get_suggestions', {type: window._vsSuggType||'', post_type: $('#vs-sugg-type').val()||'all'}, r => {
        if (!r.success) return;
        const $c = $('#vs-suggestions-list').empty();
        if (r.data.counts) {
            $('#vs-cnt-all').text(r.data.counts.all||0);
            $('#vs-cnt-exact').text(r.data.counts.exact||0);
            $('#vs-cnt-partial').text(r.data.counts.partial||0);
            $('#vs-cnt-semantic').text(r.data.counts.semantic||0);
        }
        if (r.data.types) vsFillTypes('#vs-sugg-type', r.data.types);
        if (!r.data.rows.length) { $c.html('<div class="vs-empty">پیشنهادی در این دسته نیست.</div>'); return; }
        const typeColor = {exact:'green', partial:'blue', semantic:'orange'};
        r.data.rows.forEach(s => {
            const tc = typeColor[s.type] || 'orange';
            $c.append(`<div class="vs-suggestion">
                <div class="vs-suggestion-head">
                    <span class="vs-badge vs-badge-${tc}">${s.type_label}</span>
                    <div class="vs-suggestion-score"><div class="vs-suggestion-score-fill" style="width:${s.score}%"></div></div>
                    <span class="vs-suggestion-pct">${Math.round(s.score)}%</span>
                </div>
                <div class="vs-suggestion-flow">
                    <div class="vs-flow-node"><small>از (مبدا):</small><a href="${s.source_edit}" target="_blank">${s.source}</a> <span class="vs-type-tag">${s.source_type}</span></div>
                    <span class="vs-flow-arrow">→</span>
                    <div class="vs-flow-node"><small>به (مقصد):</small><a href="${s.target_url}" target="_blank">${s.target}</a> <span class="vs-type-tag">${s.target_type}</span></div>
                </div>
                <div class="vs-suggestion-anchor-row">انکر پیشنهادی: <span class="vs-suggestion-anchor">${s.anchor}</span></div>
                <div class="vs-suggestion-reason">${s.reason||''}</div>
                <div class="vs-row"><button class="vs-btn vs-btn-sm vs-btn-primary vs-apply-link" data-id="${s.id}">⚡ درج خودکار</button><button class="vs-btn vs-btn-sm vs-btn-success vs-accept-link" data-id="${s.id}">✓ تأیید دستی</button><button class="vs-btn vs-btn-sm vs-btn-danger vs-reject-link" data-id="${s.id}">✗ رد</button></div>
            </div>`);
        });
    });
}
$(document).on('change', '#vs-sugg-type', loadSuggestions);
$(document).on('click', '#vs-sugg-filters .vs-chip', function(){
    $(this).addClass('active').siblings().removeClass('active');
    window._vsSuggType = $(this).data('type') || '';
    loadSuggestions();
});
$(document).on('click', '.vs-accept-link', function(){ post('viraseo_accept_link',{id:$(this).data('id')},()=>loadSuggestions()); });
$(document).on('click', '.vs-reject-link', function(){ post('viraseo_reject_link',{id:$(this).data('id')},()=>loadSuggestions()); });
$(document).on('click', '.vs-apply-link', function(){
    const $b = $(this).prop('disabled', true).text('در حال درج...');
    post('viraseo_apply_link', {id:$(this).data('id')}, r => {
        if (r.success) { toast(r.data.message,'success'); loadSuggestions(); }
        else { toast(r.data,'err'); $b.prop('disabled',false).text('⚡ درج خودکار'); }
    });
});
$(document).on('click', '#vs-ai-suggestions', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-ai-sugg-box').show().html('<div class="vs-empty">🤖 هوش مصنوعی در حال تحلیل پیشنهادها...</div>');
    post('viraseo_ai_suggestions', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-ai-sugg-box').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $('#vs-ai-sugg-box').html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 تحلیل پیشنهادهای لینک <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div><button class="vs-btn vs-btn-sm vs-btn-success vs-ai-save" data-kind="general">💾 ذخیره</button></div>');
    });
});
$(document).on('click', '#vs-apply-all-links', function(){
    if (!confirm('لینک‌های پیشنهادی به‌صورت خودکار داخل محتوای صفحات درج می‌شوند. ادامه می‌دهید؟')) return;
    const $b = $(this).prop('disabled', true);
    $('#vs-apply-all-status').text('در حال درج همه لینک‌ها...');
    post('viraseo_apply_all_links', {}, r => {
        $b.prop('disabled', false);
        $('#vs-apply-all-status').text(r.success ? r.data.message : 'خطا');
        if (r.success) { toast(r.data.message,'success'); loadSuggestions(); }
        else toast(r.data,'err');
    });
});
// === LINK POWER (internal PageRank) + GRAPH ===
function loadLinkPower() {
    if (!$('#vs-power-tbody').length) return;
    post('viraseo_link_scores', {}, r => {
        const $t = $('#vs-power-tbody').empty();
        if (!r.success || !r.data.rows.length) { $t.html('<tr><td colspan="3" class="vs-empty">داده‌ای نیست. «اسکن لینک‌ها» را بزنید.</td></tr>'); return; }
        r.data.rows.forEach(p => {
            $t.append('<tr><td><a href="'+p.url+'" target="_blank">'+p.title+'</a></td><td>'+(p.inlinks||0)+'</td><td>'+linkScoreBar(p.score)+'</td></tr>');
        });
    });
    drawLinkGraph();
}
function drawLinkGraph() {
    post('viraseo_link_graph', {}, r => {
        const $g = $('#vs-link-graph');
        if (!r.success || !r.data.nodes.length) { $g.html('<span class="vs-empty">گرافی نیست. «اسکن لینک‌ها» را بزنید.</span>'); return; }
        const W = $g.width() || 800, H = 460, cx = W/2, cy = H/2, R = Math.min(W,H)/2 - 60;
        const nodes = r.data.nodes, n = nodes.length;
        const pos = {};
        nodes.forEach((nd, i) => {
            const ang = (i / n) * Math.PI * 2 - Math.PI/2;
            pos[nd.id] = {x: cx + R*Math.cos(ang), y: cy + R*Math.sin(ang)};
        });
        let svg = '<svg viewBox="0 0 '+W+' '+H+'" width="100%" height="'+H+'" style="direction:ltr">';
        // edges
        r.data.edges.forEach(e => {
            const a = pos[e.from], b = pos[e.to];
            if (!a || !b) return;
            svg += '<line x1="'+a.x.toFixed(1)+'" y1="'+a.y.toFixed(1)+'" x2="'+b.x.toFixed(1)+'" y2="'+b.y.toFixed(1)+'" stroke="rgba(129,140,248,.25)" stroke-width="1"/>';
        });
        // nodes
        nodes.forEach(nd => {
            const p = pos[nd.id];
            const rad = 6 + (nd.score/100)*18;
            const color = nd.score >= 66 ? '#10b981' : (nd.score >= 33 ? '#f59e0b' : '#6366f1');
            svg += '<circle cx="'+p.x.toFixed(1)+'" cy="'+p.y.toFixed(1)+'" r="'+rad.toFixed(1)+'" fill="'+color+'" fill-opacity="0.85"><title>'+nd.title+' — قدرت: '+nd.score+'</title></circle>';
            svg += '<text x="'+p.x.toFixed(1)+'" y="'+(p.y - rad - 4).toFixed(1)+'" font-size="10" fill="#cbd5e1" text-anchor="middle">'+nd.title.substring(0,18)+'</text>';
        });
        svg += '</svg>';
        $g.html(svg);
    });
}

$(document).on('click', '.vs-cl-ai', function(){
    const ci = $(this).data('cl');
    const kw = $(this).data('kw');
    const pages = (window._vsClusters && window._vsClusters[ci]) || [];
    if (!pages.length) return;
    const $box = $('.vs-cl-ai-box[data-cl="'+ci+'"]').html('<div class="vs-empty">🤖 هوش مصنوعی در حال طراحی نقشه‌ی لینک سیلو...</div>');
    post('viraseo_ai_cluster', {keyword: kw, pages: JSON.stringify(pages)}, r => {
        if (!r.success) { $box.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $box.html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 نقشه‌ی لینک سیلو <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div></div>');
    });
});
// Topical clusters
$(document).on('click', '#vs-load-clusters', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-clusters-list').html('<div class="vs-empty">در حال محاسبه خوشه‌ها...</div>');
    post('viraseo_link_clusters', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-clusters-list').html('<div class="vs-empty">خطا</div>'); return; }
        const $l = $('#vs-clusters-list').empty();
        if (!r.data.clusters.length) { $l.html('<div class="vs-empty">خوشه‌ای یافت نشد (حداقل ۲ صفحه با موضوع مشترک لازم است).</div>'); return; }
        r.data.clusters.forEach((c, ci) => {
            let memberOpts = c.members.map(m => '<option value="'+m.id+'">'+m.title+' ['+m.type+']</option>').join('');
            let members = c.members.map(m => '<li><label><input type="checkbox" class="vs-cl-mem" data-cl="'+ci+'" value="'+m.id+'" '+(m.linked?'disabled checked':'')+'> <a href="'+m.url+'" target="_blank">'+m.title+'</a> <span class="vs-type-tag">'+m.type+'</span> '+(m.linked?'<span class="vs-badge vs-badge-green">لینک‌شده</span>':'')+'</label></li>').join('');
            const covColor = c.coverage >= 66 ? 'green' : (c.coverage >= 33 ? 'orange' : 'red');
            $l.append('<div class="vs-cluster" data-cl="'+ci+'" data-pillar="'+c.pillar_id+'">'
                + '<div class="vs-cluster-head"><span class="vs-badge vs-badge-blue">'+c.keyword+'</span> <span class="vs-cluster-count">'+c.count+' صفحه</span> <span class="vs-badge vs-badge-'+covColor+'">پوشش سیلو: '+c.coverage+'%</span> <span class="vs-cluster-count">👁️ '+c.impressions+' نمایش</span></div>'
                + '<div class="vs-cluster-pillar">🏛️ ستون (Pillar): <select class="vs-input vs-cl-pillar" data-cl="'+ci+'" style="max-width:320px;display:inline-block"><option value="'+c.pillar.id+'">'+c.pillar.title+' ['+c.pillar.type+'] (پیشنهادی)</option>'+memberOpts+'</select> <a href="'+c.pillar.url+'" target="_blank">↗</a></div>'
                + '<ul class="vs-cluster-members vs-cluster-members-list">'+members+'</ul>'
                + '<div class="vs-row"><button class="vs-btn vs-btn-sm vs-btn-primary vs-cl-link" data-cl="'+ci+'">🔗 لینک اعضای انتخابی به ستون</button><button class="vs-btn vs-btn-sm vs-btn-secondary vs-cl-ai" data-cl="'+ci+'" data-kw="'+escAttr(c.keyword)+'">🤖 نقشه‌ی لینک هوش مصنوعی</button><button class="vs-btn vs-btn-sm vs-btn-success vs-cl-content" data-cl="'+ci+'" data-kw="'+escAttr(c.keyword)+'">📝 تولید محتوا با AI</button><label class="vs-hint"><input type="checkbox" class="vs-cl-all" data-cl="'+ci+'"> انتخاب همه</label></div>'
                + '<div class="vs-cl-ai-box" data-cl="'+ci+'"></div>'
                + '<div class="vs-cl-content-panel" data-cl="'+ci+'" style="display:none"></div>'
                + '</div>');
            // Stash cluster page data for the AI request
            window._vsClusters = window._vsClusters || {};
            window._vsClusters[ci] = c.members.map(m => ({title:m.title, url:m.url, type:m.type, id:m.id})).concat([{title:c.pillar.title, url:c.pillar.url, type:c.pillar.type, id:c.pillar_id}]);
            window._vsClusterPillars = window._vsClusterPillars || {};
            window._vsClusterPillars[ci] = {title:c.pillar.title, url:c.pillar.url, id:c.pillar_id};
        });
    });
});
$(document).on('change', '.vs-cl-all', function(){
    const ci = $(this).data('cl');
    $('.vs-cl-mem[data-cl="'+ci+'"]:not(:disabled)').prop('checked', $(this).is(':checked'));
});
$(document).on('change', '.vs-cl-pillar', function(){
    const ci = $(this).data('cl');
    $('.vs-cluster[data-cl="'+ci+'"]').attr('data-pillar', $(this).val());
});
$(document).on('click', '.vs-cl-link', function(){
    const ci = $(this).data('cl');
    const $cl = $('.vs-cluster[data-cl="'+ci+'"]');
    const pillar = $cl.attr('data-pillar');
    const members = [];
    $('.vs-cl-mem[data-cl="'+ci+'"]:checked:not(:disabled)').each(function(){ if ($(this).val() !== pillar) members.push($(this).val()); });
    if (!members.length) { toast('حداقل یک عضو را انتخاب کنید.','err'); return; }
    if (!confirm('یک لینک از '+members.length+' صفحه به صفحه‌ی ستون درج می‌شود. ادامه؟')) return;
    const $b = $(this).prop('disabled', true).text('در حال لینک...');
    post('viraseo_cluster_link', {pillar_id: pillar, members: members}, r => {
        $b.prop('disabled', false).text('🔗 لینک اعضای انتخابی به ستون');
        toast(r.success?r.data.message:r.data, r.success?'success':'err');
        if (r.success) $('#vs-load-clusters').trigger('click');
    });
});

// === CLUSTER AI CONTENT GENERATION ===
$(document).on('click', '.vs-cl-content', function(){
    const ci = $(this).data('cl');
    const kw = $(this).data('kw');
    const pages = (window._vsClusters && window._vsClusters[ci]) || [];
    const pillar = (window._vsClusterPillars && window._vsClusterPillars[ci]) || {};
    const $panel = $('.vs-cl-content-panel[data-cl="'+ci+'"]');
    if ($panel.is(':visible')) { $panel.slideUp(200); return; }

    // Build the member selection UI
    let checkboxes = '';
    pages.forEach(function(pg, pi){
        if (pg.id === pillar.id) return; // skip pillar itself
        const isPost = pg.id && pg.id.toString().indexOf('p') === 0 && pg.id.toString().indexOf('pc') !== 0;
        checkboxes += '<label class="vs-cl-content-item' + (isPost ? '' : ' vs-disabled') + '">'
            + '<input type="checkbox" class="vs-cl-content-chk" data-idx="'+pi+'"' + (isPost ? '' : ' disabled') + '> '
            + pg.title + ' <span class="vs-type-tag">'+pg.type+'</span>'
            + (isPost ? '' : ' <span class="vs-hint">(فقط نوشته‌ها قابل ویرایش‌اند)</span>')
            + '</label>';
    });

    $panel.html(
        '<div class="vs-cl-content-header"><h4>📝 تولید محتوای خوشه‌ای با هوش مصنوعی</h4>'
        + '<p class="vs-hint">صفحاتی که می‌خواهید برایشان محتوا تولید شود را انتخاب کنید:</p></div>'
        + '<div class="vs-cl-content-checks">'+checkboxes+'</div>'
        + '<div class="vs-row" style="margin-top:12px">'
        + '<button class="vs-btn vs-btn-sm vs-btn-success vs-cl-content-go" data-cl="'+ci+'" data-kw="'+escAttr(kw)+'">🚀 تولید محتوا</button>'
        + '<label class="vs-hint"><input type="checkbox" class="vs-cl-content-all" data-cl="'+ci+'"> انتخاب همه</label>'
        + '</div>'
        + '<div class="vs-cl-content-progress" data-cl="'+ci+'" style="display:none"></div>'
        + '<div class="vs-cl-content-results" data-cl="'+ci+'"></div>'
    ).slideDown(200);
});
$(document).on('change', '.vs-cl-content-all', function(){
    const ci = $(this).data('cl');
    const $panel = $('.vs-cl-content-panel[data-cl="'+ci+'"]');
    $panel.find('.vs-cl-content-chk:not(:disabled)').prop('checked', $(this).is(':checked'));
});
$(document).on('click', '.vs-cl-content-go', function(){
    const ci = $(this).data('cl');
    const kw = $(this).data('kw');
    const pages = (window._vsClusters && window._vsClusters[ci]) || [];
    const pillar = (window._vsClusterPillars && window._vsClusterPillars[ci]) || {};
    const $panel = $('.vs-cl-content-panel[data-cl="'+ci+'"]');
    const $prog = $panel.find('.vs-cl-content-progress');
    const $results = $panel.find('.vs-cl-content-results').empty();

    // Gather selected pages
    const selected = [];
    $panel.find('.vs-cl-content-chk:checked').each(function(){
        const idx = parseInt($(this).data('idx'), 10);
        if (pages[idx]) selected.push(pages[idx]);
    });
    if (!selected.length) { toast('حداقل یک صفحه را انتخاب کنید.', 'err'); return; }

    const $btn = $(this).prop('disabled', true);
    let totalCost = 0;
    let current = 0;
    const total = selected.length;

    $prog.show().html('<div class="vs-cl-content-prog-bar"><span class="vs-cl-prog-text">در حال تولید: ۰ از '+total+'</span><div class="vs-cl-prog-track"><div class="vs-cl-prog-fill" style="width:0%"></div></div><span class="vs-cl-prog-cost">هزینه: $0</span></div>');

    function generateNext() {
        if (current >= total) {
            $prog.find('.vs-cl-prog-text').text('✅ تولید کامل شد ('+total+' صفحه)');
            $prog.find('.vs-cl-prog-cost').text('هزینه کل: $'+totalCost.toFixed(4));
            $btn.prop('disabled', false);
            return;
        }
        const pg = selected[current];
        $prog.find('.vs-cl-prog-text').text('در حال تولید: '+(current+1)+' از '+total+' — '+pg.title);
        $prog.find('.vs-cl-prog-fill').css('width', ((current/total)*100)+'%');

        post('viraseo_cluster_content_single', {
            keyword: kw,
            page_title: pg.title,
            page_url: pg.url,
            cluster_pages: JSON.stringify(pages),
            pillar_title: pillar.title || '',
            pillar_url: pillar.url || ''
        }, function(r){
            current++;
            $prog.find('.vs-cl-prog-fill').css('width', ((current/total)*100)+'%');
            if (!r.success) {
                $results.append('<div class="vs-cl-content-preview vs-cl-content-error"><div class="vs-cl-content-preview-head"><strong>'+pg.title+'</strong> <span class="vs-badge vs-badge-red">خطا</span></div><p>'+(r.data||'خطا در تولید')+'</p></div>');
                generateNext();
                return;
            }
            totalCost += parseFloat(r.data.cost) || 0;
            $prog.find('.vs-cl-prog-cost').text('هزینه: $'+totalCost.toFixed(4));

            // Extract post_id from the page id (p123 => 123)
            var postId = 0;
            if (pg.id && pg.id.toString().indexOf('p') === 0) postId = parseInt(pg.id.toString().substring(1), 10) || 0;

            var previewId = 'vs-clc-' + ci + '-' + current;
            $results.append(
                '<div class="vs-cl-content-preview" id="'+previewId+'">'
                + '<div class="vs-cl-content-preview-head"><strong>'+pg.title+'</strong> <span class="vs-badge vs-badge-green">تولید شد</span> <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div>'
                + '<div class="vs-cl-content-preview-title"><label>عنوان سئو:</label><input type="text" class="vs-input vs-clc-title" value="'+escAttr(r.data.title)+'" style="width:100%"></div>'
                + '<div class="vs-cl-content-preview-meta"><label>توضیحات متا:</label><input type="text" class="vs-input vs-clc-meta" value="'+escAttr(r.data.meta_desc)+'" style="width:100%"></div>'
                + '<div class="vs-cl-content-preview-body"><label>محتوا:</label><div class="vs-clc-content" contenteditable="false">'+r.data.content+'</div></div>'
                + '<div class="vs-row" style="margin-top:10px">'
                + (postId ? '<button class="vs-btn vs-btn-sm vs-btn-success vs-clc-apply" data-pid="'+postId+'" data-prev="'+previewId+'" data-kw="'+escAttr(kw)+'">✅ تأیید و ذخیره</button>' : '')
                + '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-clc-edit" data-prev="'+previewId+'">✏️ ویرایش</button>'
                + '<button class="vs-btn vs-btn-sm vs-btn-danger vs-clc-dismiss" data-prev="'+previewId+'">✗ رد</button>'
                + '</div></div>'
            );
            generateNext();
        });
    }
    generateNext();
});
// Edit mode for content preview
$(document).on('click', '.vs-clc-edit', function(){
    var prev = $(this).data('prev');
    var $c = $('#'+prev).find('.vs-clc-content');
    if ($c.attr('contenteditable') === 'true') {
        $c.attr('contenteditable', 'false').removeClass('vs-clc-editing');
        $(this).text('✏️ ویرایش');
    } else {
        $c.attr('contenteditable', 'true').addClass('vs-clc-editing').focus();
        $(this).text('💾 پایان ویرایش');
    }
});
// Dismiss a content preview
$(document).on('click', '.vs-clc-dismiss', function(){
    var prev = $(this).data('prev');
    $('#'+prev).slideUp(200, function(){ $(this).remove(); });
});
// Apply generated content to a post
$(document).on('click', '.vs-clc-apply', function(){
    var $btn = $(this).prop('disabled', true).text('در حال ذخیره...');
    var prev = $(this).data('prev');
    var pid = $(this).data('pid');
    var kw = $(this).data('kw');
    var $box = $('#'+prev);
    var title = $box.find('.vs-clc-title').val();
    var meta_desc = $box.find('.vs-clc-meta').val();
    var content = $box.find('.vs-clc-content').html();
    post('viraseo_cluster_content_apply', {
        post_id: pid, title: title, meta_desc: meta_desc, content: content, keyword: kw
    }, function(r){
        if (r.success) {
            toast(r.data.message, 'success');
            $box.find('.vs-cl-content-preview-head .vs-badge').removeClass('vs-badge-green').addClass('vs-badge-blue').text('ذخیره شد');
            $btn.text('✅ ذخیره شد').addClass('vs-btn-secondary').removeClass('vs-btn-success');
        } else {
            toast(r.data || 'خطا', 'err');
            $btn.prop('disabled', false).text('✅ تأیید و ذخیره');
        }
    });
});

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


// === BROKEN INTERNAL LINKS ===
$(document).on('click', '#vs-load-broken', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-broken-status').text('در حال بررسی محتوای صفحات...');
    $('#vs-broken-tbody').html('<tr><td colspan="5" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_broken_links', {}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-broken-tbody').empty();
        if (!r.success) { $('#vs-broken-status').text(''); $t.html('<tr><td colspan="5" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        $('#vs-broken-status').text(r.data.checked + ' صفحه بررسی شد.');
        if (!r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">🎉 لینک شکسته‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $t.append('<tr><td><a href="'+o.edit+'">'+o.source+'</a></td><td dir="ltr" style="font-size:11px"><a href="'+o.url+'" target="_blank">'+o.url+'</a></td><td>'+o.anchor+'</td><td><span class="vs-badge vs-badge-red">'+o.reason+'</span></td><td><a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>');
        });
    });
});

// === BACKLINK IMPORT FROM GSC ===
$(document).on('change', '#vs-bl-import-file', function(){
    const f = this.files && this.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = function(ev){ $('#vs-bl-import-csv').val(ev.target.result); toast('فایل خوانده شد — دکمه درون‌ریزی را بزنید','info'); };
    reader.readAsText(f, 'UTF-8');
});
$(document).on('click', '#vs-bl-import-btn', function(){
    const csv = $('#vs-bl-import-csv').val().trim();
    if (!csv) { toast('فایل CSV را انتخاب یا محتوا را بچسبانید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-bl-import-status').text('در حال درون‌ریزی...');
    post('viraseo_bl_import_gsc', {csv: csv, target_url: $('#vs-bl-import-target').val()}, r => {
        $b.prop('disabled', false);
        if (r.success) { $('#vs-bl-import-status').text(r.data.message); toast(r.data.message,'success'); $('#vs-bl-import-csv').val(''); loadBacklinks(); }
        else { $('#vs-bl-import-status').text(r.data||'خطا'); toast(r.data||'خطا','err'); }
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
    const $t = $('#vs-fc-tbody').html('<tr><td colspan="7" class="vs-empty">محاسبه...</td></tr>');
    post('viraseo_forecast', {target: $('#vs-fc-target').val()}, r => {
        if (!r.success) { $t.html('<tr><td colspan="7" class="vs-empty">خطا</td></tr>'); return; }
        const $tb = $('#vs-fc-tbody').empty();
        $('#vs-fc-total').text('+' + r.data.total_growth);
        $('#vs-fc-count').text(r.data.count || 0);
        if (!r.data.rows.length) { $tb.html('<tr><td colspan="8" class="vs-empty">فرصتی یافت نشد. ابتدا داده‌های سرچ کنسول را همگام‌سازی کنید.</td></tr>'); return; }
        r.data.rows.forEach(f => {
            const ec = f.effort_color === 'green' ? 'vs-badge-green' : (f.effort_color === 'orange' ? 'vs-badge-orange' : 'vs-badge-red');
            $tb.append(`<tr><td><a href="${f.url}" target="_blank" style="color:var(--vs-primary)">${f.keyword}</a></td><td>${f.position}</td><td>${f.impressions}</td><td>${f.clicks}</td><td>${f.potential}</td><td style="color:var(--vs-success);font-weight:700">${f.growth}</td><td><span class="vs-badge ${ec}">${f.effort}</span></td><td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-fc-page" data-url="${escAttr(f.url)}" title="${escAttr(f.action)}">💡 کلمات و اقدامات</button></td></tr>`);
        });
        vsRowPaginate($('#vs-fc-tbody'), $('#vs-fc-pager'), 25);
    });
});
// Per-page keyword opportunities + action checklist
$(document).on('click', '.vs-fc-page', function(){
    const url = $(this).data('url');
    const $row = $(this).closest('tr');
    const $next = $row.next('.vs-fc-detail');
    if ($next.length) { $next.remove(); return; }
    const $d = $('<tr class="vs-fc-detail"><td colspan="8"><div class="vs-inspect-loading">⏳ در حال تحلیل صفحه...</div></td></tr>');
    $row.after($d);
    post('viraseo_forecast_page', {url: url}, r => {
        if (!r.success) { $d.find('td').html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        let kws = r.data.keywords.map(k => '<tr><td>'+k.keyword+(k.is_opportunity?' <span class="vs-badge vs-badge-orange">فرصت</span>':'')+'</td><td>'+k.position+'</td><td>'+k.impressions+'</td><td>'+k.clicks+'</td></tr>').join('');
        // Rich, data-driven strategy cards (fallback to legacy checklist)
        let strat = '';
        if (r.data.strategy && r.data.strategy.length) {
            strat = r.data.strategy.map(s =>
                '<div class="vs-fc-strat"><div class="vs-fc-strat-h">'+s.icon+' <b>'+s.label+'</b></div><div class="vs-fc-strat-t">'+s.text+'</div></div>'
            ).join('');
        } else {
            strat = '<ul class="vs-checklist">'+(r.data.checklist||[]).map(c => '<li>'+c+'</li>').join('')+'</ul>';
        }
        // Summary chips
        const sm = r.data.summary || {};
        let chips = '';
        if (sm.impressions !== undefined) {
            chips = '<div class="vs-fc-chips">'
                + '<span class="vs-fc-chip">👁️ نمایش: <b>'+sm.impressions+'</b></span>'
                + '<span class="vs-fc-chip">🖱️ کلیک: <b>'+sm.clicks+'</b></span>'
                + '<span class="vs-fc-chip">📈 CTR: <b>'+sm.ctr+'</b></span>'
                + '<span class="vs-fc-chip vs-fc-chip-g">🎯 بُرد سریع: <b>'+sm.quickwin+'</b></span>'
                + '<span class="vs-fc-chip vs-fc-chip-b">🚀 فاصله ضربه: <b>'+sm.striking+'</b></span>'
                + '<span class="vs-fc-chip vs-fc-chip-o">🖱️ افت CTR: <b>'+sm.ctrgap+'</b></span>'
                + '</div>';
        }
        const aiBtn = r.data.ai_enabled
            ? '<button class="vs-btn vs-btn-sm vs-btn-primary vs-fc-ai" data-url="'+escAttr(r.data.url)+'">🤖 استراتژی کامل با هوش مصنوعی</button>'
              + ' <button class="vs-btn vs-btn-sm vs-btn-success vs-fc-autofix" data-url="'+escAttr(r.data.url)+'">✏️ اصلاح صفحه برای افزایش ترافیک</button>'
            : '<span class="vs-muted" style="font-size:12px">برای استراتژی هوش مصنوعی، AI را در تنظیمات فعال کنید.</span>';
        $d.find('td').html(
            '<div class="vs-fc-detail-box">'+chips
            + '<div class="vs-row" style="gap:24px;align-items:flex-start">'
            + '<div style="flex:2;min-width:280px"><h4>📊 کلمات دیگری که این صفحه می‌گیرد (فرصت رشد):</h4><table class="vs-table"><thead><tr><th>کلمه</th><th>جایگاه</th><th>نمایش</th><th>کلیک</th></tr></thead><tbody>'+kws+'</tbody></table></div>'
            + '<div style="flex:1;min-width:240px"><h4>✅ استراتژی افزایش ترافیک:</h4>'+strat+'</div>'
            + '</div>'
            + '<div class="vs-fc-ai-wrap" style="margin-top:14px">'+aiBtn+'<div class="vs-fc-ai-out" style="display:none"></div></div>'
            + '</div>'
        );
    });
});
// AI-powered complete strategy for a single page (grounded in its real GSC data)
$(document).on('click', '.vs-fc-ai', function(){
    const url = $(this).data('url');
    const $btn = $(this);
    const $out = $btn.closest('.vs-fc-ai-wrap').find('.vs-fc-ai-out');
    $btn.prop('disabled', true).text('🤖 در حال تحلیل با هوش مصنوعی...');
    $out.show().html('<div class="vs-inspect-loading">⏳ هوش مصنوعی در حال ساخت نقشه‌ی راه بر اساس داده‌های واقعی سرچ کنسول...</div>');
    post('viraseo_forecast_ai', {url: url}, r => {
        $btn.prop('disabled', false).text('🤖 استراتژی کامل با هوش مصنوعی');
        if (!r.success) { $out.html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        const cost = r.data.cost ? '<div class="vs-muted" style="font-size:11px;margin-top:8px">هزینه تقریبی: $'+r.data.cost+' • توکن: '+r.data.tokens+'</div>' : '';
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $out.html('<div class="vs-ai-result"><div class="vs-ai-text">'+html+'</div>'+cost+'</div>');
    });
});
// Auto-fix page content for traffic increase (AI rewrites/enhances the page)
$(document).on('click', '.vs-fc-autofix', function(){
    const url = $(this).data('url');
    const $btn = $(this).prop('disabled', true).text('✏️ در حال تولید محتوای بهبودیافته...');
    const $wrap = $btn.closest('.vs-fc-ai-wrap');
    let $out = $wrap.find('.vs-fc-autofix-out');
    if (!$out.length) { $wrap.append('<div class="vs-fc-autofix-out" style="margin-top:14px"></div>'); $out = $wrap.find('.vs-fc-autofix-out'); }
    $out.show().html('<div class="vs-inspect-loading">⏳ هوش مصنوعی در حال بازنویسی/بهبود محتوا برای افزایش ترافیک... (ممکن است تا ۹۰ ثانیه طول بکشد)</div>');
    post('viraseo_forecast_autofix', {url: url}, r => {
        $btn.prop('disabled', false).text('✏️ اصلاح صفحه برای افزایش ترافیک');
        if (!r.success) { $out.html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        const cost = r.data.cost ? ' (هزینه: $'+r.data.cost+' • توکن: '+r.data.tokens+')' : '';
        $out.html(
            '<div class="vs-autofix-preview">'
            + '<h4>📝 محتوای بهبودیافته (پیش‌نمایش)'+cost+'</h4>'
            + '<div class="vs-autofix-tabs"><button class="vs-btn vs-btn-sm vs-autofix-tab active" data-show="new">محتوای جدید</button><button class="vs-btn vs-btn-sm vs-autofix-tab" data-show="old">محتوای فعلی</button><button class="vs-btn vs-btn-sm vs-autofix-tab" data-show="diff">🔍 مقایسه</button></div>'
            + '<div class="vs-autofix-new vs-autofix-pane" contenteditable="true" style="min-height:200px">'+r.data.new_content+'</div>'
            + '<div class="vs-autofix-old vs-autofix-pane" style="display:none;opacity:0.7">'+r.data.old_content+'</div>'
            + '<div class="vs-autofix-diff vs-autofix-pane" style="display:none;max-height:500px;overflow:auto;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px;line-height:2.2">'+vsBuildDiff(r.data.old_content, r.data.new_content)+'</div>'
            + '<div class="vs-autofix-actions" style="margin-top:12px;">'
            + '<button class="vs-btn vs-btn-success vs-fc-apply" data-pid="'+r.data.post_id+'">✅ تأیید و جایگزینی محتوا</button> '
            + '<button class="vs-btn vs-btn-secondary vs-fc-reject">❌ رد کردن</button>'
            + '</div></div>'
        );
    });
});
$(document).on('click', '.vs-autofix-tab', function(){
    const show = $(this).data('show');
    $(this).addClass('active').siblings().removeClass('active');
    $(this).closest('.vs-autofix-preview').find('.vs-autofix-pane').hide();
    $(this).closest('.vs-autofix-preview').find('.vs-autofix-'+show).show();
});
$(document).on('click', '.vs-fc-apply', function(){
    const $btn = $(this).prop('disabled', true).text('در حال ذخیره...');
    const pid = $(this).data('pid');
    const content = $(this).closest('.vs-autofix-preview').find('.vs-autofix-new').html();
    post('viraseo_forecast_apply', {post_id: pid, content: content}, r => {
        $btn.prop('disabled', false).text('✅ تأیید و جایگزینی محتوا');
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        toast(r.data.message, 'success');
        $btn.closest('.vs-autofix-preview').html('<div class="vs-chk-ok">'+r.data.message+' <button class="vs-btn vs-btn-sm vs-btn-secondary vs-restore-backup" data-pid="'+pid+'">↩️ بازگردانی به محتوای قبلی</button></div>');
    });
});
$(document).on('click', '.vs-fc-reject', function(){
    $(this).closest('.vs-autofix-preview').html('<div class="vs-hint">محتوای پیشنهادی رد شد. تغییری اعمال نشد.</div>');
});

// === KEYWORD DISCOVERY ===
$(document).on('click', '#vs-disc-start', function(){
    const seed = $('#vs-disc-seed').val().trim();
    if (!seed) { toast('کلمه وارد کنید.','err'); return; }
    const $s = $('#vs-disc-status').text('ارسال درخواست...');
    $(this).prop('disabled',true);
    $('#vs-disc-error').hide();
    post('viraseo_discover', {seed}, r => {
        if (!r.success) {
            $s.text('');
            $('#vs-disc-start').prop('disabled',false);
            $('#vs-disc-error').show().find('p').html(r.data);
            toast(r.data,'err');
            return;
        }
        $s.text(r.data.message);
        window._vsDiscId = r.data.discovery_id;
        pollDiscovery();
    });
});
function pollDiscovery() {
    var attempts = 0;
    const maxAttempts = 20; // 20 * 3.5s = 70 seconds
    const iv = setInterval(()=>{
        attempts++;
        post('viraseo_disc_ideas', {discovery_id: window._vsDiscId}, r => {
            if (!r.success) return;
            if (r.data.status==='completed') { clearInterval(iv); $('#vs-disc-start').prop('disabled',false); showIdeas(r.data); }
            else if (attempts >= maxAttempts) {
                clearInterval(iv);
                $('#vs-disc-start').prop('disabled',false);
                $('#vs-disc-status').text('');
                $('#vs-disc-error').show().find('p').html('⏱️ Timeout — n8n نتیجه‌ای برنگردوند.<br><br>بررسی کنید:<br>• آیا ورکفلو در n8n اجرا شد؟ (Executions بررسی کنید)<br>• آیا n8n می‌تونه به REST URL سایت شما POST کنه؟<br>• Secret باید در هر دو طرف یکسان باشه<br><br>Callback URL: <code>' + window.VS.rest + 'keyword-ideas</code>');
            }
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
    if ($('#vs-kw-tbody').length) { loadKeywords(); loadStriking(); loadCannibal(); loadDaily(); }
    // Links page
    if ($('#vs-orphans-tbody').length) { loadOrphans(); loadSuggestions(); loadLinkPower(); }
    // Backlinks page
    if ($('#vs-bl-tbody').length) { loadBacklinks(); loadDisavow(); }
    // Workflows page
    if ($('#vs-wf-grid').length) loadWorkflows();
    // Rank monitor page
    if ($('#vs-rank-tbody').length) { loadRanks(); loadRankAlerts(); }
    // Target keywords page
    if ($('#vs-tg-tbody').length) loadTargets();
    // WooCommerce SEO page
    if ($('#vs-woo-tbody').length) loadWooCats();
    // AI saved outputs
    if ($('#vs-saved-list').length) loadAiSaved();
    // Keyword strategy plan
    if ($('#vs-stg-list').length) loadPlan();
    // Modern SEO: show llms.txt URL
    if ($('#vs-llms-url').length) $('#vs-llms-url').text(window.location.origin + '/llms.txt');
    // SERP auto-start when arriving from Target Keywords (?keyword=..&autostart=1)
    if ($('#vs-serp-kw').length) {
        loadSerpHistory();
        var params = new URLSearchParams(window.location.search);
        var kwParam = params.get('keyword');
        if (kwParam) {
            $('#vs-serp-kw').val(kwParam);
            window._vsSerpPost = parseInt(params.get('post'), 10) || 0;
            if (params.get('autostart') === '1') $('#vs-serp-start').trigger('click');
        }
    }
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

// === GSC WINNERS & LOSERS ===
$(document).on('click', '#vs-load-winners', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-win-tbody,#vs-lose-tbody').html('<tr><td colspan="4" class="vs-empty">در حال محاسبه...</td></tr>');
    post('viraseo_gsc_winners', {metric: $('#vs-win-metric').val(), back: $('#vs-win-back').val()||1}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-win-tbody,#vs-lose-tbody').html('<tr><td colspan="4" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        // Fill comparison dropdown once
        const $back = $('#vs-win-back');
        if (r.data.snapshots && $back.find('option').length <= 1) {
            const n = r.data.snapshots.length;
            $back.empty();
            for (let k = 1; k < n; k++) $back.append('<option value="'+k+'">مقایسه با '+r.data.snapshots[n-1-k].date+'</option>');
        }
        $('#vs-win-range').text('از ' + r.data.prev + ' تا ' + r.data.latest);
        const render = (rows, sel) => {
            const $t = $(sel).empty();
            if (!rows.length) { $t.html('<tr><td colspan="4" class="vs-empty">موردی نیست.</td></tr>'); return; }
            rows.forEach(o => {
                const color = o.delta > 0 ? '#10b981' : '#ef4444';
                $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a><br><small style="color:var(--vs-text-muted)">جایگاه: '+o.pos_was+' → '+o.pos_now+'</small></td><td>'+o.was+'</td><td>'+o.now+'</td><td style="color:'+color+';font-weight:700">'+o.delta_fa+'</td></tr>');
            });
        };
        render(r.data.winners, '#vs-win-tbody');
        render(r.data.losers, '#vs-lose-tbody');
    });
});

// === GSC SMART INSIGHTS ===
$(document).on('click', '#vs-load-insights', function(){
    const $b = $(this).prop('disabled', true);
    ['#vs-ins-ctr','#vs-ins-quick','#vs-ins-zero'].forEach(s=>$(s).html('<tr><td colspan="6" class="vs-empty">در حال تحلیل...</td></tr>'));
    post('viraseo_gsc_insights', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        const ctr = $('#vs-ins-ctr').empty();
        if (!r.data.ctr_ops.length) ctr.html('<tr><td colspan="6" class="vs-empty">موردی نیست.</td></tr>');
        r.data.ctr_ops.forEach(o => ctr.append('<tr><td><strong>'+o.keyword+'</strong></td><td><a href="'+o.url+'" target="_blank">↗</a></td><td>'+o.pos+'</td><td style="color:#ef4444">'+o.ctr+'</td><td style="color:#10b981">'+o.exp+'</td><td>'+o.impr+'</td></tr>'));
        const q = $('#vs-ins-quick').empty();
        if (!r.data.quick.length) q.html('<tr><td colspan="4" class="vs-empty">موردی نیست.</td></tr>');
        r.data.quick.forEach(o => q.append('<tr><td><strong>'+o.keyword+'</strong></td><td><a href="'+o.url+'" target="_blank">↗</a></td><td><span class="vs-badge vs-badge-orange">'+o.pos+'</span></td><td>'+o.impr+'</td></tr>'));
        const z = $('#vs-ins-zero').empty();
        if (!r.data.zero.length) z.html('<tr><td colspan="4" class="vs-empty">موردی نیست.</td></tr>');
        r.data.zero.forEach(o => z.append('<tr><td><strong>'+o.keyword+'</strong></td><td><a href="'+o.url+'" target="_blank">↗</a></td><td>'+o.pos+'</td><td>'+o.impr+'</td></tr>'));
        toast('تحلیل هوشمند انجام شد','success');
    });
});

// === SEO OPPORTUNITIES ===
$(document).on('click', '#vs-load-linkopp', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-linkopp-tbody').html('<tr><td colspan="7" class="vs-empty">در حال محاسبه...</td></tr>');
    post('viraseo_link_opportunities', {post_type: $('#vs-linkopp-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-linkopp-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="7" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-linkopp-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="7" class="vs-empty">🎉 فرصت پرپتانسیلی یافت نشد (همه صفحات پربازدید لینک کافی دارند).</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td><strong style="color:var(--vs-success)">'+o.impressions+'</strong></td><td>'+o.clicks+'</td><td>'+o.position+'</td><td><span class="vs-badge vs-badge-'+(o.inlinks_raw===0?'red':'orange')+'">'+o.inlinks+'</span></td><td><a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>');
        });
        vsRowPaginate($('#vs-linkopp-tbody'), $('#vs-linkopp-pager'), 25);
    });
});
// === ON-PAGE SEO CHECKLIST ===
$(document).on('click', '#vs-load-onpage', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-onpage-tbody').html('<tr><td colspan="6" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_onpage', {post_type: $('#vs-onpage-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-onpage-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="6" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-onpage-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">🎉 صفحه‌ای با مشکل On-Page یافت نشد (یا کلمه هدف ندارند).</td></tr>'); return; }
        r.data.rows.forEach((o, i) => {
            $t.append('<tr class="vs-onpage-row" data-i="'+i+'"><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td>'+o.keyword+'</td><td>'+o.impressions+'</td><td>'+linkScoreBar(o.score)+'</td><td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-onpage-toggle" data-i="'+i+'">جزئیات ▾</button></td></tr>');
            let checks = o.checks.map(c => '<li class="'+(c.ok?'vs-chk-ok':'vs-chk-no')+'">'+(c.ok?'✓':'✗')+' '+c.l+(c.note?' <small>('+c.note+')</small>':'')+'</li>').join('');
            $t.append('<tr class="vs-onpage-detail vs-onpage-detail-'+i+'" style="display:none"><td colspan="6"><ul class="vs-onpage-checks">'+checks+'</ul> <a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-primary">ویرایش صفحه</a> <button class="vs-btn vs-btn-sm vs-btn-secondary vs-onpage-ai" data-id="'+o.id+'">🤖 پیشنهاد اصلاح AI</button> <button class="vs-btn vs-btn-sm vs-btn-success vs-onpage-autofix" data-id="'+o.id+'" data-issues="'+escAttr(JSON.stringify(o.checks.filter(c=>!c.ok).map(c=>c.label)))+'">✏️ اصلاح خودکار محتوا</button><div class="vs-onpage-ai-box"></div></td></tr>');
        });
        vsRowPaginate($('#vs-onpage-tbody'), $('#vs-onpage-pager'), 25);
    });
});
$(document).on('click', '.vs-onpage-toggle', function(){
    $('.vs-onpage-detail-'+$(this).data('i')).toggle();
});
$(document).on('click', '.vs-onpage-ai', function(){
    const $box = $(this).siblings('.vs-onpage-ai-box').html('<div class="vs-empty">🤖 در حال تهیه پیشنهاد اصلاح...</div>');
    post('viraseo_ai_content', {post_id:$(this).data('id'), mode:'improve'}, r => {
        if (!r.success) { $box.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $box.html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 پیشنهاد اصلاح <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div></div>');
    });
});
$(document).on('click', '.vs-onpage-autofix', function(){
    const $btn = $(this).prop('disabled', true).text('✏️ در حال اصلاح...');
    const pid = $(this).data('id');
    const issues = JSON.parse($(this).attr('data-issues')||'[]');
    const $box = $(this).siblings('.vs-onpage-ai-box').html('<div class="vs-empty">⏳ هوش مصنوعی در حال اصلاح on-page... (تا ۹۰ ثانیه)</div>');
    post('viraseo_onpage_fix', {post_id: pid, issues: issues}, r => {
        $btn.prop('disabled', false).text('✏️ اصلاح خودکار محتوا');
        if (!r.success) { $box.html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        const cost = r.data.cost ? ' (هزینه: $'+r.data.cost+')' : '';
        $box.html(vsRewriteUI(r.data, cost, 'viraseo_seo_rewrite_apply'));
    });
});

$(document).on('click', '#vs-load-thin', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-thin-tbody').html('<tr><td colspan="6" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_thin_content', {threshold: $('#vs-thin-threshold').val(), post_type: $('#vs-thin-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-thin-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="6" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-thin-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">🎉 محتوای ضعیفی یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            const pc = o.priority === 'بالا' ? 'red' : (o.priority === 'متوسط' ? 'orange' : 'blue');
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td><strong style="color:'+(o.words<150?'#ef4444':'#f59e0b')+'">'+o.words_fa+'</strong></td><td>'+o.impressions+'</td><td><span class="vs-badge vs-badge-'+pc+'">'+o.priority+'</span></td><td><a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">بازنویسی</a></td></tr>');
        });
        vsRowPaginate($('#vs-thin-tbody'), $('#vs-thin-pager'), 25);
    });
});

// === TARGET KEYWORDS MANAGEMENT ===
var vsTgPage = 1, vsTgTypesLoaded = false;
function loadTargets() {
    if (!$('#vs-tg-tbody').length) return;
    $('#vs-tg-tbody').html('<tr><td colspan="8" class="vs-empty">در حال بارگذاری...</td></tr>');
    post('viraseo_targets_list', {
        search: $('#vs-tg-search').val()||'',
        post_type: $('#vs-tg-type').val()||'all',
        orderby: $('#vs-tg-orderby').val()||'modified',
        order: 'desc',
        page: vsTgPage
    }, r => {
        const $t = $('#vs-tg-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="8" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        // Populate type filter once
        if (!vsTgTypesLoaded && r.data.types) {
            r.data.types.forEach(ty => $('#vs-tg-type').append('<option value="'+ty.slug+'">'+ty.label+'</option>'));
            vsTgTypesLoaded = true;
        }
        $('#vs-tg-count').text('مجموع: '+(r.data.total||0)+' صفحه — صفحه '+(r.data.page||1)+' از '+(r.data.pages||1));
        renderTgPager(r.data.page||1, r.data.pages||1);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="8" class="vs-empty">صفحه‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            let stats = o.stats ? ('کلیک '+o.stats.clicks+' · نمایش '+o.stats.impressions+' · جایگاه '+o.stats.position) : '<span class="vs-empty">—</span>';
            let suggest = o.suggest ? ('<span class="vs-tag">'+o.suggest+'</span> <button class="vs-btn vs-btn-sm vs-btn-secondary vs-tg-use" data-id="'+o.id+'" data-kw="'+escAttr(o.suggest)+'">استفاده</button>') : '<span class="vs-empty">—</span>';
            let serpBtn = o.current ? '<a class="vs-btn vs-btn-sm vs-btn-primary" href="admin.php?page=viraseo-serp&keyword='+encodeURIComponent(o.current)+'&post='+o.id+'&autostart=1" title="تحلیل SERP این کلمه و ذخیره نتیجه برای این صفحه">🔍 تحلیل SERP</a>' : '';
            let intentCell = o.serp_intent ? ('<span class="vs-badge vs-badge-blue">'+o.serp_intent.label+'</span>'+(o.serp_intent.avg_words?'<br><small style="color:var(--vs-text-muted)">میانگین کلمات رقبا: '+o.serp_intent.avg_words+'</small>':'')+(o.serp_intent.rec?'<br><small style="color:var(--vs-text-muted)">'+o.serp_intent.rec+'</small>':'')) : '<span class="vs-empty">هنوز تحلیل نشده</span>';
            $t.append('<tr>'
                + '<td><a href="'+o.edit+'">'+o.title+'</a><br><small style="color:var(--vs-text-muted)">'+o.type+'</small></td>'
                + '<td><input type="text" class="vs-input vs-tg-kw" data-id="'+o.id+'" value="'+escAttr(o.current)+'" style="min-width:160px" placeholder="کلمه هدف اصلی..."><input type="text" class="vs-input vs-tg-sec" data-id="'+o.id+'" value="'+escAttr((o.secondary||[]).join('، '))+'" style="min-width:160px;margin-top:4px;font-size:11px" placeholder="کلمات فرعی (با کاما)..."></td>'
                + '<td><span class="vs-badge vs-badge-blue">'+o.source+'</span></td>'
                + '<td>'+linkScoreBar(o.link_score)+'</td>'
                + '<td style="font-size:11px">'+stats+'</td>'
                + '<td style="font-size:11px;max-width:240px">'+intentCell+'</td>'
                + '<td>'+suggest+'</td>'
                + '<td><button class="vs-btn vs-btn-sm vs-btn-success vs-tg-save" data-id="'+o.id+'">ذخیره</button> '+serpBtn+' <button class="vs-btn vs-btn-sm vs-btn-secondary vs-tg-ai" data-id="'+o.id+'" title="کمک هوش مصنوعی برای محتوا">🤖</button></td>'
                + '</tr>');
        });
    });
}
function escAttr(s){ return (s||'').replace(/"/g,'&quot;'); }
function linkScoreBar(score){
    score = parseInt(score,10)||0;
    var color = score >= 66 ? '#10b981' : (score >= 33 ? '#f59e0b' : '#ef4444');
    return '<div class="vs-score-bar" title="قدرت لینک داخلی: '+score+'/۱۰۰"><div class="vs-score-fill" style="width:'+score+'%;background:'+color+'"></div><span>'+score+'</span></div>';
}
$(document).on('click', '#vs-tg-reload', function(){ vsTgPage = 1; loadTargets(); });
$(document).on('change', '#vs-tg-type, #vs-tg-orderby', function(){ vsTgPage = 1; loadTargets(); });
$(document).on('keyup', '#vs-tg-search', function(e){ if (e.key === 'Enter') { vsTgPage = 1; loadTargets(); } });
function renderTgPager(page, pages) {
    const $p = $('#vs-tg-pager'); if (!$p.length) return;
    $p.empty();
    if (pages <= 1) return;
    if (page > 1) $p.append('<button class="vs-btn vs-btn-sm vs-btn-secondary vs-tg-page" data-p="'+(page-1)+'">‹ قبلی</button>');
    $p.append('<span class="vs-pager-info">صفحه '+page+' از '+pages+'</span>');
    if (page < pages) $p.append('<button class="vs-btn vs-btn-sm vs-btn-secondary vs-tg-page" data-p="'+(page+1)+'">بعدی ›</button>');
}
$(document).on('click', '.vs-tg-page', function(){ vsTgPage = parseInt($(this).data('p'),10)||1; loadTargets(); $('html,body').animate({scrollTop:0},200); });
$(document).on('click', '.vs-tg-ai', function(){
    const id = $(this).data('id');
    const $row = $(this).closest('tr');
    const $next = $row.next('.vs-tg-ai-detail');
    if ($next.length) { $next.remove(); return; }
    const $d = $('<tr class="vs-tg-ai-detail"><td colspan="8"><div class="vs-empty">🤖 هوش مصنوعی در حال تدوین طرح محتوا...</div></td></tr>');
    $row.after($d);
    post('viraseo_ai_content', {post_id: id, mode: 'outline'}, r => {
        if (!r.success) { $d.find('td').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $d.find('td').html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 طرح محتوا <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div></div>');
    });
});
$(document).on('click', '.vs-tg-use', function(){
    const $row = $(this).closest('tr');
    $row.find('.vs-tg-kw').val($(this).data('kw'));
});
$(document).on('click', '.vs-tg-save', function(){
    const id = $(this).data('id');
    const $row = $(this).closest('tr');
    const kw = $row.find('.vs-tg-kw').val();
    const sec = $row.find('.vs-tg-sec').val();
    const $b = $(this).prop('disabled', true).text('...');
    post('viraseo_target_save', {id: id, keyword: kw, secondary: sec}, r => {
        $b.prop('disabled', false).text('ذخیره');
        if (r.success) toast('کلمه هدف ذخیره شد','success'); else toast(r.data,'err');
        loadTargets();
    });
});

// === WOOCOMMERCE SEO ===
function loadWooCats() {
    $('#vs-woo-tbody').html('<tr><td colspan="7" class="vs-empty">در حال تحلیل...</td></tr>');
    post('viraseo_woo_categories', {}, r => {
        const $t = $('#vs-woo-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="7" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        if (!r.data.rows.length) { $t.html('<tr><td colspan="7" class="vs-empty">دسته‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(c => {
            const hc = c.health === 'ok' ? 'green' : (c.health === 'warn' ? 'orange' : 'red');
            const hl = c.health === 'ok' ? 'سالم' : (c.health === 'warn' ? 'نیاز به بهبود' : 'ضعیف');
            const issues = c.issues.length ? '<br><small style="color:var(--vs-text-muted)">'+c.issues.join(' · ')+'</small>' : '';
            $t.append('<tr>'
                + '<td><a href="'+c.url+'" target="_blank"><strong>'+c.name+'</strong></a>'+issues+'</td>'
                + '<td>'+c.count_fa+'</td><td>'+c.desc_words+'</td><td>'+c.impressions+'</td>'
                + '<td><input type="text" class="vs-input vs-woo-kw" data-id="'+c.id+'" value="'+escAttr(c.keyword)+'" placeholder="کلمه هدف..." style="min-width:140px"></td>'
                + '<td><span class="vs-badge vs-badge-'+hc+'">'+hl+'</span></td>'
                + '<td><button class="vs-btn vs-btn-sm vs-btn-success vs-woo-kw-save" data-id="'+c.id+'">ذخیره</button> <button class="vs-btn vs-btn-sm vs-btn-primary vs-woo-autolink" data-id="'+c.id+'" title="درج لینک از همه محصولات این دسته به صفحه دسته">🔗 لینک محصولات به دسته</button></td>'
                + '</tr>');
        });
    });
}
$(document).on('click', '#vs-woo-load', loadWooCats);
$(document).on('click', '.vs-woo-kw-save', function(){
    const id = $(this).data('id');
    const kw = $(this).closest('tr').find('.vs-woo-kw').val();
    post('viraseo_woo_cat_kw', {id:id, keyword:kw}, r => toast(r.success?r.data.message:r.data, r.success?'success':'err'));
});
$(document).on('click', '.vs-woo-autolink', function(){
    if (!confirm('یک لینک به صفحه‌ی این دسته در انتهای توضیحات همه محصولات این دسته درج می‌شود. ادامه؟')) return;
    const $b = $(this).prop('disabled', true).text('...');
    post('viraseo_woo_autolink', {id:$(this).data('id')}, r => {
        $b.prop('disabled', false).text('🔗 لینک محصولات به دسته');
        toast(r.success?r.data.message:r.data, r.success?'success':'err');
    });
});

// === MODERN SEO 2026 ===
function vsFillTypes(sel, types){ const $s=$(sel); if($s.data('filled')||!types)return; types.forEach(t=>$s.append('<option value="'+t.slug+'">'+t.label+'</option>')); $s.data('filled',true); }
$(document).on('click', '#vs-ai-load', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-ai-tbody').html('<tr><td colspan="5" class="vs-empty">در حال تحلیل...</td></tr>');
    post('viraseo_ai_readiness', {post_type: $('#vs-ai-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-ai-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="5" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-ai-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="5" class="vs-empty">صفحه‌ای یافت نشد.</td></tr>'); return; }
        // Store for sorting
        window._vsAiRows = r.data.rows;
        vsAiRender(r.data.rows);
    });
});
function vsAiRender(rows) {
    const $t = $('#vs-ai-tbody').empty();
    rows.forEach(o => {
        const tips = o.tips.map(t=>'<li>'+t+'</li>').join('');
        const aiFixBtn = V.aiEnabled ? '<button class="vs-btn vs-btn-sm vs-btn-success vs-aifix-geo" data-id="'+o.id+'" data-tips="'+escAttr(JSON.stringify(o.tips))+'">🤖 اصلاح خودکار</button> ' : '';
        const scoreClass = o.score >= 80 ? 'vs-badge-green' : (o.score >= 50 ? 'vs-badge-orange' : 'vs-badge-red');
        $t.append('<tr data-score="'+o.score+'"><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td><span class="vs-badge '+scoreClass+'">'+o.score+'</span></td><td><ul style="margin:0;padding-right:16px;font-size:11px">'+tips+'</ul></td><td>'+aiFixBtn+'<a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">بهبود</a></td></tr>');
    });
    vsRowPaginate($('#vs-ai-tbody'), $('#vs-ai-pager'), 25);
}
$(document).on('change', '#vs-ai-sort', function(){
    if (!window._vsAiRows) return;
    const val = $(this).val();
    let sorted = window._vsAiRows.slice();
    if (val === 'score-asc') sorted.sort((a,b) => a.score - b.score);
    else if (val === 'score-desc') sorted.sort((a,b) => b.score - a.score);
    vsAiRender(sorted);
});
$(document).on('click', '#vs-fresh-load', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-fresh-tbody').html('<tr><td colspan="7" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_freshness', {months: $('#vs-fresh-months').val(), post_type: $('#vs-fresh-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-fresh-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="7" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-fresh-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="7" class="vs-empty">محتوای کهنه‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            const pc = o.priority==='بالا'?'red':(o.priority==='متوسط'?'orange':'blue');
            const rewriteBtn = V.aiEnabled ? '<button class="vs-btn vs-btn-sm vs-btn-success vs-seo-rewrite" data-id="'+o.id+'">🤖 بروزرسانی سئو</button> ' : '';
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td>'+o.modified+'</td><td>'+o.age+'</td><td>'+o.impressions+'</td><td><span class="vs-badge vs-badge-'+pc+'">'+o.priority+'</span></td><td>'+rewriteBtn+'<a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">به‌روزرسانی</a></td></tr>');
        });
        vsRowPaginate($('#vs-fresh-tbody'), $('#vs-fresh-pager'), 25);
    });
});
$(document).on('click', '#vs-fa-load', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-fa-tbody').html('<tr><td colspan="4" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_persian_quality', {post_type: $('#vs-fa-type').val()||'all'}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-fa-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="4" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        vsFillTypes('#vs-fa-type', r.data.types);
        if (!r.data.rows.length) { $t.html('<tr><td colspan="4" class="vs-empty">🎉 مشکل نگارشی مهمی یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            const issues = o.issues.map(i=>'<li>'+i+'</li>').join('');
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><span class="vs-type-tag">'+o.type+'</span></td><td><ul style="margin:0;padding-right:16px;font-size:11px">'+issues+'</ul></td><td><button class="vs-btn vs-btn-sm vs-btn-success vs-fa-fix" data-id="'+o.id+'">🔧 اصلاح خودکار</button> <a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>');
        });
        vsRowPaginate($('#vs-fa-tbody'), $('#vs-fa-pager'), 25);
    });
});
$(document).on('click', '.vs-fa-fix', function(){
    if (!confirm('مشکلات نگارشی این صفحه (نیم‌فاصله و حروف عربی) به‌صورت خودکار اصلاح و ذخیره می‌شود. ادامه؟')) return;
    const $b = $(this).prop('disabled', true).text('...');
    post('viraseo_persian_fix', {post_id:$(this).data('id')}, r => {
        if (r.success) { toast(r.data.message,'success'); $('#vs-fa-load').trigger('click'); }
        else { toast(r.data,'err'); $b.prop('disabled',false).text('🔧 اصلاح خودکار'); }
    });
});

// AI auto-fix for GEO/AI readiness issues
$(document).on('click', '.vs-aifix-geo', function(){
    const $btn = $(this).prop('disabled', true).text('🤖 در حال اصلاح...');
    const pid = $(this).data('id');
    const tips = JSON.parse($(this).attr('data-tips')||'[]');
    const $row = $(this).closest('tr');
    let $detail = $row.next('.vs-aifix-detail');
    if (!$detail.length) { $row.after('<tr class="vs-aifix-detail"><td colspan="5"></td></tr>'); $detail = $row.next('.vs-aifix-detail'); }
    $detail.show().find('td').html('<div class="vs-empty">⏳ هوش مصنوعی در حال اصلاح محتوا برای AI/GEO... (ممکن است تا ۹۰ ثانیه)</div>');
    post('viraseo_ai_fix_readiness', {post_id: pid, tips: tips}, r => {
        $btn.prop('disabled', false).text('🤖 اصلاح خودکار');
        if (!r.success) { $detail.find('td').html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        const cost = r.data.cost ? ' (هزینه: $'+r.data.cost+')' : '';
        $detail.find('td').html(vsRewriteUI(r.data, cost, 'viraseo_seo_rewrite_apply'));
    });
});
// SEO rewrite for stale content
$(document).on('click', '.vs-seo-rewrite', function(){
    const $btn = $(this).prop('disabled', true).text('🤖 در حال بروزرسانی...');
    const pid = $(this).data('id');
    const $row = $(this).closest('tr');
    let $detail = $row.next('.vs-rewrite-detail');
    if (!$detail.length) { $row.after('<tr class="vs-rewrite-detail"><td colspan="7"></td></tr>'); $detail = $row.next('.vs-rewrite-detail'); }
    $detail.show().find('td').html('<div class="vs-empty">⏳ هوش مصنوعی در حال بروزرسانی بر اساس اصول Helpful Content... (تا ۹۰ ثانیه)</div>');
    post('viraseo_seo_rewrite', {post_id: pid}, r => {
        $btn.prop('disabled', false).text('🤖 بروزرسانی سئو');
        if (!r.success) { $detail.find('td').html('<div class="vs-inspect-err">'+(r.data||'خطا')+'</div>'); return; }
        const cost = r.data.cost ? ' (هزینه: $'+r.data.cost+')' : '';
        $detail.find('td').html(vsRewriteUI(r.data, cost, 'viraseo_seo_rewrite_apply'));
    });
});
function vsRewriteUI(data, costStr, applyAction) {
    // Build a visual diff (highlight changes)
    const diffHtml = vsBuildDiff(data.old_content, data.new_content);
    return '<div class="vs-autofix-preview">'
        + '<h4>📝 محتوای بهبودیافته'+costStr+'</h4>'
        + '<div class="vs-autofix-tabs"><button class="vs-btn vs-btn-sm vs-rw-tab active" data-show="new">محتوای جدید (قابل ویرایش)</button><button class="vs-btn vs-btn-sm vs-rw-tab" data-show="old">محتوای فعلی</button><button class="vs-btn vs-btn-sm vs-rw-tab" data-show="diff">🔍 مقایسه تفاوت‌ها</button></div>'
        + '<div class="vs-autofix-new vs-autofix-pane" contenteditable="true" style="min-height:180px;max-height:400px;overflow:auto;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px">'+data.new_content+'</div>'
        + '<div class="vs-autofix-old vs-autofix-pane" style="display:none;opacity:0.7;max-height:400px;overflow:auto;border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:12px">'+data.old_content+'</div>'
        + '<div class="vs-autofix-diff vs-autofix-pane" style="display:none;max-height:500px;overflow:auto;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px;line-height:2.2">'+diffHtml+'</div>'
        + '<div class="vs-autofix-actions" style="margin-top:12px;">'
        + '<button class="vs-btn vs-btn-success vs-rw-apply" data-pid="'+data.post_id+'" data-action="'+applyAction+'">✅ تأیید و جایگزینی</button> '
        + '<button class="vs-btn vs-btn-secondary vs-rw-reject">❌ رد کردن</button>'
        + '<span class="vs-hint" style="margin-right:12px">می‌توانید قبل از تأیید، محتوای جدید را مستقیماً ویرایش کنید.</span>'
        + '</div></div>';
}
/**
 * Build a visual word-level diff between old and new HTML content.
 * Green background = added in new, Red strikethrough = removed from old.
 */
function vsBuildDiff(oldHtml, newHtml) {
    // Strip HTML tags for text comparison, then re-wrap
    const stripTags = h => (h||'').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    const oldWords = stripTags(oldHtml).split(' ');
    const newWords = stripTags(newHtml).split(' ');

    // Simple LCS-based diff (optimized for readability, not performance on huge texts)
    const MAX = 800; // limit for performance
    const ow = oldWords.slice(0, MAX), nw = newWords.slice(0, MAX);

    // Build LCS table (bounded)
    const m = ow.length, n = nw.length;
    const dp = Array.from({length: m+1}, () => new Uint16Array(n+1));
    for (let i = 1; i <= m; i++)
        for (let j = 1; j <= n; j++)
            dp[i][j] = ow[i-1] === nw[j-1] ? dp[i-1][j-1]+1 : Math.max(dp[i-1][j], dp[i][j-1]);

    // Backtrack to find diff
    let result = [];
    let i = m, j = n;
    while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && ow[i-1] === nw[j-1]) {
            result.unshift({type:'same', word: ow[i-1]});
            i--; j--;
        } else if (j > 0 && (i === 0 || dp[i][j-1] >= dp[i-1][j])) {
            result.unshift({type:'add', word: nw[j-1]});
            j--;
        } else {
            result.unshift({type:'del', word: ow[i-1]});
            i--;
        }
    }

    // Render with colors
    let html = '<div class="vs-diff-view">';
    let lastType = '';
    result.forEach(r => {
        if (r.type === 'add') html += '<span class="vs-diff-add">'+r.word+'</span> ';
        else if (r.type === 'del') html += '<span class="vs-diff-del">'+r.word+'</span> ';
        else html += r.word + ' ';
    });
    // If content was longer than MAX, note it
    if (oldWords.length > MAX || newWords.length > MAX) html += '<br><span class="vs-hint">(مقایسه فقط ۸۰۰ کلمه‌ی اول را نشان می‌دهد)</span>';
    html += '</div>';
    return html;
}
$(document).on('click', '.vs-rw-tab', function(){
    const show = $(this).data('show');
    $(this).addClass('active').siblings().removeClass('active');
    const $preview = $(this).closest('.vs-autofix-preview');
    $preview.find('.vs-autofix-pane').hide();
    $preview.find('.vs-autofix-'+show).show();
});
$(document).on('click', '.vs-rw-apply', function(){
    const $btn = $(this).prop('disabled', true).text('در حال ذخیره...');
    const pid = $(this).data('pid');
    const action = $(this).data('action') || 'viraseo_seo_rewrite_apply';
    const content = $(this).closest('.vs-autofix-preview').find('.vs-autofix-new').html();
    post(action, {post_id: pid, content: content}, r => {
        $btn.prop('disabled', false).text('✅ تأیید و جایگزینی');
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        toast(r.data.message, 'success');
        $btn.closest('.vs-autofix-preview').html('<div class="vs-chk-ok">'+r.data.message+' <button class="vs-btn vs-btn-sm vs-btn-secondary vs-restore-backup" data-pid="'+pid+'">↩️ بازگردانی به محتوای قبلی</button></div>');
    });
});
$(document).on('click', '.vs-rw-reject', function(){
    $(this).closest('.vs-autofix-preview').html('<div class="vs-hint">رد شد. تغییری اعمال نشد.</div>');
});
// Restore backup content
$(document).on('click', '.vs-restore-backup', function(){
    const pid = $(this).data('pid');
    if (!confirm('محتوای فعلی با نسخه‌ی قبلی (قبل از اصلاح AI) جایگزین می‌شود. ادامه؟')) return;
    const $b = $(this).prop('disabled', true).text('↩️ در حال بازگردانی...');
    post('viraseo_restore_backup', {post_id: pid}, r => {
        $b.prop('disabled', false).text('↩️ بازگردانی به محتوای قبلی');
        toast(r.success ? r.data.message : (r.data||'خطا'), r.success ? 'success' : 'err');
        if (r.success) $b.replaceWith('<span class="vs-hint">✅ بازگردانی شد.</span>');
    });
});

// === BACKUP MANAGEMENT (diagnostics page) ===
$(document).on('click', '#vs-load-backups', function(){
    const $t = $('#vs-backup-tbody').html('<tr><td colspan="4" class="vs-empty">در حال بارگذاری...</td></tr>');
    post('viraseo_list_backups', {}, r => {
        const $tb = $('#vs-backup-tbody').empty();
        if (!r.success) { $tb.html('<tr><td colspan="4" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        if (!r.data.rows.length) { $tb.html('<tr><td colspan="4" class="vs-empty">🎉 هیچ بکاپی وجود ندارد (هنوز محتوایی با AI اصلاح نشده یا همه بازگردانی شده‌اند).</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $tb.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td>'+o.type+'</td><td>'+o.backup_time+'</td><td><button class="vs-btn vs-btn-sm vs-btn-primary vs-restore-backup" data-pid="'+o.id+'">↩️ بازگردانی</button> <a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>');
        });
    });
});

$(document).on('click', '#vs-llms-gen', function(){
    const $b = $(this).prop('disabled', true);
    post('viraseo_llms_txt', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        $('#vs-llms-content').val(r.data.content);
        $('#vs-llms-url').text(r.data.url);
        toast('llms.txt تولید شد','success');
    });
});
$(document).on('click', '#vs-llms-copy', function(){ copyText($('#vs-llms-content').val()); toast('کپی شد','success'); });

// === DIAGNOSTICS PAGE ===
$(document).on('click', '#vs-run-diag', function(){
    const $btn = $(this).prop('disabled', true).text('در حال بررسی...');
    post('viraseo_run_diagnostics', {}, r => {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> اجرای تشخیص کامل');
        if (!r.success) { toast(r.data || 'خطا', 'err'); return; }
        $('#vs-diag-results').show();
        const d = r.data;

        // Database
        let dbHtml = '<p style="font-size:14px;margin-bottom:12px;">' + d.database.message + '</p>';
        dbHtml += '<table class="vs-table"><thead><tr><th>جدول</th><th>وجود</th><th>ردیف</th></tr></thead><tbody>';
        d.database.tables.forEach(function(t) {
            dbHtml += '<tr><td>viraseo_' + t.table + '</td><td>' + (t.exists ? '<span class="vs-badge vs-badge-green">✓</span>' : '<span class="vs-badge vs-badge-red">✗</span>') + '</td><td>' + (t.rows >= 0 ? t.rows : '—') + '</td></tr>';
        });
        dbHtml += '</tbody></table>';
        $('#vs-diag-db-content').html(dbHtml);

        // GSC
        $('#vs-diag-gsc-content').html('<p style="font-size:14px;">' + d.gsc.message + '</p><p style="font-size:12px;color:var(--vs-text-muted);">Proxy: <code>' + d.gsc.proxy_url + '</code></p>');

        // n8n
        var n8nHtml = '<p style="font-size:14px;margin-bottom:12px;">' + d.n8n.message + '</p>';
        n8nHtml += '<p style="font-size:12px;color:var(--vs-text-muted);">آدرس: <code>' + d.n8n.url + '</code> | Secret: ' + d.n8n.secret + '</p>';
        if (d.n8n.webhooks && d.n8n.webhooks.length) {
            n8nHtml += '<h4 style="margin:16px 0 8px;color:#fff;">وضعیت ورکفلوها:</h4>';
            n8nHtml += '<table class="vs-table"><thead><tr><th>ورکفلو</th><th>Path</th><th>وضعیت</th><th>تست</th></tr></thead><tbody>';
            d.n8n.webhooks.forEach(function(w) {
                n8nHtml += '<tr><td>' + w.label + '</td><td dir="ltr"><code>' + w.path + '</code></td><td>' + w.message + '</td><td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-test-wh" data-path="' + w.path + '">تست</button></td></tr>';
            });
            n8nHtml += '</tbody></table>';
            n8nHtml += '<div class="vs-alert vs-alert-info" style="margin-top:16px;"><span class="dashicons dashicons-info"></span><p><strong>ورکفلو ❌ ؟</strong> فایل JSON مربوطه را از «ورکفلوهای n8n» دانلود → در n8n Import → Active کنید.</p></div>';
        }
        $('#vs-diag-n8n-content').html(n8nHtml);

        // AI
        if (d.ai) {
            var aiCls = d.ai.status === 'ok' ? 'green' : (d.ai.status === 'warning' ? 'orange' : 'red');
            var aiHtml = '<p style="font-size:14px;">' + d.ai.message + '</p>';
            aiHtml += '<p style="font-size:12px;color:var(--vs-text-muted);">مدل: <code>' + d.ai.model + '</code> | پروکسی: <code dir="ltr">' + d.ai.proxy + '</code></p>';
            if ($('#vs-diag-ai-content').length) $('#vs-diag-ai-content').html(aiHtml);
            else $('#vs-diag-n8n-content').after('<div class="vs-card" style="margin-top:16px;"><h3 class="vs-card-title">🤖 هوش مصنوعی (OpenRouter) <span class="vs-badge vs-badge-'+aiCls+'">'+(d.ai.status==='ok'?'سالم':(d.ai.status==='warning'?'غیرفعال':'خطا'))+'</span></h3><div id="vs-diag-ai-content">'+aiHtml+'</div></div>');
        }

        // Data
        var dataHtml = '<table class="vs-table"><tbody>';
        dataHtml += '<tr><td>کلمات GSC</td><td><strong>' + d.data.keywords + '</strong></td></tr>';
        dataHtml += '<tr><td>صفحات یتیم</td><td><strong>' + d.data.orphans + '</strong></td></tr>';
        dataHtml += '<tr><td>بک‌لینک</td><td><strong>' + d.data.backlinks + '</strong></td></tr>';
        dataHtml += '<tr><td>تحلیل SERP</td><td><strong>' + d.data.serp_analyses + '</strong></td></tr>';
        dataHtml += '<tr><td>آخرین sync GSC</td><td>' + d.data.last_gsc_sync + '</td></tr>';
        dataHtml += '<tr><td>آخرین scan لینک</td><td>' + d.data.last_scan + '</td></tr>';
        dataHtml += '</tbody></table>';
        $('#vs-diag-data-content').html(dataHtml);

        // Env
        var envHtml = '<table class="vs-table"><tbody>';
        Object.keys(d.environment).forEach(function(k) {
            envHtml += '<tr><td>' + k + '</td><td><code>' + d.environment[k] + '</code></td></tr>';
        });
        envHtml += '</tbody></table>';
        $('#vs-diag-env-content').html(envHtml);
    });
});

$(document).on('click', '.vs-test-wh', function(){
    var $btn = $(this).prop('disabled', true);
    post('viraseo_test_n8n_webhook', {path: $(this).data('path')}, function(r) {
        $btn.prop('disabled', false);
        alert(r.success ? r.data : r.data);
    });
});

$(document).on('click', '#vs-repair-tables', function(){
    var $btn = $(this).prop('disabled', true).text('بازسازی...');
    post('viraseo_repair_tables', {}, function(r) {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> بازسازی جداول');
        alert(r.success ? r.data.message : (r.data || 'خطا'));
    });
});

// === CORE WEB VITALS ===
function vsCwvMetric(label, val, verdict) {
    const cls = verdict==='good'?'vs-cwv-good':(verdict==='poor'?'vs-cwv-poor':'vs-cwv-ni');
    return '<div class="vs-cwv-metric '+cls+'"><span class="vs-cwv-m-label">'+label+'</span><span class="vs-cwv-m-val">'+val+'</span></div>';
}
function vsCwvVerdictBadge(v, fa) {
    const cls = v==='good'?'vs-badge-green':(v==='poor'?'vs-badge-red':'vs-badge-orange');
    return '<span class="vs-badge '+cls+'">'+fa+'</span>';
}
function vsCwvDetailHtml(o) {
    let sug = (o.suggestions && o.suggestions.length)
        ? '<ul class="vs-checklist">'+o.suggestions.map(s=>'<li>'+s+'</li>').join('')+'</ul>'
        : '<div class="vs-chk-ok">✅ مشکل عمده‌ای یافت نشد.</div>';
    return '<div class="vs-cwv-detail-box">'
        + '<div class="vs-cwv-metrics">'
        + vsCwvMetric('LCP', o.lcp, o.v_lcp)
        + vsCwvMetric('INP', o.inp, o.v_inp)
        + vsCwvMetric('CLS', o.cls, o.v_cls)
        + vsCwvMetric('TTFB', o.ttfb, '')
        + '</div>'
        + '<div class="vs-hint" style="margin:6px 0">منبع داده: '+o.source+'</div>'
        + '<h4>🛠️ پیشنهادهای بهبود (به ترتیب اولویت):</h4>'+sug
        + '</div>';
}
$(document).on('click', '#vs-cwv-one', function(){
    const url = $('#vs-cwv-url').val().trim();
    if (!url) { toast('آدرس را وارد کنید.','err'); return; }
    const $b = $(this).prop('disabled', true);
    $('#vs-cwv-one-box').html('<div class="vs-empty">در حال اندازه‌گیری سرعت (تا ۶۰ ثانیه)...</div>');
    post('viraseo_cwv_check', {url:url, strategy:$('#vs-cwv-strategy').val()}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-cwv-one-box').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const o = r.data;
        $('#vs-cwv-one-box').html('<div class="vs-cwv-result"><div class="vs-cwv-head"><span class="vs-cwv-score vs-cwv-score-'+o.verdict+'">'+o.perf+'</span><div><strong dir="ltr">'+o.url+'</strong><br>'+vsCwvVerdictBadge(o.verdict,o.verdict_fa)+'</div></div>'+vsCwvDetailHtml(o)+'</div>');
    });
});
function vsCwvRow(o) {
    const title = o.title ? '<a href="'+(o.edit||o.url)+'" target="_blank">'+o.title+'</a>' : '<span dir="ltr">'+o.url+'</span>';
    const sc = '<span class="vs-cwv-score-sm vs-cwv-score-'+o.verdict+'">'+o.perf+'</span>';
    const mc = v => v==='good'?'style="color:#10b981"':(v==='poor'?'style="color:#ef4444"':'style="color:#f59e0b"');
    return '<tr class="vs-cwv-trow" data-text="'+escAttr((o.title||'')+' '+o.url)+'" data-verdict="'+o.verdict+'">'
        + '<td>'+title+(o.checked?' <span class="vs-hint">('+o.checked+')</span>':'')+'</td>'
        + '<td>'+sc+'</td>'
        + '<td '+mc(o.v_lcp)+'>'+o.lcp+'</td>'
        + '<td '+mc(o.v_inp)+'>'+o.inp+'</td>'
        + '<td '+mc(o.v_cls)+'>'+o.cls+'</td>'
        + '<td>'+o.ttfb+'</td>'
        + '<td>'+vsCwvVerdictBadge(o.verdict,o.verdict_fa)+'</td>'
        + '<td><button class="vs-btn vs-btn-sm vs-btn-secondary vs-cwv-toggle">پیشنهادها</button></td></tr>'
        + '<tr class="vs-cwv-detail" style="display:none"><td colspan="8">'+vsCwvDetailHtml(o)+'</td></tr>';
}
function vsCwvRender(rows) {
    const $t = $('#vs-cwv-tbody').empty();
    if (!rows.length) { $t.html('<tr class="vs-empty"><td colspan="8" class="vs-empty">موردی نیست.</td></tr>'); $('#vs-cwv-pager').empty(); return; }
    rows.forEach(o => $t.append(vsCwvRow(o)));
    vsCwvApplyFilter();
}
function vsCwvApplyFilter() {
    const q = ($('#vs-cwv-filter').val()||'').toLowerCase();
    const v = $('#vs-cwv-vfilter').val()||'';
    $('#vs-cwv-tbody tr.vs-cwv-trow').each(function(){
        const $row = $(this), $detail = $row.next('.vs-cwv-detail');
        const okText = !q || ($row.data('text')||'').toString().toLowerCase().indexOf(q) > -1;
        const okV = !v || $row.data('verdict') === v;
        if (okText && okV) { $row.show(); } else { $row.hide(); $detail.hide(); }
    });
}
$(document).on('input', '#vs-cwv-filter', vsCwvApplyFilter);
$(document).on('change', '#vs-cwv-vfilter', vsCwvApplyFilter);
$(document).on('click', '.vs-cwv-toggle', function(){
    $(this).closest('tr').next('.vs-cwv-detail').toggle();
});
$(document).on('click', '#vs-cwv-batch', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-cwv-tbody').html('<tr class="vs-empty"><td colspan="8" class="vs-empty">در حال بررسی دسته‌ای (ممکن است چند دقیقه طول بکشد)...</td></tr>');
    post('viraseo_cwv_batch', {limit:$('#vs-cwv-limit').val()||5, strategy:$('#vs-cwv-batch-strategy').val()}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-cwv-tbody').html('<tr class="vs-empty"><td colspan="8" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        $('#vs-cwv-summary').text('خوب: '+r.data.good+' · ضعیف: '+r.data.poor+' · مجموع: '+r.data.total+(r.data.errors?' · خطا: '+r.data.errors:''));
        vsCwvRender(r.data.rows);
    });
});
$(document).on('click', '#vs-cwv-load', function(){
    $('#vs-cwv-tbody').html('<tr class="vs-empty"><td colspan="8" class="vs-empty">در حال بارگذاری...</td></tr>');
    post('viraseo_cwv_list', {strategy:$('#vs-cwv-batch-strategy').val()}, r => {
        if (!r.success) { $('#vs-cwv-tbody').html('<tr class="vs-empty"><td colspan="8" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        $('#vs-cwv-summary').text('نتایج ذخیره‌شده: '+r.data.rows.length);
        vsCwvRender(r.data.rows);
    });
});

// === CANNIBALIZATION (AI + auto-merge) ===
function vsCanLoad() {
    if (!$('#vs-can-list').length) return;
    $('#vs-can-list').html('<div class="vs-empty">در حال بارگذاری...</div>');
    post('viraseo_cannibal_list', {status:$('#vs-can-status').val()}, r => {
        const $l = $('#vs-can-list').empty();
        if (!r.success) { $l.html('<div class="vs-empty">'+(r.data||'خطا')+'</div>'); return; }
        if (!r.data.rows.length) { $l.html('<div class="vs-empty">🎉 موردی در این وضعیت نیست.</div>'); return; }
        r.data.rows.forEach(c => $l.append(vsCanCard(c)));
        vsCanFilter();
    });
}
function vsCanPageBox(p, isWinner, cid, sel) {
    const crown = isWinner ? ' 👑' : '';
    const t = p.pid ? '<a href="'+(p.url)+'" target="_blank">'+p.title+'</a>' : '<span dir="ltr">'+p.url+'</span>';
    return '<label class="vs-can-page'+(isWinner?' vs-can-winner':'')+'">'
        + '<input type="radio" name="vs-can-w-'+cid+'" value="'+sel+'"'+(isWinner?' checked':'')+'> '
        + '<div><div class="vs-can-page-title">'+t+crown+'</div>'
        + '<div class="vs-hint">جایگاه: '+p.pos+' · نمایش: '+p.imp+'</div></div></label>';
}
function vsCanCard(c) {
    const sevCls = c.severity==='critical'?'vs-badge-red':(c.severity==='warning'?'vs-badge-orange':'vs-badge-blue');
    const aiBtn = V.aiEnabled ? '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-can-ai" data-id="'+c.id+'">🤖 تحلیل و توصیه‌ی AI</button>' : '';
    return '<div class="vs-card vs-can-card" data-kw="'+escAttr(c.keyword)+'" style="margin-bottom:14px">'
        + '<div class="vs-can-head"><h3 class="vs-card-title" style="margin:0">⚔️ «'+c.keyword+'»</h3>'
        + '<span class="vs-badge '+sevCls+'">'+c.severity_fa+'</span>'
        + '<span class="vs-hint">پیشنهاد: '+c.action_fa+' · '+c.detected+'</span></div>'
        + '<div class="vs-can-pages">'
        + vsCanPageBox(c.page_1, c.winner===1, c.id, 1)
        + '<div class="vs-can-vs">VS</div>'
        + vsCanPageBox(c.page_2, c.winner===2, c.id, 2)
        + '</div>'
        + '<div class="vs-can-actions">'
        + '<span class="vs-hint">برنده را انتخاب و روش ادغام را بزنید:</span> '
        + '<button class="vs-btn vs-btn-sm vs-btn-secondary vs-can-merge" data-id="'+c.id+'" data-mode="canonical">کانونیکال</button> '
        + '<button class="vs-btn vs-btn-sm vs-btn-primary vs-can-merge" data-id="'+c.id+'" data-mode="redirect">ریدایرکت ۳۰۱</button> '
        + '<button class="vs-btn vs-btn-sm vs-btn-danger vs-can-merge" data-id="'+c.id+'" data-mode="merge">ادغام کامل محتوا</button> '
        + aiBtn
        + ' <button class="vs-btn vs-btn-sm vs-btn-secondary vs-can-ignore" data-id="'+c.id+'">نادیده بگیر</button>'
        + '</div>'
        + '<div class="vs-can-ai-box" style="display:none"></div>'
        + '</div>';
}
function vsCanFilter() {
    const q = ($('#vs-can-filter').val()||'').toLowerCase();
    $('.vs-can-card').each(function(){
        $(this).toggle(!q || ($(this).data('kw')||'').toString().toLowerCase().indexOf(q) > -1);
    });
}
$(document).on('input', '#vs-can-filter', vsCanFilter);
$(document).on('change', '#vs-can-status', vsCanLoad);
$(document).on('click', '#vs-can-detect', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-can-status-msg').text('در حال شناسایی...');
    post('viraseo_cannibal_detect', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-can-status-msg').text(''); toast(r.data||'خطا','err'); return; }
        $('#vs-can-status-msg').text('');
        toast(r.data.detected+' تعارض جدید شناسایی شد.', 'success');
        $('#vs-can-status').val('detected');
        vsCanLoad();
    });
});
$(document).on('click', '.vs-can-merge', function(){
    const $card = $(this).closest('.vs-can-card');
    const id = $(this).data('id'), mode = $(this).data('mode');
    const winner = $card.find('input[name="vs-can-w-'+id+'"]:checked').val() || 1;
    const labels = {canonical:'کانونیکال', redirect:'ریدایرکت ۳۰۱', merge:'ادغام کامل محتوا (صفحه‌ی بازنده پیش‌نویس می‌شود)'};
    if (!confirm('آیا مطمئنید؟ روش: '+labels[mode]+'\nاین عمل صفحه‌ی بازنده را به صفحه‌ی برنده هدایت می‌کند.')) return;
    const $b = $(this).prop('disabled', true);
    post('viraseo_cannibal_merge', {id:id, mode:mode, winner:winner}, r => {
        $b.prop('disabled', false);
        if (!r.success) { toast(r.data||'خطا','err'); return; }
        toast(r.data.message,'success');
        $card.fadeOut(300, function(){ $(this).remove(); });
    });
});
$(document).on('click', '.vs-can-ignore', function(){
    const id = $(this).data('id'); const $card = $(this).closest('.vs-can-card');
    post('viraseo_cannibal_resolve', {id:id, status:'ignored'}, r => {
        if (r.success) $card.fadeOut(300, function(){ $(this).remove(); });
    });
});
$(document).on('click', '.vs-can-ai', function(){
    const $box = $(this).closest('.vs-can-card').find('.vs-can-ai-box').show().html('<div class="vs-empty">🤖 در حال تحلیل تعارض...</div>');
    post('viraseo_cannibal_ai', {id:$(this).data('id')}, r => {
        if (!r.success) { $box.html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const html = (r.data.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        $box.html('<div class="vs-ai-output"><div class="vs-ai-head">🤖 توصیه‌ی هوش مصنوعی <span class="vs-hint">هزینه: $'+(r.data.cost||0)+'</span></div><div class="vs-ai-body">'+html+'</div></div>');
    });
});
$(vsCanLoad);

// === CRAWL & HOST HEALTH ===
$(document).on('click', '#vs-crawl-run', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-crawl-list').html('<div class="vs-empty">در حال بررسی خزش و هاست (چند ثانیه)...</div>');
    post('viraseo_crawl_check', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-crawl-list').html('<div class="vs-alert vs-alert-danger"><span class="dashicons dashicons-dismiss"></span><p>'+(r.data||'خطا')+'</p></div>'); return; }
        const sc = r.data.score, scColor = sc >= 75 ? '#10b981' : (sc >= 45 ? '#f59e0b' : '#ef4444');
        $('#vs-crawl-score').html('<span class="vs-health-label">سلامت خزش</span><span class="vs-health-score" style="color:'+scColor+'">'+sc+'/۱۰۰</span>');
        const icon = {ok:'✅', warn:'⚠️', bad:'⛔', info:'ℹ️'};
        const cls = {ok:'vs-crawl-ok', warn:'vs-crawl-warn', bad:'vs-crawl-bad', info:'vs-crawl-info'};
        const $l = $('#vs-crawl-list').empty();
        // Sort: bad first, then warn, then info, then ok
        const order = {bad:0, warn:1, info:2, ok:3};
        const rows = r.data.checks.slice().sort((a,b)=>order[a.status]-order[b.status]);
        rows.forEach(c => {
            $l.append('<div class="vs-crawl-item '+(cls[c.status]||'')+'">'
                + '<div class="vs-crawl-icon">'+(icon[c.status]||'')+'</div>'
                + '<div class="vs-crawl-body"><div class="vs-crawl-title">'+c.title+'</div>'
                + '<div class="vs-crawl-detail">'+c.detail+'</div>'
                + (c.fix ? '<div class="vs-crawl-fix">🛠️ راهکار: '+c.fix+'</div>' : '')
                + '</div></div>');
        });
    });
});

})(jQuery);


/* Toast CSS */
(function(){var s=document.createElement('style');s.textContent='.vs-toast{position:fixed;bottom:24px;left:24px;padding:14px 24px;border-radius:10px;font-size:13px;z-index:99999;opacity:0;transform:translateY(10px);transition:all .3s;font-family:var(--vs-font);max-width:400px;direction:rtl}.vs-toast.show{opacity:1;transform:none}.vs-toast-success{background:#065f46;color:#6ee7b7;border:1px solid #10b981}.vs-toast-err{background:#7f1d1d;color:#fca5a5;border:1px solid #ef4444}.vs-toast-info{background:#1e3a5f;color:#7dd3fc;border:1px solid #0ea5e9}';document.head.appendChild(s)})();
