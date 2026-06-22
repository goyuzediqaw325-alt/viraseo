# Feature 9: Zero-Cost Farsi Keyword Discovery — n8n Workflow

## Overview

This n8n workflow discovers new Persian keyword opportunities using **free** Google endpoints:
1. **Google Autocomplete API** — Fetches Farsi long-tail suggestions
2. **Google SERP Related Searches** — Scrapes "جستجوهای مرتبط" from page bottom

No paid APIs (Ahrefs, Semrush) required.

---

## Workflow Architecture

```
Webhook Trigger (seed keyword)
     │
     ├─► Google Autocomplete API (base keyword)
     │      └─► Autocomplete API (keyword + alphabet expansion)
     │
     ├─► Google SERP Scrape (extract Related Searches)
     │      └─► Extract "People Also Ask" questions
     │
     └─► Merge + Deduplicate + Normalize Persian
              │
              └─► POST results to WP callback URL
```

---

## Node Details

### Node 1: Webhook Trigger

- **Path**: `/apseo-keyword-discover`
- **Method**: POST
- **Auth**: Header `X-APSEO-Secret`
- **Immediate Response**: `{"status":"accepted"}`

Payload from WP:
```json
{
  "action": "keyword_discover",
  "seed_keyword": "بهترین لپ‌تاپ",
  "discovery_id": "abc123hash",
  "callback_url": "https://site.com/wp-json/apseo/v1/keyword-ideas",
  "language": "fa"
}
```


---

### Node 2: Google Autocomplete API — Base Query

**Type**: HTTP Request  
**Purpose**: Fetch suggestions for the seed keyword

**Configuration**:
```
URL: http://suggestqueries.google.com/complete/search
Method: GET
Query Parameters:
  - output: firefox
  - hl: fa
  - gl: ir
  - q: {{ encodeURIComponent($json.body.seed_keyword) }}
```

**Important Notes on Persian URL Encoding**:
- Persian characters must be UTF-8 encoded
- n8n's `encodeURIComponent()` handles this correctly
- Example: "بهترین لپ‌تاپ" → `%D8%A8%D9%87%D8%AA%D8%B1%DB%8C%D9%86+%D9%84%D9%BE%E2%80%8C%D8%AA%D8%A7%D9%BE`
- The ZWNJ character (U+200C) encodes as `%E2%80%8C`

**Response Format** (JSON array):
```json
[
  "بهترین لپ‌تاپ",
  [
    "بهترین لپ تاپ ۱۴۰۳",
    "بهترین لپ تاپ دانشجویی",
    "بهترین لپ تاپ برای برنامه نویسی",
    "بهترین لپ تاپ گیمینگ",
    "بهترین لپ تاپ ارزان",
    "بهترین لپ تاپ لنوو",
    "بهترین لپ تاپ تا ۲۰ میلیون",
    "بهترین لپ تاپ ایسوس"
  ]
]
```

The suggestions are in `response[1]` (array index 1).

---

### Node 3: Alphabet Expansion (Loop)

**Type**: SplitInBatches + HTTP Request  
**Purpose**: Expand suggestions by appending Persian letters

Generate additional autocomplete queries by appending each Persian letter:

```javascript
// Node: Generate alphabet queries
const seed = $input.first().json.body.seed_keyword;
const persianLetters = [
  'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ',
  'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض',
  'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل',
  'م', 'ن', 'و', 'ه', 'ی'
];

// Also add common modifiers
const modifiers = [
  'چیست', 'چگونه', 'بهترین', 'ارزان‌ترین',
  'خرید', 'قیمت', 'مقایسه', 'آموزش'
];

const queries = [];

// Seed + letter
persianLetters.forEach(letter => {
  queries.push({ query: `${seed} ${letter}` });
});

// Modifier + seed
modifiers.forEach(mod => {
  queries.push({ query: `${mod} ${seed}` });
});

return queries.map(q => ({ json: q }));
```

Then for each query, hit the same Autocomplete API:
```
URL: http://suggestqueries.google.com/complete/search?output=firefox&hl=fa&gl=ir&q={{ encodeURIComponent($json.query) }}
```

**Rate Limiting**: Add 500ms Wait node between requests to avoid blocks.

---

### Node 4: Google SERP — Related Searches

**Type**: HTTP Request  
**Purpose**: Scrape "جستجوهای مرتبط" from Google SERP bottom

```
URL: https://www.google.com/search?q={{ encodeURIComponent($json.body.seed_keyword) }}&hl=fa&gl=ir&num=10
Method: GET
Headers:
  User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
  Accept-Language: fa-IR,fa;q=0.9
```

**Extract Related Searches** (Code node):
```javascript
const cheerio = require('cheerio');
const html = $input.first().json.data;
const $ = cheerio.load(html);

const relatedSearches = [];
const questions = [];

// Related Searches (bottom of SERP)
// Google uses various selectors; try multiple
$('a[href*="/search?q="]').each(function() {
  const parent = $(this).closest('.k8XOCe, .s75CSd, .AJLUJb, [data-ved]');
  if (parent.length) {
    const text = $(this).text().trim();
    // Filter: must contain Persian chars, not too short
    if (text.length > 3 && /[\u0600-\u06FF]/.test(text)) {
      relatedSearches.push(text);
    }
  }
});

// Fallback: look for specific related search containers
$('.k8XOCe a, .s75CSd a, .AJLUJb a').each(function() {
  const text = $(this).text().trim();
  if (text.length > 3 && /[\u0600-\u06FF]/.test(text) && !relatedSearches.includes(text)) {
    relatedSearches.push(text);
  }
});

// "People Also Ask" (سؤالات مرتبط)
$('[data-q], .related-question-pair [data-q]').each(function() {
  const q = $(this).attr('data-q') || $(this).text().trim();
  if (q && q.length > 5) {
    questions.push(q);
  }
});

// Also try jscontroller-based PAA
$('div[jsname] span.CSkcDe, div[data-lk] span').each(function() {
  const q = $(this).text().trim();
  if (q.length > 5 && /[\u0600-\u06FF]/.test(q) && q.includes('؟')) {
    questions.push(q);
  }
});

return [{
  json: {
    related_searches: [...new Set(relatedSearches)].slice(0, 15),
    people_also_ask: [...new Set(questions)].slice(0, 10)
  }
}];
```


---

### Node 5: Merge, Deduplicate & Normalize

**Type**: Code (JavaScript)  
**Purpose**: Combine all sources, normalize Persian, score relevance

```javascript
const ZWNJ = '\u200C';
const seed = $node["Webhook Trigger"].json.body.seed_keyword;
const discoveryId = $node["Webhook Trigger"].json.body.discovery_id;
const callbackUrl = $node["Webhook Trigger"].json.body.callback_url;

// Collect all suggestions from various sources
const autocompleteBase = $node["Autocomplete Base"].json[1] || [];
const autocompleteExpanded = $node["Autocomplete Expanded"].all()
  .flatMap(item => item.json[1] || []);
const relatedSearches = $node["Extract Related"].json.related_searches || [];
const paaQuestions = $node["Extract Related"].json.people_also_ask || [];

// Persian normalization function
function normalize(text) {
  text = text.replace(/\u0643/g, '\u06A9'); // Arabic Kaf -> Persian
  text = text.replace(/\u064A/g, '\u06CC'); // Arabic Yeh -> Persian
  text = text.replace(/[\u064B-\u065F\u0670]/g, ''); // Remove diacritics
  text = text.replace(new RegExp(`${ZWNJ}{2,}`, 'g'), ZWNJ);
  text = text.replace(/\s+/g, ' ').trim();
  return text;
}

// Persian stop words for relevance scoring
const stopWords = new Set([
  'و','در','به','از','که','این','را','با','است','آن','یک',
  'برای','تا','بر','هم','ها','های','می','شود'
]);

// Score relevance (how related to seed keyword)
function scoreRelevance(keyword, seed) {
  const seedWords = seed.split(/[\s\u200C]+/).filter(w => !stopWords.has(w));
  const kwWords = keyword.split(/[\s\u200C]+/).filter(w => !stopWords.has(w));
  
  let matchCount = 0;
  seedWords.forEach(sw => {
    if (kwWords.some(kw => kw.includes(sw) || sw.includes(kw))) {
      matchCount++;
    }
  });
  
  const relevance = seedWords.length > 0 
    ? Math.round((matchCount / seedWords.length) * 100) 
    : 50;
  
  // Bonus for longer keywords (long-tail = more specific)
  const lengthBonus = Math.min(20, kwWords.length * 5);
  
  return Math.min(100, relevance + lengthBonus);
}

// Check if keyword is a question
function isQuestion(text) {
  const questionWords = ['چیست', 'چگونه', 'چرا', 'کجا', 'کی', 'چه', 'آیا', 'کدام'];
  const hasQuestionMark = text.includes('؟');
  const hasQuestionWord = questionWords.some(q => text.includes(q));
  return hasQuestionMark || hasQuestionWord;
}

// Combine all keywords with source tracking
const allKeywords = new Map(); // key: normalized keyword, value: data

function addKeyword(keyword, source) {
  const normalized = normalize(keyword);
  if (normalized.length < 3) return;
  if (normalized === normalize(seed)) return; // Skip exact seed match
  
  const key = normalized.toLowerCase().replace(/\u200C/g, ' ');
  
  if (!allKeywords.has(key)) {
    allKeywords.set(key, {
      keyword: normalized,
      source: source,
      relevance_score: scoreRelevance(normalized, seed),
      is_question: isQuestion(normalized),
      search_volume_hint: null // Free API doesn't provide volume
    });
  }
}

// Add from each source
autocompleteBase.forEach(kw => addKeyword(kw, 'autocomplete'));
autocompleteExpanded.forEach(kw => addKeyword(kw, 'autocomplete'));
relatedSearches.forEach(kw => addKeyword(kw, 'related_search'));
paaQuestions.forEach(kw => addKeyword(kw, 'people_also_ask'));

// Convert to array and sort by relevance
const ideas = Array.from(allKeywords.values())
  .sort((a, b) => b.relevance_score - a.relevance_score)
  .slice(0, 200); // Cap at 200 ideas

return [{
  json: {
    discovery_id: discoveryId,
    callback_url: callbackUrl,
    seed_keyword: seed,
    ideas: ideas,
    stats: {
      total: ideas.length,
      from_autocomplete: ideas.filter(i => i.source === 'autocomplete').length,
      from_related: ideas.filter(i => i.source === 'related_search').length,
      from_paa: ideas.filter(i => i.source === 'people_also_ask').length,
      questions: ideas.filter(i => i.is_question).length
    }
  }
}];
```

---

### Node 6: Send Results to WP

**Type**: HTTP Request  
**Method**: POST  
**URL**: `{{ $json.callback_url }}`  
**Headers**: `X-APSEO-Secret: {{ $env.APSEO_SECRET_KEY }}`  
**Body**: Full JSON from Node 5

---

## WP REST Endpoint (Receiving Side)

Add to `WebhookHandler.php`:

```php
// Register route
register_rest_route('apseo/v1', '/keyword-ideas', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_keyword_ideas'],
    'permission_callback' => [$this, 'verify_webhook_secret'],
]);

// Handler
public function handle_keyword_ideas(\WP_REST_Request $request): \WP_REST_Response {
    global $wpdb;
    
    $payload = $request->get_json_params();
    $discovery_id = sanitize_text_field($payload['discovery_id'] ?? '');
    $ideas = $payload['ideas'] ?? [];
    
    if (empty($discovery_id) || empty($ideas)) {
        return new \WP_REST_Response(['success' => false], 400);
    }
    
    $table = $wpdb->prefix . 'apseo_keyword_ideas';
    $disc_table = $wpdb->prefix . 'apseo_keyword_discoveries';
    
    $inserted = 0;
    foreach ($ideas as $idea) {
        $wpdb->insert($table, [
            'discovery_id'       => $discovery_id,
            'keyword'            => sanitize_text_field($idea['keyword'] ?? ''),
            'keyword_hash'       => md5(mb_strtolower($idea['keyword'] ?? '')),
            'source'             => sanitize_text_field($idea['source'] ?? 'autocomplete'),
            'relevance_score'    => absint($idea['relevance_score'] ?? 50),
            'search_volume_hint' => sanitize_text_field($idea['search_volume_hint'] ?? ''),
            'is_question'        => !empty($idea['is_question']) ? 1 : 0,
            'status'             => 'active',
            'created_at'         => current_time('mysql'),
        ]);
        $inserted++;
    }
    
    // Update discovery status
    $wpdb->update($disc_table, [
        'status'       => 'completed',
        'completed_at' => current_time('mysql'),
        'ideas_count'  => $inserted,
    ], ['discovery_id' => $discovery_id]);
    
    return new \WP_REST_Response([
        'success'  => true,
        'inserted' => $inserted,
    ], 200);
}
```

---

## Google Autocomplete API Notes

### Endpoint
```
http://suggestqueries.google.com/complete/search
```

### Parameters
| Param | Value | Description |
|-------|-------|-------------|
| `output` | `firefox` | Returns JSON (not JSONP) |
| `hl` | `fa` | Persian language suggestions |
| `gl` | `ir` | Iran geo-location |
| `q` | URL-encoded query | The seed keyword |

### Rate Limits
- No official rate limit documentation
- Recommended: 1 request per 500ms
- Use 32 alphabet queries + 8 modifiers = 40 requests
- Total time: ~20 seconds with delays

### Persian URL Encoding Examples
| Input | Encoded |
|-------|---------|
| خرید | `%D8%AE%D8%B1%DB%8C%D8%AF` |
| نیم‌فاصله | `%D9%86%DB%8C%D9%85%E2%80%8C%D9%81%D8%A7%D8%B5%D9%84%D9%87` |
| ۱۴۰۳ | `%DB%B1%DB%B4%DB%B0%DB%B3` |

### Handling in n8n
In n8n expressions, use:
```
{{ encodeURIComponent($json.body.seed_keyword) }}
```
This automatically handles UTF-8 encoding of Persian characters including ZWNJ.

---

## Workflow Error Handling

1. **Autocomplete returns empty**: Skip alphabet expansion, rely on Related Searches
2. **SERP blocked**: Use cached suggestions or return partial results
3. **Timeout**: Set 60s timeout per HTTP request, continue on fail
4. **Empty results**: Mark discovery as `failed` with error message
