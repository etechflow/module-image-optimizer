# ETechFlow Image Optimizer

Server-side WebP image optimization for Magento 2. No external API, no per-image fees, no rate limits.

## Install

```bash
composer require etechflow/module-image-optimizer:^1.0
bin/magento module:enable ETechFlow_ImageOptimizer
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
# Restart php-fpm to clear OPcache (mandatory on prod with opcache.validate_timestamps=0)
```

## Activate the licence

```bash
php tools/generate-license.php --module=image-optimizer --host=<your-domain>
```

Paste into **Stores → Configuration → eTechFlow → Image Optimizer → License Key** (or use the Bundle License Key if you're an ETechFlow suite customer).

## Verify

```bash
bin/magento etechflow:io:verify
```

Twelve PASS lines means you're good to go.

## Run the first bulk optimization

```bash
bin/magento etechflow:io:optimize
```

Walks `pub/media/catalog/product/cache/`, converts every JPEG/PNG to WebP. Resumable, idempotent, dedupes already-converted images.

After this finishes once, the `<picture>` block plugin starts emitting WebP variants automatically on every PDP / category page. New images cached by Magento are picked up on the next cron tick (every 5 min).

## How it works

Three pieces:

1. **Conversion engine chain** — tries `cwebp` binary first (fastest), then ImageMagick PHP extension, then PHP-GD. First available wins. No external API.
2. **Per-image `<picture>` block** — when Magento renders `<img src="x.jpg">`, our plugin wraps it: `<picture><source srcset="x.webp" type="image/webp"><img src="x.jpg" loading="lazy"></picture>`. WebP-capable browsers grab the smaller file; the rest get the JPEG. Universal compatibility.
3. **Bulk + cron CLI** — convert existing inventory once, then trust the cron to keep up with new images.

## Configuration

`Stores → Configuration → eTechFlow → Image Optimizer`:

- **License Key** — per-module key (or use Bundle License Key for the suite)
- **Module Enabled** — toggle the whole feature
- **Quality** — 1-100, default 80. Higher = larger files, marginal quality gain past ~85.
- **Conversion engine order** — pick which engines to try and in what order. Default: cwebp → Imagick → GD.
- **Image coverage** — which images to optimize: product catalog, search, cross-sells, CMS images, sliders.
- **Bulk batch size** — how many images per cron tick (default 200).

## Honest caveats

- Requires PHP-GD with WebP support **OR** Imagick with WebP support **OR** `cwebp` binary installed. Most modern hosts have at least one. Run `etechflow:io:verify` to confirm.
- Doubles the size of `pub/media/catalog/product/cache/` (JPEG + WebP coexist). Plan disk space accordingly — typically +25-40% growth.
- After the first bulk run, your CDN cache needs a purge to start serving the new HTML with `<picture>` tags.

## Compatibility

- Magento Open Source 2.4.4 – 2.4.8
- Adobe Commerce 2.4.4 – 2.4.8
- PHP 8.1 / 8.2 / 8.3 / 8.4
- Hyvä Theme + Hyvä Checkout
- Luma theme

## Support

info@etechflow.com — include your license key + Magento version when reporting issues.
