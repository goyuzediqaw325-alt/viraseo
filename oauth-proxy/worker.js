/**
 * ViraSEO OAuth Proxy — Cloudflare Worker
 * 
 * این فایل رو در Cloudflare Workers دیپلوی کنید.
 * کار: واسطه بین افزونه وردپرس و Google OAuth
 * 
 * 🔧 نصب:
 * 1. حساب Cloudflare بسازید (رایگان)
 * 2. Workers & Pages → Create Worker
 * 3. این کد رو paste کنید
 * 4. Environment Variables اضافه کنید:
 *    - GOOGLE_CLIENT_ID = (از Google Cloud Console)
 *    - GOOGLE_CLIENT_SECRET = (از Google Cloud Console)
 * 5. Worker URL رو در تنظیمات افزونه وردپرس وارد کنید
 * 
 * 📝 در Google Cloud Console:
 * - Authorized redirect URI: https://YOUR-WORKER.workers.dev/callback
 * 
 * فلو:
 * WP Plugin → /auth?redirect=SITE_URL → Google Consent → /callback → WP Plugin (with tokens)
 */

const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/indexing';

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname;

    // CORS headers for preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type',
        },
      });
    }

    try {
      if (path === '/auth') return handleAuth(url, env);
      if (path === '/callback') return handleCallback(url, env);
      if (path === '/refresh') return handleRefresh(request, env);
      if (path === '/health') return jsonResponse({ status: 'ok', service: 'ViraSEO OAuth Proxy' });

      return jsonResponse({ error: 'Not Found' }, 404);
    } catch (e) {
      return jsonResponse({ error: e.message }, 500);
    }
  },
};

/**
 * Step 1: /auth?redirect=https://mysite.com/wp-admin/admin-ajax.php?action=viraseo_gsc_callback
 * Redirects user to Google consent screen
 */
function handleAuth(url, env) {
  const redirect = url.searchParams.get('redirect');
  if (!redirect) return jsonResponse({ error: 'Missing redirect parameter' }, 400);

  // Store the WP callback URL in state (base64 encoded)
  const state = btoa(JSON.stringify({ redirect, nonce: crypto.randomUUID() }));

  const params = new URLSearchParams({
    client_id: env.GOOGLE_CLIENT_ID,
    redirect_uri: `${url.origin}/callback`,
    response_type: 'code',
    scope: SCOPE,
    access_type: 'offline',
    prompt: 'consent',
    state: state,
  });

  return Response.redirect(`${GOOGLE_AUTH_URL}?${params.toString()}`, 302);
}

/**
 * Step 2: /callback?code=xxx&state=xxx
 * Google redirects here after user consents
 * We exchange code for tokens, then redirect back to WP with tokens
 */
async function handleCallback(url, env) {
  const code = url.searchParams.get('code');
  const state = url.searchParams.get('state');
  const error = url.searchParams.get('error');

  if (error) {
    return jsonResponse({ error: `Google error: ${error}` }, 400);
  }

  if (!code || !state) {
    return jsonResponse({ error: 'Missing code or state' }, 400);
  }

  // Decode state to get WP redirect URL
  let stateData;
  try {
    stateData = JSON.parse(atob(state));
  } catch (e) {
    return jsonResponse({ error: 'Invalid state' }, 400);
  }

  const wpRedirect = stateData.redirect;
  if (!wpRedirect) return jsonResponse({ error: 'No redirect in state' }, 400);

  // Exchange code for tokens
  const tokenResponse = await fetch(GOOGLE_TOKEN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      code: code,
      client_id: env.GOOGLE_CLIENT_ID,
      client_secret: env.GOOGLE_CLIENT_SECRET,
      redirect_uri: `${url.origin}/callback`,
      grant_type: 'authorization_code',
    }),
  });

  const tokens = await tokenResponse.json();

  if (!tokens.access_token) {
    const errMsg = tokens.error_description || tokens.error || 'Token exchange failed';
    // Redirect back to WP with error
    const sep = wpRedirect.includes('?') ? '&' : '?';
    return Response.redirect(`${wpRedirect}${sep}gsc_error=${encodeURIComponent(errMsg)}`, 302);
  }

  // Redirect back to WP with tokens (via query params — short-lived, consumed immediately)
  const sep = wpRedirect.includes('?') ? '&' : '?';
  const params = new URLSearchParams({
    gsc_access_token: tokens.access_token,
    gsc_refresh_token: tokens.refresh_token || '',
    gsc_expires_in: tokens.expires_in || 3600,
    gsc_connected: '1',
  });

  return Response.redirect(`${wpRedirect}${sep}${params.toString()}`, 302);
}

/**
 * POST /refresh — Refresh an expired access token
 * Body: { refresh_token: "xxx" }
 * Returns: { access_token: "xxx", expires_in: 3600 }
 */
async function handleRefresh(request, env) {
  const body = await request.json();
  const refreshToken = body.refresh_token;

  if (!refreshToken) return jsonResponse({ error: 'Missing refresh_token' }, 400);

  const tokenResponse = await fetch(GOOGLE_TOKEN_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      refresh_token: refreshToken,
      client_id: env.GOOGLE_CLIENT_ID,
      client_secret: env.GOOGLE_CLIENT_SECRET,
      grant_type: 'refresh_token',
    }),
  });

  const tokens = await tokenResponse.json();

  if (!tokens.access_token) {
    return jsonResponse({ error: tokens.error_description || 'Refresh failed' }, 401);
  }

  return jsonResponse({
    access_token: tokens.access_token,
    expires_in: tokens.expires_in || 3600,
  });
}

function jsonResponse(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'Access-Control-Allow-Origin': '*',
    },
  });
}
