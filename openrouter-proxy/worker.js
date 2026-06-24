/**
 * ViraSEO — OpenRouter Streaming Proxy (Cloudflare Worker)
 * ---------------------------------------------------------
 * Relays requests to OpenRouter with full streaming support.
 * The response is streamed chunk-by-chunk back to the caller,
 * so the Worker never buffers the full response and never times out
 * (even if AI takes 2+ minutes to generate a long response).
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

    // CORS preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders() });
    }

    // Health check / info
    if (!url.pathname.startsWith('/v1/')) {
      return new Response(
        JSON.stringify({ status: 'ViraSEO OpenRouter Streaming Proxy v2', usage: 'POST {worker}/v1/chat/completions' }),
        { status: 200, headers: { 'Content-Type': 'application/json', ...corsHeaders() } }
      );
    }

    const target = OPENROUTER + url.pathname + url.search;

    // Forward relevant headers
    const headers = new Headers();
    const auth = request.headers.get('Authorization');
    if (auth) headers.set('Authorization', auth);
    headers.set('Content-Type', request.headers.get('Content-Type') || 'application/json');
    const ref = request.headers.get('X-Site-Url') || request.headers.get('Referer') || url.origin;
    headers.set('HTTP-Referer', ref);
    headers.set('X-Title', 'ViraSEO');

    const init = {
      method: request.method,
      headers,
      body: (request.method === 'GET' || request.method === 'HEAD') ? undefined : request.body,
    };

    try {
      const resp = await fetch(target, init);

      // Stream the response body directly — no buffering, no timeout.
      // TransformStream ensures we relay chunks as they arrive.
      const { readable, writable } = new TransformStream();
      resp.body.pipeTo(writable);

      return new Response(readable, {
        status: resp.status,
        headers: {
          'Content-Type': resp.headers.get('Content-Type') || 'text/event-stream',
          'Cache-Control': 'no-cache',
          'Connection': 'keep-alive',
          ...corsHeaders(),
        },
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
    'Access-Control-Allow-Headers': 'Authorization, Content-Type, X-Site-Url',
  };
}
