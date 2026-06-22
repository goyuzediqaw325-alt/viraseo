# Feature 7: E-commerce SERP Intelligence — n8n Workflow Upgrade

## Overview

This document describes how to **upgrade the existing SERP Scraper workflow** (Workflow 2 from the base plugin) to extract **commercial/transactional data** when the target keyword is e-commerce related.

### What's New
When the keyword is transactional (e.g., "خرید لپ‌تاپ ایسوس", "قیمت گوشی سامسونگ"), the scraper extracts additional commercial signals from the top 10 competitors:

| Signal | Description |
|--------|-------------|
| **Price Tables** | Detects structured price display (table, list, comparison) |
| **Technical Specs** | Detects spec/feature tables or headings |
| **Product Count** | Number of products visible on category pages |
| **Buy/CTA Buttons** | Presence of add-to-cart or purchase CTAs |
| **Review/Rating** | Presence of user reviews and star ratings |
| **Comparison Tables** | Side-by-side product comparisons |
| **FAQ Section** | Structured Q&A content |

---

## Architecture Change

```
┌──────────────────────────────────────────────────────────┐
│ Existing SERP Workflow (Workflow 2)                       │
│                                                          │
│  Webhook → Scrape SERP → [NEW] Detect Intent            │
│                               ↓                          │
│                    ┌──────────┴──────────┐               │
│                    │                     │               │
│              Informational          Transactional        │
│                    │                     │               │
│           Standard Extract    E-commerce Extract         │
│           (H1-H3, words)     (+ prices, specs,          │
│                              products, CTAs)             │
│                    │                     │               │
│                    └──────────┬──────────┘               │
│                               ↓                          │
│                    Aggregate & Send to WP                 │
└──────────────────────────────────────────────────────────┘
```

---

## Modified/New n8n Nodes

### Node 2.5 (NEW): Detect Keyword Intent

Insert this **after** the Webhook Trigger, **before** the SERP scrape.

**Type**: Code (JavaScript)
**Purpose**: Classify keyword as transactional vs informational

```javascript
// Persian transactional keyword signals
const transactionalSignals = [
  'خرید', 'قیمت', 'فروش', 'ارزان', 'تخفیف', 'سفارش',
  'قسطی', 'اقساط', 'فروشگاه', 'مقایسه', 'بهترین',
  'ارسال رایگان', 'موجود', 'لیست قیمت', 'مشخصات فنی',
  'بررسی و خرید', 'راهنمای خرید', 'انتخاب', 'پیشنهاد',
  'محصول', 'کالا', 'لوازم', 'قطعات', 'اکسسوری'
];

const keyword = $input.first().json.body.keyword || '';
const keywordLower = keyword.toLowerCase();

// Check if keyword contains transactional signals
let transactionalScore = 0;
const matchedSignals = [];

transactionalSignals.forEach(signal => {
  if (keywordLower.includes(signal)) {
    transactionalScore++;
    matchedSignals.push(signal);
  }
});

// Also check SERP features (if scrape already done)
// Price-related numbers in keyword suggest transactional intent
const hasPricePattern = /\d+[\s,.]*(تومان|ریال|هزار|میلیون)/i.test(keyword);
if (hasPricePattern) transactionalScore += 2;

const isTransactional = transactionalScore >= 1;

return [{
  json: {
    ...($input.first().json),
    intent: {
      type: isTransactional ? 'transactional' : 'informational',
      score: transactionalScore,
      matched_signals: matchedSignals,
      has_price_pattern: hasPricePattern
    }
  }
}];
```

---

### Node 7-B (NEW): E-commerce Data Extractor

This node **replaces or extends** the existing "Extract Page Data" node (Node 7) when intent is transactional.

**Type**: Code (JavaScript)
**Condition**: Only run when `$json.intent.type === 'transactional'`

**Implementation** (use n8n IF node to branch):

```javascript
const cheerio = require('cheerio');
const html = $input.first().json.data || '';
const $ = cheerio.load(html);

// Remove nav/footer/sidebar
$('script, style, nav, footer, header, aside, .sidebar').remove();

// =============================================
// 1. PRICE TABLE DETECTION
// =============================================
let hasPriceTable = false;
let priceCount = 0;
const pricePatterns = [
  /[\d,.]+ تومان/g,
  /[\d,.]+ ریال/g,
  /قیمت[\s:]*[\d,.]+/g,
  /price/i
];

const bodyText = $('body').text();

// Check for structured price elements
const priceSelectors = [
  '.price', '.product-price', '.woocommerce-Price-amount',
  '[class*="price"]', '[class*="gheymat"]', '[class*="qeymat"]',
  'table td:contains("تومان")', '.amount'
];

priceSelectors.forEach(sel => {
  if ($(sel).length > 0) {
    hasPriceTable = true;
    priceCount += $(sel).length;
  }
});

// Count price mentions in text
pricePatterns.forEach(pattern => {
  const matches = bodyText.match(pattern);
  if (matches) priceCount += matches.length;
});

// =============================================
// 2. TECHNICAL SPECIFICATIONS DETECTION
// =============================================
let hasSpecTable = false;
let specHeaders = [];

// Common Persian spec section patterns
const specIndicators = [
  'مشخصات فنی', 'مشخصات کلی', 'ویژگی‌ها', 'ویژگی ها',
  'اطلاعات فنی', 'جزئیات محصول', 'specifications',
  'مشخصات', 'پارامترها', 'جدول مشخصات'
];

// Check headings for spec sections
$('h1, h2, h3, h4').each(function() {
  const text = $(this).text().trim();
  specIndicators.forEach(indicator => {
    if (text.includes(indicator)) {
      hasSpecTable = true;
      specHeaders.push(text);
    }
  });
});

// Check for spec tables (dl/dt/dd or table structures)
const specTableSelectors = [
  'table.specifications', 'table.specs', '.product-specs',
  '.specifications', '.spec-table', 'dl.specs',
  '[class*="specification"]', '[class*="feature"]',
  'table:has(th:contains("مشخصات"))',
  'table:has(td:contains("ابعاد"))',
  'table:has(td:contains("وزن"))'
];

specTableSelectors.forEach(sel => {
  if ($(sel).length > 0) hasSpecTable = true;
});

// Also check for <dl> definition lists (common for specs)
if ($('dl dt').length >= 3) hasSpecTable = true;

// =============================================
// 3. PRODUCT COUNT ON CATEGORY PAGES
// =============================================
let productCount = 0;

const productSelectors = [
  '.product', '.product-item', '.product-card',
  '[class*="product-col"]', '.woocommerce-loop-product__link',
  'li.product', '.products > *',
  '[class*="product-grid"] > *', '[class*="product-list"] > *'
];

productSelectors.forEach(sel => {
  const count = $(sel).length;
  if (count > productCount) productCount = count;
});

// Fallback: count add-to-cart buttons
if (productCount === 0) {
  productCount = $('[class*="add-to-cart"], [class*="add_to_cart"], .btn-cart').length;
}

// =============================================
// 4. CTA/BUY BUTTON DETECTION
// =============================================
let hasBuyButton = false;
let ctaCount = 0;

const ctaSelectors = [
  '.add-to-cart', '.add_to_cart', '[class*="add-to-cart"]',
  'button:contains("خرید")', 'a:contains("خرید")',
  'button:contains("افزودن به سبد")', 'a:contains("سفارش")',
  '.buy-now', '[class*="purchase"]', '[class*="order"]',
  'button:contains("سبد خرید")'
];

ctaSelectors.forEach(sel => {
  const count = $(sel).length;
  if (count > 0) {
    hasBuyButton = true;
    ctaCount += count;
  }
});

// =============================================
// 5. REVIEW/RATING DETECTION
// =============================================
let hasReviews = false;
let reviewCount = 0;

const reviewSelectors = [
  '.review', '.comment', '[class*="review"]',
  '.star-rating', '[class*="rating"]', '.woocommerce-review',
  '[class*="stars"]', '.product-rating',
  'span:contains("نظر")', 'a:contains("دیدگاه")'
];

reviewSelectors.forEach(sel => {
  if ($(sel).length > 0) {
    hasReviews = true;
    reviewCount = Math.max(reviewCount, $(sel).length);
  }
});

// Try to extract review count from text
const reviewCountMatch = bodyText.match(/(\d+)\s*(نظر|دیدگاه|بررسی|review)/i);
if (reviewCountMatch) {
  hasReviews = true;
  reviewCount = parseInt(reviewCountMatch[1]);
}

// =============================================
// 6. COMPARISON TABLE DETECTION
// =============================================
let hasComparison = false;

const comparisonIndicators = [
  'مقایسه', 'comparison', 'vs', 'در برابر',
  'تفاوت', 'بهتر است یا'
];

$('h1, h2, h3, table caption, th').each(function() {
  const text = $(this).text().toLowerCase();
  comparisonIndicators.forEach(ind => {
    if (text.includes(ind)) hasComparison = true;
  });
});

// Tables with 3+ columns often indicate comparisons
$('table').each(function() {
  const cols = $(this).find('tr:first td, tr:first th').length;
  if (cols >= 3) hasComparison = true;
});

// =============================================
// 7. FAQ SECTION DETECTION
// =============================================
let hasFAQ = false;
let faqCount = 0;

const faqIndicators = [
  'سوالات متداول', 'پرسش و پاسخ', 'سوالات رایج',
  'FAQ', 'سوال', 'پرسش‌های'
];

$('h1, h2, h3, h4').each(function() {
  const text = $(this).text();
  faqIndicators.forEach(ind => {
    if (text.includes(ind)) hasFAQ = true;
  });
});

// Schema.org FAQPage detection
$('script[type="application/ld+json"]').each(function() {
  try {
    const ld = JSON.parse($(this).html());
    if (ld['@type'] === 'FAQPage' || (ld.mainEntity && Array.isArray(ld.mainEntity))) {
      hasFAQ = true;
      faqCount = (ld.mainEntity || []).length;
    }
  } catch(e) {}
});

// Count question-like elements
if (!faqCount) {
  faqCount = $('[itemtype*="Question"], .faq-item, [class*="faq"]').length;
}

// =============================================
// OUTPUT: Merge with standard extraction data
// =============================================
const ecommerceData = {
  price_table: {
    detected: hasPriceTable,
    price_mentions: priceCount
  },
  specifications: {
    detected: hasSpecTable,
    spec_headers: specHeaders
  },
  product_count: productCount,
  cta_buttons: {
    detected: hasBuyButton,
    count: ctaCount
  },
  reviews: {
    detected: hasReviews,
    count: reviewCount
  },
  comparison: {
    detected: hasComparison
  },
  faq: {
    detected: hasFAQ,
    count: faqCount
  }
};

// Return merged with existing page data
return [{
  json: {
    ...$input.first().json,
    ecommerce_signals: ecommerceData
  }
}];
```

---

### Node 8-B (NEW): E-commerce Aggregation

This **extends** the existing aggregation node (Node 8) to compile e-commerce signals across all 10 competitors.

**Type**: Code (JavaScript)

```javascript
const competitors = $input.all().map(item => item.json);
const intent = $node["Detect Intent"].json.intent;

// Only aggregate e-commerce data if transactional
if (intent.type !== 'transactional') {
  // Pass through without e-commerce data
  return $input.all();
}

// Aggregate e-commerce signals
const ecomSummary = {
  intent: intent,
  price_table_prevalence: 0,    // % of competitors with price tables
  specs_prevalence: 0,           // % with spec tables
  avg_product_count: 0,          // Average products shown
  cta_prevalence: 0,             // % with buy buttons
  review_prevalence: 0,          // % with reviews
  comparison_prevalence: 0,      // % with comparisons
  faq_prevalence: 0,             // % with FAQ
  common_spec_headers: [],       // Most common spec section titles
  recommendations: []            // Persian recommendations for the user
};

let totalProducts = 0;
let specHeaderCounts = {};

competitors.forEach(comp => {
  const ecom = comp.ecommerce_signals || {};
  
  if (ecom.price_table?.detected) ecomSummary.price_table_prevalence++;
  if (ecom.specifications?.detected) ecomSummary.specs_prevalence++;
  if (ecom.cta_buttons?.detected) ecomSummary.cta_prevalence++;
  if (ecom.reviews?.detected) ecomSummary.review_prevalence++;
  if (ecom.comparison?.detected) ecomSummary.comparison_prevalence++;
  if (ecom.faq?.detected) ecomSummary.faq_prevalence++;
  
  totalProducts += (ecom.product_count || 0);
  
  // Collect spec headers
  (ecom.specifications?.spec_headers || []).forEach(header => {
    specHeaderCounts[header] = (specHeaderCounts[header] || 0) + 1;
  });
});

const total = competitors.length || 1;

// Convert to percentages
ecomSummary.price_table_prevalence = Math.round((ecomSummary.price_table_prevalence / total) * 100);
ecomSummary.specs_prevalence = Math.round((ecomSummary.specs_prevalence / total) * 100);
ecomSummary.cta_prevalence = Math.round((ecomSummary.cta_prevalence / total) * 100);
ecomSummary.review_prevalence = Math.round((ecomSummary.review_prevalence / total) * 100);
ecomSummary.comparison_prevalence = Math.round((ecomSummary.comparison_prevalence / total) * 100);
ecomSummary.faq_prevalence = Math.round((ecomSummary.faq_prevalence / total) * 100);
ecomSummary.avg_product_count = Math.round(totalProducts / total);

// Top spec headers
ecomSummary.common_spec_headers = Object.entries(specHeaderCounts)
  .sort((a, b) => b[1] - a[1])
  .slice(0, 10)
  .map(([header]) => header);

// Generate Persian recommendations
if (ecomSummary.price_table_prevalence >= 70) {
  ecomSummary.recommendations.push('جدول قیمت یا لیست قیمت‌ها ضروری است (۷۰%+ رقبا دارند)');
}
if (ecomSummary.specs_prevalence >= 60) {
  ecomSummary.recommendations.push('جدول مشخصات فنی اضافه کنید (۶۰%+ رقبا دارند)');
}
if (ecomSummary.review_prevalence >= 50) {
  ecomSummary.recommendations.push('بخش نظرات و امتیازدهی کاربران فعال کنید');
}
if (ecomSummary.faq_prevalence >= 40) {
  ecomSummary.recommendations.push('بخش سؤالات متداول (FAQ) با Schema اضافه کنید');
}
if (ecomSummary.comparison_prevalence >= 30) {
  ecomSummary.recommendations.push('جدول مقایسه محصولات می‌تواند مزیت رقابتی ایجاد کند');
}
if (ecomSummary.avg_product_count >= 20) {
  ecomSummary.recommendations.push(
    `میانگین ${ecomSummary.avg_product_count} محصول در صفحات رقبا نمایش داده می‌شود`
  );
}

// Add to the payload that goes back to WP
return [{
  json: {
    ...($input.first().json),
    ecommerce_intelligence: ecomSummary
  }
}];
```

---

## Updated WP REST Payload

The `serp-results` endpoint now receives an additional `ecommerce_intelligence` field:

```json
{
  "analysis_id": 42,
  "avg_content_length": 2500,
  "avg_headings_count": 12,
  "lsi_keywords": ["..."],
  "content_gap": ["..."],
  "common_questions": ["..."],
  "competitors": [
    {
      "position": 1,
      "url": "...",
      "word_count": 3000,
      "ecommerce_signals": {
        "price_table": { "detected": true, "price_mentions": 15 },
        "specifications": { "detected": true, "spec_headers": ["مشخصات فنی"] },
        "product_count": 24,
        "cta_buttons": { "detected": true, "count": 24 },
        "reviews": { "detected": true, "count": 45 },
        "comparison": { "detected": false },
        "faq": { "detected": true, "count": 8 }
      }
    }
  ],
  "ecommerce_intelligence": {
    "intent": { "type": "transactional", "score": 3 },
    "price_table_prevalence": 80,
    "specs_prevalence": 70,
    "avg_product_count": 20,
    "cta_prevalence": 90,
    "review_prevalence": 60,
    "comparison_prevalence": 30,
    "faq_prevalence": 40,
    "common_spec_headers": ["مشخصات فنی", "ویژگی‌های کلیدی"],
    "recommendations": [
      "جدول قیمت یا لیست قیمت‌ها ضروری است (۷۰%+ رقبا دارند)",
      "جدول مشخصات فنی اضافه کنید (۶۰%+ رقبا دارند)"
    ]
  }
}
```

---

## WP Plugin Changes (WebhookHandler Update)

In `WebhookHandler::handle_serp_results()`, add storage for e-commerce data:

```php
// After updating analysis record, store e-commerce intelligence
if (!empty($payload['ecommerce_intelligence'])) {
    $wpdb->update($analysis_table, [
        'ecommerce_data' => wp_json_encode($payload['ecommerce_intelligence']),
    ], ['id' => $analysis_id]);
}

// Store per-competitor e-commerce signals
foreach ($payload['competitors'] as $comp) {
    if (!empty($comp['ecommerce_signals'])) {
        $wpdb->update($competitors_table, [
            'ecommerce_signals' => wp_json_encode($comp['ecommerce_signals']),
        ], [
            'analysis_id' => $analysis_id,
            'position'    => $comp['position'],
        ]);
    }
}
```

### Database Schema Addition

Add these columns to existing tables:

```sql
-- Add to apseo_serp_analysis table
ALTER TABLE {prefix}apseo_serp_analysis
ADD COLUMN keyword_intent VARCHAR(20) DEFAULT 'informational' AFTER keyword_hash,
ADD COLUMN ecommerce_data LONGTEXT DEFAULT NULL AFTER common_questions;

-- Add to apseo_serp_competitors table
ALTER TABLE {prefix}apseo_serp_competitors
ADD COLUMN ecommerce_signals LONGTEXT DEFAULT NULL AFTER schema_types;
```

---

## WP Admin UI (Persian)

The SERP Analysis results page shows an additional **"هوش تجاری"** (Commercial Intelligence) section when ecommerce data is present:

### UI Elements:
1. **Intent Badge**: Shows "تراکنشی" (Transactional) or "اطلاعاتی" (Informational)
2. **Signal Prevalence Bars**: Visual bars showing % of competitors with each signal
3. **Recommendations List**: Persian action items based on aggregated data
4. **Competitor Comparison Table**: Which signals each competitor has (✓/✗)

---

## n8n Workflow Configuration Notes

### IF Node Setup (Branching by Intent)
- **Condition**: `{{ $json.intent.type }}` equals `transactional`
- **True Branch**: Route to E-commerce extractor (Node 7-B)
- **False Branch**: Route to standard extractor (Node 7)
- **Merge**: Both branches merge back at Node 8

### Error Handling
- If e-commerce extraction fails for a competitor, the standard data is still sent
- `continueOnFail: true` on Node 7-B ensures one failed page doesn't break the whole workflow

### Performance
- E-commerce extraction adds ~1-2 seconds per competitor page
- Total workflow time increase: ~10-20 seconds for transactional keywords
- No additional API calls needed (uses same HTML already fetched)
