# Changelog — ETechFlow Image Optimizer

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.3.1] — 2026-05-22 — Move admin menu under eTechFlow top-level sidebar

### Changed

- **IO admin pages relocated to a dedicated "eTechFlow" sidebar entry.** Previously the Optimization Log lived under `System → Other Settings`. Now it sits as an `Image Optimizer` column inside a new top-level `eTechFlow` sidebar entry (clusters with other paid-extension vendors above Magento's Stores). Matches the pattern Amasty / Magefan / MageWorx use.
- Each eTechFlow module declares the same `eTechFlow::root` + `eTechFlow::settings` + `eTechFlow::configuration` entries — Magento merges by id, so installing N modules still produces exactly one `eTechFlow` sidebar group.

### Migration

```
composer update etechflow/module-image-optimizer
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Admin URL routes unchanged (`etechflow_io/log/index` still works). No schema or behaviour changes — pure menu-layout adjustment.

---

## [1.3.0] — 2026-05-21 — Scope correction: PSI Diagnose moved out to the upcoming PSO module

**Reverts the PSI Diagnose feature added in v1.2.0.** The Diagnose tool measures page speed via Google's PageSpeed Insights API but doesn't actually optimize anything — it's a measurement tool, not an image-optimization feature. After shipping v1.2.0 we realised it didn't belong in a module called "Image Optimizer".

The PSI code wasn't deleted — it stays on GitHub at the [v1.2.0 tag](https://github.com/etechflow/module-image-optimizer/releases/tag/v1.2.0) and will be lifted into the upcoming `ETechFlow_PageSpeedOptimizer` (PSO) module where it belongs alongside the broader code-optimization feature set (CSS/JS minification, defer fonts, prioritize resource loading).

### What v1.3.0 actually contains

Everything from **v1.1.0** — pure image optimization, no diagnostic:

- WebP conversion via cwebp / Imagick / GD engine chain (v1.0.0)
- `<picture>` element rendering + native `loading="lazy"` (v1.0.0)
- Bulk + cron CLI: `etechflow:io:optimize`, `etechflow:io:verify` (v1.0.0)
- Admin grid at *Stores → Settings → Image Optimization Log* (v1.1.0)
- Savings summary banner (v1.1.0)
- Mass actions: Delete log entries, Restore originals (v1.1.0)

### Removed (compared to v1.2.0)

- ❌ `Model/Psi/PsiClient` + `DiagnosticLogger` — moved to PSO module
- ❌ `Model/Data/DiagnosticResult` + `Recommendation` — moved to PSO module
- ❌ `Model/Recommendation/Mapper` — moved to PSO module
- ❌ `Model/Source/PsiStrategy` — moved to PSO module
- ❌ `Controller/Adminhtml/Diagnose/*` — moved to PSO module
- ❌ `Block/Adminhtml/Diagnose/Page` — moved to PSO module
- ❌ `Console/Command/DiagnoseCommand` — moved to PSO module
- ❌ Admin page at *Stores → Settings → Page Speed Diagnose* — moved to PSO
- ❌ `bin/magento etechflow:io:diagnose` CLI — moved to PSO
- ❌ DB table `etechflow_io_diagnostic_log` — **auto-dropped by Magento's declarative schema** on next `setup:upgrade` (still in whitelist; not in db_schema.xml = drop)
- ❌ Admin config section: Google PageSpeed Insights — moved to PSO
- ❌ Encrypted PSI API key, default strategy, timeout settings — moved to PSO

### Migration from v1.2.0

```
composer update etechflow/module-image-optimizer
bin/magento setup:upgrade      # drops the etechflow_io_diagnostic_log table
bin/magento setup:di:compile
bin/magento cache:flush
```

Any merchant who saved a Google API key in v1.2.0's admin config will see the field disappear from admin. The encrypted key value remains in `core_config_data` until manually cleared (or simply ignored — no other code reads it). When PSO ships, it'll re-introduce the same field in its own admin section.

### Why bump minor not patch

v1.2.0 → v1.2.1 would suggest a bugfix. This is a deliberate feature removal that changes the module's interface (URLs, CLIs, admin sections). v1.3.0 makes the change visible in semver — anyone tracking the changelog sees something happened.

### Lessons documented

Three Magento gotchas that surfaced during the v1.2.0 build remain documented above (PHP 8.1+ readonly child property, declarative-schema varchar(1024) cap, InnoDB utf8mb4 3072-byte key limit). Those lessons apply equally to PSO when we build it.

---

## [1.2.0] — 2026-05-21 — Google PageSpeed Insights diagnostic

Closes the "did this module actually help?" gap. Connects to Google's PageSpeed Insights API so you can run real performance diagnostics from the admin and see how each ETechFlow setting maps to Google's recommendations.

### Why this is the headline feature

Every Amasty / Mageworx / Mirasvit page-speed module markets the same optimization features. What makes Amasty's $259 product feel premium is the **Diagnostic** tool that shows the merchant a real Google score before/after. We now ship the same surface, free under the ETechFlow Bundle key, with one improvement Amasty doesn't have: **the recommendation list shows which ETechFlow feature would fix each Google complaint**, inline.

### Added

- **Admin page** at *Stores → Settings → Page Speed Diagnose*. Run a real PSI call from the admin:
  - Big colour-coded score card (green ≥ 90, orange 50-89, red < 50 — Google's own bands)
  - **Lab data** (Lighthouse): FCP, LCP, TBT, CLS
  - **Field data** (CrUX — Chrome User Experience Report, real-user data when available)
  - Sorted recommendations list (biggest impact first) with HIGH/MEDIUM/LOW impact buckets
  - **ETechFlow fix badge** on every recommendation we cover — "Serve images in next-gen formats → Enable WebP conversion (IO → General → Module Enabled)"
- **Google PageSpeed Insights API integration** via `Model/Psi/PsiClient` — vanilla `Curl` HTTP, no SDK, no library. Free 25,000 requests/day per merchant's Google Cloud API key.
- **Admin config section**: *Stores → Configuration → eTechFlow → Image Optimizer → Google PageSpeed Insights* — API key (encrypted), default strategy (mobile/desktop), timeout.
- **Recommendation mapper** (`Model/Recommendation/Mapper`) — curated mapping of ~16 PSI audit IDs to the ETechFlow feature that fixes them.
- **DB table** `etechflow_io_diagnostic_log` — every diagnostic run persisted with lab + field metrics + raw JSON for future re-parsing. Foundation for v1.3's score-timeline graph.
- **CLI** `bin/magento etechflow:io:diagnose --url=... --strategy=mobile|desktop --json --pass-score=80` — headless diagnostic for CI integration. Exit 0 if score ≥ pass-score, 1 if below, 2 if API call itself failed. Perfect for pre-deploy gates.

### How it stacks up vs Amasty's PSI integration

| Feature | Amasty Pro ($259) | ETechFlow IO v1.2.0 |
|---|---|---|
| PSI Diagnostic in admin | ✅ | ✅ |
| Mobile + desktop scores | ✅ | ✅ |
| CrUX field data display | partial | ✅ (with category badge) |
| Recommendations list with impact | ✅ | ✅ |
| **One-click feature mapping** | ❌ — admin has to guess which setting fixes which audit | ✅ **— "ETechFlow fix" badge on every recommendation we cover** |
| Headless CLI for CI gates | ❌ | ✅ |
| JSON output for tooling | ❌ | ✅ |
| Persisted history (foundation for trend graph) | partial | ✅ |
| Source visibility | ❌ — Amasty docs walled | ✅ public on GitHub |

### Setup (one-time, ~3 min)

1. Go to https://console.cloud.google.com/apis/credentials
2. Create Credentials → API Key (free)
3. Enable "PageSpeed Insights API" on the project (free, 25,000 requests/day)
4. Paste the key into *Stores → Configuration → eTechFlow → Image Optimizer → Google PageSpeed Insights → PageSpeed Insights API Key*

Without a key it still works (with Google's per-IP rate limit), but the key is strongly recommended.

### Backwards compatibility

- New DB table — **requires `setup:upgrade`** when upgrading from v1.1.0.
- No changes to v1.1.0's admin grid or v1.0.0's conversion logic. All previous features unchanged.

### Notes for developers

A few real Magento gotchas surfaced during live testing — documenting so future modules don't re-hit them:

- **`Magento\Backend\Block\Template` already declares a non-readonly `$formKey` property.** PHP 8.1+ rejects re-declaring it as `readonly` in a child class. Use a differently-named property (we use `$diagnoseFormKey`).
- **Magento's declarative schema XSD caps `varchar` columns at `length="1024"`** — if you need more, use `text` (which can't be indexed without a prefix that declarative schema doesn't support).
- **InnoDB on utf8mb4 limits index keys to 3072 bytes.** A composite index `(varchar(768), varchar(16), timestamp)` = 768×4 + 16×4 + 4 = 3140 bytes — over the limit. Use separate single-column indexes instead of a composite when the leading column is long.

### Migration

```
composer update etechflow/module-image-optimizer
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
# Restart php-fpm to clear OPcache (mandatory on prod with opcache.validate_timestamps=0)
```

---

## [1.1.0] — 2026-05-21 — Admin grid + savings banner

Closes the "what just happened?" gap from v1.0. Merchants who don't live in the CLI can now see exactly what was converted, how much was saved, and roll back individual conversions if needed.

### Added

- **Admin grid** at `Stores → Settings → Image Optimization Log`. Magento UI Component listing of `etechflow_io_optimization_log` rows with filterable + sortable columns: ID, Source path, From/To, Bytes before/after, **Savings %** (sorted desc by default), Engine, Status, Optimized at.
- **Savings summary banner** at the top of the grid — 5 metric cards: images optimized, source total, WebP total, **saved on disk** (green), avg savings %.
- **Mass action: Delete log entries** — clears stale audit rows for images that no longer exist in the catalog. WebP files on disk are untouched (they get re-logged on the next `etechflow:io:optimize` run).
- **Mass action: Restore originals** — deletes the `.webp` file from disk AND removes the log row. Safety-checked against path-traversal: only files under `pub/` with a `.webp` extension can be deleted. Browsers fall back to serving the original JPEG/PNG after this runs.
- **ACL split**: `ETechFlow_ImageOptimizer::log` (view), `log_delete` (delete log rows), `log_restore` (delete WebP files). Grant view-only access to most admin roles, restrict destructive actions to ops.
- **Status source model** for the filter dropdown.

### Why not more

Skipped on purpose to avoid overkill in v1.1:
- No async "Run bulk optimize now" admin button — `etechflow:io:optimize` from the CLI is the supported path, and most production deploys already run it via cron.
- No per-row buttons — mass actions cover Delete + Restore, which is everything per-row buttons would do.
- No requeue mass action — the CLI re-converts changed files via mtime dedupe automatically.

### Backwards compatibility

No schema changes, no `setup:upgrade` required when upgrading from v1.0.0 → v1.1.0. Only `composer update` + `cache:flush`.

### Migration

```
composer update etechflow/module-image-optimizer
bin/magento setup:di:compile
bin/magento cache:flush
```

The new admin section appears under **Stores → Settings → Image Optimization Log** after cache flush. Existing CLI workflows are unchanged.

---

## [1.0.0] — 2026-05-21 — Server-side WebP optimization, no external API

First commercial release. Converts product images to WebP locally on your server (no Tinify / ShortPixel / external API), emits `<picture>` elements with proper fallback, and ships with bulk + cron + CLI tooling.

### What it does

When Magento renders a product image (PDP, category grid, cart, search results), our plugin wraps the `<img>` in a `<picture>` element that browsers consume top-to-bottom — modern browsers grab the smaller WebP, legacy browsers (IE / old Safari) fall back to the original JPEG/PNG silently. The customer's browser does the work; you serve smaller files.

### Why server-side instead of an external API

Every paid competitor in the space either:
- Routes images through **TinyPNG / ShortPixel API** — per-image fees, rate limits, latency added to image processing, hard dependency on a 3rd-party uptime
- Or ships **without WebP conversion** and asks the merchant to figure it out themselves

We do all conversion **locally** with a graceful engine chain — try `cwebp` binary first (fastest), fall back to ImageMagick, fall back to PHP-GD. Most modern hosts have at least one. No API key, no per-image cost, no rate limit, no external dependency, no privacy concerns about your product images leaving your server.

### Added

**Foundation**
- `registration.php`, `composer.json` (proprietary licence, soft-deps on suite modules via Bundle key)
- `etc/module.xml` setup_version `1.0.0`
- **DB schema**: 1 table `etechflow_io_optimization_log` — full audit trail of every conversion: source path, format from/to, bytes before/after, savings %, engine used, when. Powers admin grid + savings report.
- **Admin config** (`etc/adminhtml/system.xml`) — License section + General Settings + Image Coverage + Notifications. Standard 4-section layout.

**Licensing + Infrastructure**
- `Model/LicenseValidator` — per-domain HMAC + bundle key. `MODULE_ID = image-optimizer`. Shares `BUNDLE_SECRET_FRAGMENTS` with every eTechFlow module.
- `Model/Config` — license-aware `isEnabled()`. Conversion-engine selection, quality slider, paths to optimize.
- `Model/Performance/Profiler` — Tideways span helper, tags `ETechFlow_IO_*`.

**Conversion engines (pluggable)**
- `Model/Engine/ConversionEngineInterface` — common contract: `available()`, `convertToWebp()`, `getName()`.
- `Model/Engine/CwebpEngine` — shells out to the `cwebp` binary. Fastest + smallest output. Available on most Linux hosts via the `webp` package.
- `Model/Engine/ImagickEngine` — uses ImageMagick's PHP extension. Available where Imagick is compiled with WebP support.
- `Model/Engine/GdEngine` — uses PHP-GD's `imagewebp()`. Most universally available (PHP 7.0+).
- `Model/Engine/EngineChain` — tries engines in admin-configured order, picks the first available.

**Generators**
- `Model/WebpGenerator` — converts a single source file to `.webp` next to it. Dedupes "already converted" via mtime + size check. Writes to optimization log.
- `Model/ImageProcessor` — walks the cache dir, calls WebpGenerator per file, batches.

**Frontend rendering**
- `Plugin/Catalog/Block/PictureBlockPlugin` — `after`-plugin on `Magento\Catalog\Block\Product\Image::toHtml`. Wraps the rendered `<img>` in `<picture><source srcset="...webp" type="image/webp"><img ...></picture>`. Hyvä-safe (Hyvä uses the same block).
- **Native `loading="lazy"` attribute** — injected into below-the-fold product images automatically. Free Core Web Vitals win.

**CLI**
- `bin/magento etechflow:io:optimize` — walks `pub/media/catalog/product/cache/`, converts JPEG/PNG to WebP. Progress bar, dry-run mode (`--dry-run`), quality override (`--quality=85`), path filter (`--paths=...`). Resumable on large catalogs.
- `bin/magento etechflow:io:verify` — 12-check smoke test: license, config, DB table, engine availability (GD WebP support, Imagick WebP support, cwebp binary presence), DI resolution.

### Compatibility

- Magento Open Source 2.4.4 – 2.4.8
- Adobe Commerce 2.4.4 – 2.4.8
- PHP 8.1 / 8.2 / 8.3 / 8.4
- Hyvä Theme + Hyvä Checkout — picture block emits standard HTML, no jQuery / Alpine / Magewire dependency
- Luma theme — works with Magento's default product image block
- Browser support: WebP works in 96%+ of browsers (everything since Safari 14, Chrome 32, Firefox 65, Edge 18). Older browsers transparently fall back to the original JPEG/PNG.

### Performance

- Conversion happens during the bulk CLI / cron, not in the request path — zero impact on frontend response times.
- Resulting WebP files are 25-50% smaller than the JPEG/PNG. Real-world LCP improvements of 0.5-2.0 seconds on image-heavy pages.
- Profiler-instrumented: every conversion is a `ETechFlow_IO_Convert_<engine>` span in Tideways / Blackfire so you can measure the savings.

### Deferred to v1.1

- **AVIF support** — Safari 16.4+ ships AVIF, 80%+ browser coverage. Will land as a second `<picture>` source above WebP.
- **`etechflow:io:perf` benchmark CLI** — measures actual LCP improvement, not just file-size %. Suite-pattern matches the other 9 modules.
- **Per-product `image_opt_skip` EAV attribute** — opt-out per product for high-fidelity photography.
- **Responsive `srcset` for Luma** — Hyvä already does this; Luma users get it in v1.1.
- **Admin status grid + restore originals** — basic UI is in v1.0; rich grid with bulk-restore actions in v1.1.
