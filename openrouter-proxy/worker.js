/**
 * ViraSEO — OpenRouter Proxy (Cloudflare Worker)
 * ------------------------------------------------
 * Iranian hosts are often blocked from reaching openrouter.ai directly (or OpenRouter
 * blocks Iran IPs). This Worker runs on Cloudflare's global network and transparently
 * relays requests to OpenRouter, so the WordPress server only talks to the Worker.
 *
 * DEPLOY:
 *   1. Go to https://dash.cloudflare.com → Workers & Pages → Create Worker.
 *   2. Paste this file, Deploy.
 *   3. Copy the Worker URL (e.g. https://viraseo-ai.YOURNAME.workers.dev).
 *   4. In ViraSEO → Settings → AI, paste it into "آدرس پروکسی OpenRouter".
 *
 * The plugin calls:  {worker}/v1/models  and  {worker}/v1/chat/completions
 * which are forwarded to:  https://openrouter.ai/api/v1/...
 * The Authorization header (your OpenRouter key) is passed through untouched.
 */

const OPENROUTER = 'https://openrouter.ai/api';

export default {
  async fetch(request) {
    const url = new URL(request.url);

    // CORS preflight (harmless; main use is server-to-server)
    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders() });
    }

    // Only proxy the OpenRouter API paths
    if (!url.pathname.startsWith('/v1/')) {
      return new Response(
        JSON.stringify({ status: 'ViraSEO OpenRouter proxy is running', usage: 'POST {worker}/v1/chat/completions' }),
        { status: 200, headers: { 'Content-Type': 'application/json', ...corsHeaders() } }
      );
    }

    const target = OPENROUTER + url.pathname + url.search;

    // Clone headers, forward Authorization + Content-Type, set referer/title for OpenRouter
    const headers = new Headers();
    const auth = request.headers.get('Authorization');
    if (auth) headers.set('Authorization', auth);
    headers.set('Content-Type', request.headers.get('Content-Type') || 'application/json');
    // Pass the calling site's referer through (any domain), fall back to the worker's own origin
    const ref = request.headers.get('X-Site-Url') || request.headers.get('Referer') || url.origin;
    headers.set('HTTP-Referer', ref);
    headers.set('X-Title', 'ViraSEO');

    const init = {
      method: request.method,
      headers,
      body: (request.method === 'GET' || request.method === 'HEAD') ? undefined : await request.text(),
    };

    try {
      const resp = await fetch(target, init);
      // Stream the body straight through (lower latency, no buffering limits)
      return new Response(resp.body, {
        status: resp.status,
        headers: { 'Content-Type': resp.headers.get('Content-Type') || 'application/json', ...corsHeaders() },
      });
    } catch (e) {
      return new Response(
        JSON.stringify({ error: { message: 'Proxy fetch failed: ' + e.message } }),
        { status: 502, headers: { 'Content-Type': 'application/json', ...corsHeaders() } }
      );
    }
  },
};

function corsHeaders() {
  return {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Authorization, Content-Type',
  };
}
