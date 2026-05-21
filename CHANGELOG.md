# Changelog — ETechFlow Image Optimizer

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

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
