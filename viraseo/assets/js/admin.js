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
    const $wrap = $(this).closest('.vs-wrap');
    $(this).addClass('active').siblings().removeClass('active');
    $wrap.find('.vs-tab-panel').removeClass('active');
    // Support BOTH conventions: id="panel-X" AND data-panel="X"
    var $panel = $('#panel-' + t);
    if (!$panel.length) $panel = $wrap.find('.vs-tab-panel[data-panel="' + t + '"]');
    $panel.addClass('active');
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
function loadKeywords(search, page) {
    post('viraseo_get_keywords', {search: search||$('#vs-kw-search').val()||'', page: page||1, orderby: vsKwSort.orderby, order: vsKwSort.order}, r => {
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
$(document).on('click', '.vs-sort', function(){
    const col = $(this).data('sort');
    if (vsKwSort.orderby === col) vsKwSort.order = (vsKwSort.order === 'asc' ? 'desc' : 'asc');
    else { vsKwSort.orderby = col; vsKwSort.order = 'desc'; }
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
        d.competitors.forEach(c => { $t.append(`<tr class="vs-serp-row" data-url="${c.url}" title="برای تحلیل دقیق این صفحه کلیک کنید"><td>${c.pos}</td><td>${c.domain}</td><td>${c.title||'-'}</td><td>${c.words} <span class="vs-snippet-note">(اسنیپت)</span></td><td>${c.h1}/${c.h2}/${c.h3}</td><td><span class="dashicons dashicons-search" style="color:var(--vs-primary)"></span></td></tr>`); });
        const $l = $('#vs-lsi-tags').empty();
        (d.lsi||[]).forEach(w => $l.append(`<span class="vs-tag">${w}</span>`));
        const $g = $('#vs-gap-list').empty();
        (d.gap||[]).forEach(g => $g.append(`<li>${g}</li>`));
    });
}


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
        let h2list = (d.h2_texts||[]).map(t=>'<li>'+t+'</li>').join('') || '<li class="vs-empty">—</li>';
        let schema = (d.schema||[]).length ? (d.schema||[]).map(s=>'<span class="vs-tag">'+s+'</span>').join('') : '<span class="vs-empty">ندارد</span>';
        $detail.find('td').html(
            '<div class="vs-inspect">'
            + '<div class="vs-inspect-grid">'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.word_count_fa+'</span><span class="vs-im-lbl">تعداد دقیق کلمات</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.h1+'/'+d.h2+'/'+d.h3+'</span><span class="vs-im-lbl">H1/H2/H3</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.images+'</span><span class="vs-im-lbl">تصاویر ('+d.images_no_alt+' بدون alt)</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.internal_links+'/'+d.external_links+'</span><span class="vs-im-lbl">لینک داخلی/خارجی</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.paragraphs+'</span><span class="vs-im-lbl">پاراگراف</span></div>'
            +   '<div class="vs-inspect-metric"><span class="vs-im-num">'+d.word_count_score+'/۱۰۰</span><span class="vs-im-lbl">امتیاز محتوا</span></div>'
            + '</div>'
            + '<div class="vs-inspect-cols">'
            +   '<div><h4>ساختار هدینگ‌ها (H2):</h4><ul class="vs-inspect-h2">'+h2list+'</ul></div>'
            +   '<div><h4>عنوان صفحه (Title):</h4><p class="vs-inspect-title">'+(d.title||'—')+'</p>'
            +       '<h4>توضیحات متا:</h4><p class="vs-inspect-desc">'+(d.meta_desc||'—')+'</p>'
            +       '<h4>اسکیما (Schema):</h4><div class="vs-tags">'+schema+'</div></div>'
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
    post('viraseo_get_orphans', {}, r => {
        if (!r.success) return;
        const $t = $('#vs-orphans-tbody').empty();
        if (!r.data.rows || !r.data.rows.length) { 
            $t.html('<tr><td colspan="5" class="vs-empty">🎉 عالی! هیچ صفحه یتیمی یافت نشد. همه صفحات حداقل ۳ لینک ورودی دارند.</td></tr>'); 
            return; 
        }
        r.data.rows.forEach(o => {
            $t.append(`<tr><td><a href="${o.url}" target="_blank">${o.title}</a></td><td>${o.type}</td><td>${o.inlinks}</td><td>${o.outlinks}</td><td><a href="${o.edit}" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>`);
        });
    });
}
function loadSuggestions() {
    post('viraseo_get_suggestions', {type: window._vsSuggType||''}, r => {
        if (!r.success) return;
        const $c = $('#vs-suggestions-list').empty();
        if (r.data.counts) {
            $('#vs-cnt-all').text(r.data.counts.all||0);
            $('#vs-cnt-exact').text(r.data.counts.exact||0);
            $('#vs-cnt-partial').text(r.data.counts.partial||0);
            $('#vs-cnt-semantic').text(r.data.counts.semantic||0);
        }
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
                    <div class="vs-flow-node"><small>از (مبدا):</small><a href="${s.source_edit}" target="_blank">${s.source}</a></div>
                    <span class="vs-flow-arrow">→</span>
                    <div class="vs-flow-node"><small>به (مقصد):</small><a href="${s.target_url}" target="_blank">${s.target}</a></div>
                </div>
                <div class="vs-suggestion-anchor-row">انکر پیشنهادی: <span class="vs-suggestion-anchor">${s.anchor}</span></div>
                <div class="vs-suggestion-reason">${s.reason||''}</div>
                <div class="vs-row"><button class="vs-btn vs-btn-sm vs-btn-primary vs-apply-link" data-id="${s.id}">⚡ درج خودکار</button><button class="vs-btn vs-btn-sm vs-btn-success vs-accept-link" data-id="${s.id}">✓ تأیید دستی</button><button class="vs-btn vs-btn-sm vs-btn-danger vs-reject-link" data-id="${s.id}">✗ رد</button></div>
            </div>`);
        });
    });
}
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

// Topical clusters
$(document).on('click', '#vs-load-clusters', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-clusters-list').html('<div class="vs-empty">در حال محاسبه خوشه‌ها...</div>');
    post('viraseo_link_clusters', {}, r => {
        $b.prop('disabled', false);
        if (!r.success) { $('#vs-clusters-list').html('<div class="vs-empty">خطا</div>'); return; }
        const $l = $('#vs-clusters-list').empty();
        if (!r.data.clusters.length) { $l.html('<div class="vs-empty">خوشه‌ای یافت نشد (حداقل ۲ صفحه با موضوع مشترک لازم است).</div>'); return; }
        r.data.clusters.forEach(c => {
            let members = c.members.map(m => '<li><a href="'+m.url+'" target="_blank">'+m.title+'</a></li>').join('');
            $l.append('<div class="vs-cluster"><div class="vs-cluster-head"><span class="vs-badge vs-badge-blue">'+c.keyword+'</span> <span class="vs-cluster-count">'+c.count+' صفحه</span></div><div class="vs-cluster-pillar">🏛️ ستون پیشنهادی: <a href="'+c.pillar.url+'" target="_blank"><strong>'+c.pillar.title+'</strong></a></div><ul class="vs-cluster-members">'+members+'</ul></div>');
        });
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
        let checks = r.data.checklist.map(c => '<li>'+c+'</li>').join('');
        $d.find('td').html(
            '<div class="vs-fc-detail-box"><div class="vs-row" style="gap:24px;align-items:flex-start">'
            + '<div style="flex:2;min-width:280px"><h4>📊 کلمات دیگری که این صفحه می‌گیرد (فرصت رشد):</h4><table class="vs-table"><thead><tr><th>کلمه</th><th>جایگاه</th><th>نمایش</th><th>کلیک</th></tr></thead><tbody>'+kws+'</tbody></table></div>'
            + '<div style="flex:1;min-width:220px"><h4>✅ اقدامات پیشنهادی برای افزایش ترافیک:</h4><ul class="vs-checklist">'+checks+'</ul></div>'
            + '</div></div>'
        );
    });
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
    $('#vs-linkopp-tbody').html('<tr><td colspan="6" class="vs-empty">در حال محاسبه...</td></tr>');
    post('viraseo_link_opportunities', {}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-linkopp-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="6" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">🎉 فرصت پرپتانسیلی یافت نشد (همه صفحات پربازدید لینک کافی دارند).</td></tr>'); return; }
        r.data.rows.forEach(o => {
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td><strong style="color:var(--vs-success)">'+o.impressions+'</strong></td><td>'+o.clicks+'</td><td>'+o.position+'</td><td><span class="vs-badge vs-badge-'+(o.inlinks_raw===0?'red':'orange')+'">'+o.inlinks+'</span></td><td><a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">ویرایش</a></td></tr>');
        });
    });
});
$(document).on('click', '#vs-load-thin', function(){
    const $b = $(this).prop('disabled', true);
    $('#vs-thin-tbody').html('<tr><td colspan="6" class="vs-empty">در حال بررسی...</td></tr>');
    post('viraseo_thin_content', {threshold: $('#vs-thin-threshold').val()}, r => {
        $b.prop('disabled', false);
        const $t = $('#vs-thin-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="6" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        if (!r.data.rows.length) { $t.html('<tr><td colspan="6" class="vs-empty">🎉 محتوای ضعیفی یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            const pc = o.priority === 'بالا' ? 'red' : (o.priority === 'متوسط' ? 'orange' : 'blue');
            $t.append('<tr><td><a href="'+o.url+'" target="_blank">'+o.title+'</a></td><td>'+o.type+'</td><td><strong style="color:'+(o.words<150?'#ef4444':'#f59e0b')+'">'+o.words_fa+'</strong></td><td>'+o.impressions+'</td><td><span class="vs-badge vs-badge-'+pc+'">'+o.priority+'</span></td><td><a href="'+o.edit+'" class="vs-btn vs-btn-sm vs-btn-secondary">بازنویسی</a></td></tr>');
        });
    });
});

// === TARGET KEYWORDS MANAGEMENT ===
function loadTargets() {
    if (!$('#vs-tg-tbody').length) return;
    $('#vs-tg-tbody').html('<tr><td colspan="8" class="vs-empty">در حال بارگذاری...</td></tr>');
    post('viraseo_targets_list', {search: $('#vs-tg-search').val()||''}, r => {
        const $t = $('#vs-tg-tbody').empty();
        if (!r.success) { $t.html('<tr><td colspan="8" class="vs-empty">'+(r.data||'خطا')+'</td></tr>'); return; }
        if (!r.data.rows.length) { $t.html('<tr><td colspan="8" class="vs-empty">صفحه‌ای یافت نشد.</td></tr>'); return; }
        r.data.rows.forEach(o => {
            let stats = o.stats ? ('کلیک '+o.stats.clicks+' · نمایش '+o.stats.impressions+' · جایگاه '+o.stats.position) : '<span class="vs-empty">—</span>';
            let suggest = o.suggest ? ('<span class="vs-tag">'+o.suggest+'</span> <button class="vs-btn vs-btn-sm vs-btn-secondary vs-tg-use" data-id="'+o.id+'" data-kw="'+escAttr(o.suggest)+'">استفاده</button>') : '<span class="vs-empty">—</span>';
            let serpBtn = o.current ? '<a class="vs-btn vs-btn-sm vs-btn-primary" href="admin.php?page=viraseo-serp&keyword='+encodeURIComponent(o.current)+'&post='+o.id+'&autostart=1" title="تحلیل SERP این کلمه و ذخیره نتیجه برای این صفحه">🔍 تحلیل SERP</a>' : '';
            let intentCell = o.serp_intent ? ('<span class="vs-badge vs-badge-blue">'+o.serp_intent.label+'</span>'+(o.serp_intent.avg_words?'<br><small style="color:var(--vs-text-muted)">میانگین کلمات رقبا: '+o.serp_intent.avg_words+'</small>':'')+(o.serp_intent.rec?'<br><small style="color:var(--vs-text-muted)">'+o.serp_intent.rec+'</small>':'')) : '<span class="vs-empty">هنوز تحلیل نشده</span>';
            $t.append('<tr>'
                + '<td><a href="'+o.edit+'">'+o.title+'</a><br><small style="color:var(--vs-text-muted)">'+o.type+'</small></td>'
                + '<td><input type="text" class="vs-input vs-tg-kw" data-id="'+o.id+'" value="'+escAttr(o.current)+'" style="min-width:160px" placeholder="کلمه هدف..."></td>'
                + '<td><span class="vs-badge vs-badge-blue">'+o.source+'</span></td>'
                + '<td>'+linkScoreBar(o.link_score)+'</td>'
                + '<td style="font-size:11px">'+stats+'</td>'
                + '<td style="font-size:11px;max-width:240px">'+intentCell+'</td>'
                + '<td>'+suggest+'</td>'
                + '<td><button class="vs-btn vs-btn-sm vs-btn-success vs-tg-save" data-id="'+o.id+'">ذخیره</button> '+serpBtn+'</td>'
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
$(document).on('click', '#vs-tg-reload', loadTargets);
$(document).on('keyup', '#vs-tg-search', function(e){ if (e.key === 'Enter') loadTargets(); });
$(document).on('click', '.vs-tg-use', function(){
    const $row = $(this).closest('tr');
    $row.find('.vs-tg-kw').val($(this).data('kw'));
});
$(document).on('click', '.vs-tg-save', function(){
    const id = $(this).data('id');
    const kw = $(this).closest('tr').find('.vs-tg-kw').val();
    const $b = $(this).prop('disabled', true).text('...');
    post('viraseo_target_save', {id: id, keyword: kw}, r => {
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

})(jQuery);


/* Toast CSS */
(function(){var s=document.createElement('style');s.textContent='.vs-toast{position:fixed;bottom:24px;left:24px;padding:14px 24px;border-radius:10px;font-size:13px;z-index:99999;opacity:0;transform:translateY(10px);transition:all .3s;font-family:var(--vs-font);max-width:400px;direction:rtl}.vs-toast.show{opacity:1;transform:none}.vs-toast-success{background:#065f46;color:#6ee7b7;border:1px solid #10b981}.vs-toast-err{background:#7f1d1d;color:#fca5a5;border:1px solid #ef4444}.vs-toast-info{background:#1e3a5f;color:#7dd3fc;border:1px solid #0ea5e9}';document.head.appendChild(s)})();
