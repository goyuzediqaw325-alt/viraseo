# ViraSEO OAuth Proxy (Cloudflare Worker)

## این چیه؟

یک سرور واسط (proxy) که اجازه می‌ده افزونه ویرا سئو **بدون نیاز به Client ID از طرف کاربر** به Google Search Console وصل بشه.

مثل Rank Math که از سرور خودش استفاده می‌کنه، شما هم از این Worker استفاده می‌کنید.

## نصب (۵ دقیقه)

### 1. Google Cloud Console

1. برید به [console.cloud.google.com](https://console.cloud.google.com)
2. پروژه جدید بسازید (یا موجود انتخاب کنید)
3. **APIs & Services → Library** → "Google Search Console API" فعال کنید
4. **APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID**
   - Application type: **Web application**
   - Name: `ViraSEO Proxy`
   - Authorized redirect URIs: `https://YOUR-WORKER-NAME.workers.dev/callback`
5. Client ID و Client Secret رو کپی کنید

### 2. Cloudflare Worker

1. حساب Cloudflare بسازید (رایگان): [dash.cloudflare.com](https://dash.cloudflare.com)
2. **Workers & Pages → Create → Create Worker**
3. اسم بذارید (مثلاً `viraseo-auth`)
4. کد فایل `worker.js` رو paste کنید
5. **Settings → Variables and Secrets** اضافه کنید:
   - `GOOGLE_CLIENT_ID` = مقدار از مرحله ۱
   - `GOOGLE_CLIENT_SECRET` = مقدار از مرحله ۱ (Encrypt کنید)
6. Save & Deploy

### 3. تنظیمات افزونه ViraSEO

در وردپرس → ویرا سئو → تنظیمات:
- **آدرس OAuth Proxy:** `https://viraseo-auth.YOUR-ACCOUNT.workers.dev`

تمام! حالا کاربرها فقط دکمه "اتصال به گوگل" می‌زنن.

## فلو

```
کاربر: دکمه "اتصال" → 
  افزونه: redirect به PROXY/auth?redirect=SITE/callback →
    Proxy: redirect به Google Consent →
      کاربر: "Allow" →
        Google: redirect به PROXY/callback?code=xxx →
          Proxy: exchange code for tokens →
            Proxy: redirect به SITE/callback?tokens=xxx →
              افزونه: ذخیره tokens → "متصل شدید! ✓"
```

## Endpoints

| Path | Method | Description |
|------|--------|-------------|
| `/auth?redirect=URL` | GET | شروع OAuth flow |
| `/callback` | GET | Google redirects here |
| `/refresh` | POST | Refresh expired token |
| `/health` | GET | Health check |

## امنیت

- Client Secret فقط روی Cloudflare Worker ذخیره می‌شه (encrypted)
- توکن‌ها فقط via redirect به سایت کاربر ارسال می‌شن (HTTPS)
- هیچ داده‌ای روی Worker ذخیره نمی‌شه (stateless)
- هر سایت وردپرسی مستقیماً با Google API ارتباط داره (Worker فقط OAuth رو handle می‌کنه)

## هزینه

Cloudflare Workers: **رایگان** تا ۱۰۰,۰۰۰ request/روز (OAuth معمولاً <10 request/روز)
