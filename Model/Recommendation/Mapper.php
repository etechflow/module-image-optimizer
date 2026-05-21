<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Recommendation;

/**
 * Maps PageSpeed Insights audit IDs → which ETechFlow feature fixes them.
 *
 * Drives the "ETechFlow can fix this!" badge next to each PSI recommendation
 * in the admin. Lets a merchant see Google's complaint AND know which one
 * of our settings would resolve it in the same place.
 *
 * Curated, not exhaustive — we cover the audits that map cleanly to a
 * single ETechFlow toggle. Audits with no clean mapping (e.g. server
 * response time, third-party JS) get no badge — they're outside our
 * scope and showing a fake "we fix this" claim would be dishonest.
 */
class Mapper
{
    /**
     * @var array<string, string>  PSI audit ID → ETechFlow feature name
     */
    private const MAP = [
        // Image-related audits — covered by IO v1.0+
        'uses-webp-images'           => 'Enable WebP conversion (IO → General → Module Enabled)',
        'modern-image-formats'       => 'Enable WebP conversion (IO → General → Module Enabled)',
        'uses-optimized-images'      => 'Run etechflow:io:optimize to compress JPEGs/PNGs',
        'offscreen-images'           => 'Enable lazy loading (IO → Frontend Output → Native lazy-loading)',
        'unsized-images'             => 'Magento product images carry width/height by default — check your theme overrides',
        'uses-responsive-images'     => 'IO v1.1+: per-attribute image sizing (planned for PSO v1.0)',

        // Code optimization — coming in PSO v1.0
        'unminified-css'             => 'Coming in PSO v1.0 — CSS minification',
        'unminified-javascript'      => 'Coming in PSO v1.0 — JavaScript minification',
        'render-blocking-resources'  => 'Coming in PSO v1.0 — Critical CSS extraction + defer JS',
        'unused-css-rules'           => 'Coming in PSO v1.0 — Critical CSS extraction',
        'unused-javascript'          => 'Coming in PSO v1.0 — JS tree-shaking + defer',
        'uses-text-compression'      => 'Enable GZIP/Brotli at the server (.htaccess or nginx) — coming in PSO v1.0',

        // Font + LCP audits — coming in PSO v1.0
        'font-display'               => 'Coming in PSO v1.0 — Defer Fonts Loading',
        'preload-fonts'              => 'Coming in PSO v1.0 — Prioritize Resource Loading',
        'critical-request-chains'    => 'Coming in PSO v1.0 — Critical CSS + preload',
        'largest-contentful-paint-element' => 'Mostly addressed by image optimization + critical CSS',
    ];

    /**
     * Returns the ETechFlow fix description for an audit ID, or null if
     * we don't claim to fix it.
     */
    public function getFix(string $auditId): ?string
    {
        return self::MAP[$auditId] ?? null;
    }
}
