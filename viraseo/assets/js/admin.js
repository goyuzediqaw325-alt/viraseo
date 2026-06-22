(function($){
'use strict';
var V = window.viraseo || {};
if (!V.ajax_url) return;

// ======= TAB SWITCHING =======
$(document).on('click', '.viraseo-tab', function(e){
    e.preventDefault();
    var tab = $(this).data('tab');
    if (!tab) return;
    $(this).addClass('active').siblings().removeClass('active');
    $(this).closest('.viraseo-tabs-wrapper').find('.viraseo-tab-content').removeClass('active');
    $('#tab-' + tab).addClass('active');
});

// ======= SETTINGS: TEST N8N =======
$(document).on('click', '#viraseo-test-n8n', function(){
    var $r = $('#viraseo-test-result').text(V.strings.loading).css('color','');
    $.post(V.ajax_url, {action:'viraseo_test_n8n', nonce:V.nonce}, function(res){
        if (res.success) $r.text(res.data.message).css('color','#059669');
        else $r.text(res.data.message || res.data).css('color','#dc2626');
    });
});

// ======= GSC SYNC =======
$(document).on('click', '#viraseo-sync-gsc', function(){
    var $s = $('#viraseo-sync-status').text(V.strings.loading);
    $.post(V.ajax_url, {action:'viraseo_trigger_gsc_sync', nonce:V.nonce}, function(res){
        $s.text(res.success ? res.data.message : (res.data||V.strings.error));
    });
});

// ======= KEYWORD SEARCH =======
$(document).on('keyup', '#viraseo-kw-search', function(){
    var q = $(this).val();
    $.post(V.ajax_url, {action:'viraseo_get_gsc_keywords', nonce:V.nonce, search:q}, function(res){
        if (!res.success) return;
        var $t = $('#viraseo-kw-tbody').empty();
        if (!res.data.keywords.length) {
            $t.html('<tr><td colspan="6" class="viraseo-empty-state">نتیجه‌ای یافت نشد.</td></tr>');
            return;
        }
        res.data.keywords.forEach(function(k){
            $t.append('<tr><td>'+k.keyword+(k.is_striking?' ⭐':'')+'</td><td>'+k.clicks+'</td><td>'+k.impressions+'</td><td>'+k.ctr+'</td><td>'+k.position+'</td><td><a href="'+k.page_url+'" target="_blank">↗</a></td></tr>');
        });
    });
});


// ======= SERP ANALYSIS =======
$(document).on('click', '#viraseo-start-serp', function(){
    var kw = $('#viraseo-serp-keyword').val().trim();
    if (!kw) return;
    $('#viraseo-serp-progress').show();
    $.post(V.ajax_url, {action:'viraseo_start_serp', nonce:V.nonce, keyword:kw}, function(res){
        if (!res.success) { $('#viraseo-serp-progress').hide(); alert(res.data||V.strings.error); return; }
        pollSerp(res.data.analysis_id);
    });
});
function pollSerp(id){
    var iv = setInterval(function(){
        $.post(V.ajax_url, {action:'viraseo_check_serp_status', nonce:V.nonce, analysis_id:id}, function(res){
            if (!res.success) return;
            if (res.data.status === 'completed') { clearInterval(iv); loadSerp(id); }
            else if (res.data.status === 'failed') { clearInterval(iv); $('#viraseo-serp-progress').hide(); alert('خطا در تحلیل'); }
        });
    }, 5000);
}
function loadSerp(id){
    $.post(V.ajax_url, {action:'viraseo_get_serp_results', nonce:V.nonce, analysis_id:id}, function(res){
        $('#viraseo-serp-progress').hide();
        if (!res.success || res.data.status !== 'completed') return;
        var d = res.data;
        $('#viraseo-serp-results').show();
        $('#viraseo-serp-summary').html(
            '<div class="viraseo-stat-card"><div class="viraseo-stat-content"><span class="viraseo-stat-number">'+d.avg_content_length+'</span><span class="viraseo-stat-label">میانگین کلمات</span></div></div>'+
            '<div class="viraseo-stat-card"><div class="viraseo-stat-content"><span class="viraseo-stat-number">'+d.avg_headings+'</span><span class="viraseo-stat-label">میانگین هدینگ</span></div></div>'+
            '<div class="viraseo-stat-card"><div class="viraseo-stat-content"><span class="viraseo-stat-number">'+d.competitors.length+'</span><span class="viraseo-stat-label">رقیب</span></div></div>'
        );
        var $t = $('#viraseo-serp-tbody').empty();
        d.competitors.forEach(function(c){
            $t.append('<tr><td>'+c.position+'</td><td>'+c.domain+'</td><td>'+c.title+'</td><td>'+c.word_count+'</td><td>'+c.h1_count+'</td><td>'+c.h2_count+'</td><td>'+c.h3_count+'</td><td>'+c.images+'</td></tr>');
        });
        var $l = $('#viraseo-lsi-tags').empty();
        (d.lsi_keywords||[]).forEach(function(w){ $l.append('<span class="viraseo-tag">'+w+'</span>'); });
        var $g = $('#viraseo-content-gap').empty();
        (d.content_gap||[]).forEach(function(g){ $g.append('<li>'+g+'</li>'); });
    });
}

// ======= INTERNAL LINKS =======
$(document).on('click', '#viraseo-scan-links', function(){
    var $s = $('#viraseo-scan-status').text(V.strings.loading);
    $.post(V.ajax_url, {action:'viraseo_trigger_orphan_scan', nonce:V.nonce}, function(res){
        $s.text(res.success ? res.data.message : (res.data||V.strings.error));
        if (res.success) loadOrphans();
    });
});
function loadOrphans(){
    $.post(V.ajax_url, {action:'viraseo_get_orphan_pages', nonce:V.nonce, status:'orphan'}, function(res){
        if (!res.success) return;
        var $t = $('#viraseo-orphans-tbody').empty();
        res.data.orphans.forEach(function(o){
            $t.append('<tr><td><a href="'+o.permalink+'" target="_blank">'+o.title+'</a></td><td>'+o.post_type+'</td><td>'+o.inlinks+'</td><td>'+o.outlinks+'</td><td><a href="'+o.edit_url+'" class="button button-small">ویرایش</a></td></tr>');
        });
    });
}
function loadSuggestions(){
    $.post(V.ajax_url, {action:'viraseo_get_link_suggestions', nonce:V.nonce}, function(res){
        if (!res.success) return;
        var $c = $('#viraseo-suggestions-list').empty();
        if (!res.data.suggestions.length) { $c.html('<div class="viraseo-empty-state">پیشنهادی وجود ندارد.</div>'); return; }
        res.data.suggestions.forEach(function(s){
            $c.append('<div class="viraseo-suggestion-card"><strong>از:</strong> '+s.source_title+' <strong>→ به:</strong> '+s.target_title+'<br><span class="viraseo-tag">'+s.anchor+'</span> ('+Math.round(s.relevance)+'%)<br><button class="button button-small viraseo-accept-sug" data-id="'+s.id+'">✓ پذیرش</button> <button class="button button-small viraseo-reject-sug" data-id="'+s.id+'">✗ رد</button></div>');
        });
    });
}
$(document).on('click', '.viraseo-accept-sug', function(){
    $.post(V.ajax_url, {action:'viraseo_accept_suggestion', nonce:V.nonce, suggestion_id:$(this).data('id')});
    $(this).closest('.viraseo-suggestion-card').fadeOut();
});
$(document).on('click', '.viraseo-reject-sug', function(){
    $.post(V.ajax_url, {action:'viraseo_reject_suggestion', nonce:V.nonce, suggestion_id:$(this).data('id')});
    $(this).closest('.viraseo-suggestion-card').fadeOut();
});
if ($('#viraseo-orphans-tbody').length) { loadOrphans(); loadSuggestions(); }


// ======= BACKLINKS =======
if ($('#viraseo-bl-tbody').length) { loadBacklinks(); loadDisavow(); }
function loadBacklinks(){
    $.post(V.ajax_url, {action:'viraseo_get_backlinks', nonce:V.nonce}, function(res){
        if (!res.success) return;
        var $t = $('#viraseo-bl-tbody').empty();
        if (!res.data.backlinks.length) { $t.html('<tr><td colspan="8" class="viraseo-empty-state">بک‌لینکی ثبت نشده.</td></tr>'); return; }
        res.data.backlinks.forEach(function(b){
            $t.append('<tr><td>'+b.source_domain+'</td><td>'+b.anchor_text+'</td><td>'+b.link_type+'</td><td>'+b.domain_authority+'</td><td>'+b.cost+'</td><td>'+b.link_status+'</td><td>'+b.date_jalali+'</td><td><button class="button button-small viraseo-del-bl" data-id="'+b.id+'">×</button></td></tr>');
        });
    });
}
function loadDisavow(){
    $.post(V.ajax_url, {action:'viraseo_get_disavow_list', nonce:V.nonce}, function(res){
        if (!res.success) return;
        var $t = $('#viraseo-disavow-tbody').empty();
        if (!res.data.entries.length) { $t.html('<tr><td colspan="3" class="viraseo-empty-state">لیست خالی.</td></tr>'); return; }
        res.data.entries.forEach(function(e){
            $t.append('<tr><td dir="ltr">'+e.domain_or_url+'</td><td>'+e.type+'</td><td>'+e.reason+'</td></tr>');
        });
    });
}
$(document).on('click', '.viraseo-del-bl', function(){
    if (!confirm(V.strings.confirm_delete)) return;
    var $row = $(this).closest('tr');
    $.post(V.ajax_url, {action:'viraseo_delete_backlink', nonce:V.nonce, backlink_id:$(this).data('id')}, function(){ $row.fadeOut(); });
});
$(document).on('click', '#viraseo-add-disavow', function(){
    $.post(V.ajax_url, {action:'viraseo_add_disavow', nonce:V.nonce, domain_or_url:$('#viraseo-disavow-input').val(), disavow_type:$('#viraseo-disavow-type').val(), reason:$('#viraseo-disavow-reason').val()}, function(res){
        if (res.success) { $('#viraseo-disavow-input').val(''); $('#viraseo-disavow-reason').val(''); loadDisavow(); }
        else alert(res.data || V.strings.error);
    });
});
$(document).on('click', '#viraseo-gen-disavow', function(){
    $.post(V.ajax_url, {action:'viraseo_generate_disavow', nonce:V.nonce}, function(res){
        if (res.success) { $('#viraseo-disavow-preview').show().text(res.data.content); }
        else alert(res.data || V.strings.error);
    });
});

// ======= TRAFFIC FORECAST =======
$(document).on('click', '#viraseo-fc-calc', function(){
    var $t = $('#viraseo-fc-tbody').html('<tr><td colspan="6" class="viraseo-empty-state">'+V.strings.loading+'</td></tr>');
    $.post(V.ajax_url, {action:'viraseo_get_forecast', nonce:V.nonce, target_position:$('#viraseo-fc-target').val()}, function(res){
        if (!res.success) { $t.html('<tr><td colspan="6">'+V.strings.error+'</td></tr>'); return; }
        var $tb = $('#viraseo-fc-tbody').empty();
        res.data.forecasts.forEach(function(f){
            $tb.append('<tr><td>'+f.keyword+'</td><td>'+f.current_position+'</td><td>'+f.impressions+'</td><td>'+f.current_clicks+'</td><td>'+f.potential_clicks+'</td><td style="color:#059669;font-weight:bold;">+'+f.traffic_growth+'</td></tr>');
        });
    });
});

// ======= KEYWORD DISCOVERY =======
$(document).on('click', '#viraseo-start-discover', function(){
    var kw = $('#viraseo-seed-kw').val().trim();
    if (!kw) return;
    var $s = $('#viraseo-disc-status').text(V.strings.loading);
    $.post(V.ajax_url, {action:'viraseo_discover_keywords', nonce:V.nonce, seed_keyword:kw}, function(res){
        if (!res.success) { $s.text(res.data||V.strings.error); return; }
        $s.text(res.data.message);
        window._vsDiscId = res.data.discovery_id;
        pollDiscovery();
    });
});
function pollDiscovery(){
    var iv = setInterval(function(){
        $.post(V.ajax_url, {action:'viraseo_get_keyword_ideas', nonce:V.nonce, discovery_id:window._vsDiscId}, function(res){
            if (!res.success) return;
            if (res.data.status === 'completed') { clearInterval(iv); showIdeas(res.data); }
        });
    }, 4000);
}
function showIdeas(d){
    $('#viraseo-disc-status').text('');
    $('#viraseo-disc-results').show();
    var $t = $('#viraseo-disc-tbody').empty();
    d.ideas.forEach(function(i){
        $t.append('<tr><td><input type="checkbox" class="viraseo-disc-cb" value="'+i.id+'" /></td><td>'+i.keyword+'</td><td>'+i.source+'</td><td>'+i.relevance+'%</td><td>'+(i.is_question?'؟':'')+'</td></tr>');
    });
}
$(document).on('change', '#viraseo-disc-all', function(){ $('.viraseo-disc-cb').prop('checked', $(this).is(':checked')); updateBrief(); });
$(document).on('change', '.viraseo-disc-cb', function(){ updateBrief(); });
function updateBrief(){ $('#viraseo-gen-brief').prop('disabled', !$('.viraseo-disc-cb:checked').length); }
$(document).on('click', '#viraseo-gen-brief', function(){
    var ids = []; $('.viraseo-disc-cb:checked').each(function(){ ids.push($(this).val()); });
    $.post(V.ajax_url, {action:'viraseo_generate_brief', nonce:V.nonce, selected_ids:ids}, function(res){
        if (res.success) alert(res.data.message);
        else alert(res.data||V.strings.error);
    });
});


// ======= WOO OOS =======
if ($('#viraseo-oos-tbody').length){
    $.post(V.ajax_url, {action:'viraseo_get_oos_products', nonce:V.nonce}, function(res){
        if (!res.success) return;
        var $t = $('#viraseo-oos-tbody').empty();
        if (!res.data.products.length) { $t.html('<tr><td colspan="4" class="viraseo-empty-state">محصول ناموجودی شناسایی نشده.</td></tr>'); return; }
        res.data.products.forEach(function(p){
            $t.append('<tr><td>'+p.title+'</td><td style="color:'+(p.has_traffic?'#059669':'#dc2626')+'">'+(p.has_traffic?'دارد ✓':'ندارد ✗')+'</td><td>'+p.action+'</td><td>'+p.detected_at+'</td></tr>');
        });
    });
}

// ======= FACETED NAV =======
if ($('#viraseo-faceted-form').length){
    $.post(V.ajax_url, {action:'viraseo_get_faceted_settings', nonce:V.nonce}, function(res){
        if (!res.success) return;
        var s = res.data;
        $('#viraseo-fac-enabled').prop('checked', s.enabled);
        $('[name=max_params_allowed]').val(s.max_params_allowed);
        $('[name=filter_params_text]').val(s.filter_params_text);
        $('[name=safe_params_text]').val(s.safe_params_text);
        $('[name=prefix]').val(s.prefix);
        $('[name=noindex_sorting]').prop('checked', s.noindex_sorting);
        $('[name=add_canonical]').prop('checked', s.add_canonical);
    });
}
$(document).on('submit', '#viraseo-faceted-form', function(e){
    e.preventDefault();
    var d = $(this).serializeArray();
    var payload = {action:'viraseo_save_faceted_settings', nonce:V.nonce};
    d.forEach(function(f){ payload[f.name] = f.value; });
    $.post(V.ajax_url, payload, function(res){
        alert(res.success ? res.data.message : (res.data||V.strings.error));
    });
});

// ======= N8N WORKFLOW MANAGER =======
$(document).on('click', '.viraseo-wf-view, .viraseo-wf-edit', function(){
    var idx = $(this).data('index');
    var isEdit = $(this).hasClass('viraseo-wf-edit');
    var wf = window.viraseoWorkflows[idx];
    if (!wf) return;

    var json = JSON.parse(wf.content);
    var pretty = JSON.stringify(json, null, 2);

    $('#viraseo-wf-modal-title').text(json.name || wf.filename);
    $('#viraseo-wf-modal-filename').text('📄 ' + wf.filename);
    $('#viraseo-wf-modal-nodes').text('🔗 ' + (json.nodes ? json.nodes.length : 0) + ' نود');
    $('#viraseo-wf-editor').val(pretty).prop('readonly', !isEdit);
    $('#viraseo-wf-save-btn').toggle(isEdit).data('filename', wf.filename);
    $('#viraseo-wf-download-btn').data('filename', wf.filename);
    $('#viraseo-wf-modal').show();
});

$(document).on('click', '.viraseo-wf-copy', function(){
    var idx = $(this).data('index');
    var wf = window.viraseoWorkflows[idx];
    if (!wf) return;
    copyToClipboard(wf.content);
    alert('JSON ورکفلو در کلیپ‌بورد کپی شد.');
});

$(document).on('click', '#viraseo-wf-copy-btn', function(){
    copyToClipboard($('#viraseo-wf-editor').val());
    alert('کپی شد!');
});

$(document).on('click', '#viraseo-wf-save-btn', function(){
    var filename = $(this).data('filename');
    var content = $('#viraseo-wf-editor').val();
    $.post(V.ajax_url, {action:'viraseo_wf_save', nonce:V.nonce, filename:filename, content:content}, function(res){
        if (res.success) { alert(res.data.message); location.reload(); }
        else alert(res.data||V.strings.error);
    });
});

$(document).on('click', '#viraseo-wf-download-btn', function(){
    var filename = $(this).data('filename');
    var content = $('#viraseo-wf-editor').val();
    downloadJSON(filename, content);
});

$(document).on('click', '.viraseo-wf-download', function(){
    var filename = $(this).data('filename');
    var wf = window.viraseoWorkflows.find(function(w){ return w.filename === filename; });
    if (wf) downloadJSON(filename, wf.content);
});

$(document).on('click', '#viraseo-wf-add-new', function(){
    $('#viraseo-wf-add-modal').show();
});

$(document).on('click', '#viraseo-wf-create-btn', function(){
    var name = $('#viraseo-wf-new-name').val().trim();
    var content = $('#viraseo-wf-new-content').val().trim();
    if (!name) { alert('نام فایل الزامی است.'); return; }
    $.post(V.ajax_url, {action:'viraseo_wf_create', nonce:V.nonce, name:name, content:content}, function(res){
        if (res.success) { alert(res.data.message); location.reload(); }
        else alert(res.data||V.strings.error);
    });
});

$(document).on('click', '#viraseo-wf-download-all', function(){
    $.post(V.ajax_url, {action:'viraseo_wf_download_all', nonce:V.nonce}, function(res){
        if (res.success && res.data.zip) { window.location.href = res.data.url; }
        else if (res.success && res.data.urls) { res.data.urls.forEach(function(u){ window.open(u); }); }
        else alert(res.data||V.strings.error);
    });
});

// Modal close
$(document).on('click', '.viraseo-modal-close, .viraseo-modal-close-btn, .viraseo-modal-overlay', function(){
    $(this).closest('.viraseo-modal').hide();
});

// Utilities
function copyToClipboard(text){
    if (navigator.clipboard) { navigator.clipboard.writeText(text); return; }
    var $ta = $('<textarea>').val(text).appendTo('body').select();
    document.execCommand('copy');
    $ta.remove();
}
function downloadJSON(filename, content){
    var blob = new Blob([content], {type:'application/json'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a); URL.revokeObjectURL(url);
}

})(jQuery);
