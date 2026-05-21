<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Data;

/**
 * Value object — one diagnostic run's result.
 *
 * Carries:
 *   - Lab data (Lighthouse score 0-100, FCP, LCP, INP/TBT, CLS)
 *   - Field data (Chrome User Experience Report — REAL user data, present when
 *     the URL has enough Chrome user traffic; null otherwise)
 *   - List of failed audit recommendations (PSI audit IDs + descriptions)
 *   - Raw JSON for storage / debugging
 *
 * Note: PSI 2024+ replaced TBT with INP. We store both names for safety; INP
 * is the actual Core Web Vital metric and what merchants should care about.
 */
final class DiagnosticResult
{
    /**
     * @param int $performanceScore         Lab score 0-100 (or -1 if unavailable)
     * @param ?float $labFcpSeconds         First Contentful Paint (lab, seconds)
     * @param ?float $labLcpSeconds         Largest Contentful Paint (lab, seconds)
     * @param ?float $labTbtMillis          Total Blocking Time (lab, ms; INP proxy)
     * @param ?float $labClsScore           Cumulative Layout Shift (lab, unitless)
     * @param ?float $fieldLcpMillis        REAL-USER LCP from CrUX (ms)
     * @param ?float $fieldInpMillis        REAL-USER INP from CrUX (ms)
     * @param ?float $fieldClsScore         REAL-USER CLS from CrUX (unitless)
     * @param ?string $fieldOverallCategory CrUX overall: FAST | AVERAGE | SLOW | null
     * @param Recommendation[] $recommendations
     * @param ?string $rawJson              For debug / DB storage
     * @param ?string $errorMessage         Non-null when call failed
     */
    public function __construct(
        public readonly string $url,
        public readonly string $strategy,        // 'mobile' | 'desktop'
        public readonly int $performanceScore,
        public readonly ?float $labFcpSeconds,
        public readonly ?float $labLcpSeconds,
        public readonly ?float $labTbtMillis,
        public readonly ?float $labClsScore,
        public readonly ?float $fieldLcpMillis,
        public readonly ?float $fieldInpMillis,
        public readonly ?float $fieldClsScore,
        public readonly ?string $fieldOverallCategory,
        public readonly array $recommendations,
        public readonly ?string $rawJson = null,
        public readonly ?string $errorMessage = null
    ) {
    }

    public function failed(): bool
    {
        return $this->errorMessage !== null;
    }

    /**
     * Colour-coded interpretation of the lab score.
     * Matches Google's own bands: green ≥ 90, orange 50-89, red < 50.
     */
    public function scoreCategory(): string
    {
        if ($this->performanceScore < 0) {
            return 'unknown';
        }
        if ($this->performanceScore >= 90) {
            return 'good';
        }
        if ($this->performanceScore >= 50) {
            return 'needs-improvement';
        }
        return 'poor';
    }

    public function hasFieldData(): bool
    {
        return $this->fieldLcpMillis !== null
            || $this->fieldInpMillis !== null
            || $this->fieldClsScore !== null;
    }
}
