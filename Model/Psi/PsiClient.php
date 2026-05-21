<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Psi;

use ETechFlow\ImageOptimizer\Model\Config;
use ETechFlow\ImageOptimizer\Model\Data\DiagnosticResult;
use ETechFlow\ImageOptimizer\Model\Data\Recommendation;
use ETechFlow\ImageOptimizer\Model\Performance\Profiler;
use ETechFlow\ImageOptimizer\Model\Recommendation\Mapper;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Talks to Google's PageSpeed Insights v5 API.
 *
 * Endpoint: https://www.googleapis.com/pagespeedonline/v5/runPagespeed
 * Free tier: 25,000 requests/day per API key (no key → ~1 req/sec per IP).
 *
 * Returns a DiagnosticResult — never throws to the caller. Network or
 * API errors are captured as `errorMessage` on the result so the admin
 * can render a meaningful failure state.
 *
 * Parses the response for:
 *   - Lab (Lighthouse) performance score + Core Web Vital metrics
 *   - Field (CrUX) real-user metrics — present only when the URL has
 *     enough Chrome user traffic
 *   - Failed audits with their PSI audit IDs + descriptions
 *
 * Each failed audit is augmented with our internal "ETechFlow can fix
 * this" mapping via the Mapper service.
 */
class PsiClient
{
    private const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Audits Lighthouse marks as informational rather than failed —
     * we skip these so the recommendation list shows only actionable items.
     */
    private const SKIP_AUDIT_IDS = [
        'final-screenshot',
        'full-page-screenshot',
        'screenshot-thumbnails',
        'metrics',
        'diagnostics',
        'network-requests',
        'network-rtt',
        'network-server-latency',
        'main-thread-tasks',
        'resource-summary',
        'third-party-summary',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly Mapper $mapper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run a PSI diagnostic.
     *
     * @param string $url      Public-facing URL to analyse (must be reachable from Google's IP space).
     * @param string $strategy 'mobile' or 'desktop'.
     */
    public function diagnose(string $url, string $strategy = 'mobile'): DiagnosticResult
    {
        $span = Profiler::start('ETechFlow_IO_PsiDiagnose');
        try {
            $strategy = in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile';
            $apiKey = $this->config->getGooglePsiApiKey();
            $timeout = $this->config->getPsiTimeoutSeconds();

            $query = [
                'url'      => $url,
                'category' => 'PERFORMANCE',
                'strategy' => $strategy,
            ];
            if ($apiKey !== '') {
                $query['key'] = $apiKey;
            }
            $requestUrl = self::API_ENDPOINT . '?' . http_build_query($query);

            $this->curl->setOption(CURLOPT_TIMEOUT, $timeout);
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 30);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);

            try {
                $this->curl->get($requestUrl);
            } catch (\Throwable $e) {
                return $this->failedResult($url, $strategy, sprintf('Network error: %s', $e->getMessage()));
            }

            $statusCode = (int) $this->curl->getStatus();
            $body = (string) $this->curl->getBody();

            if ($statusCode !== 200) {
                $errorMsg = $this->extractApiError($body, $statusCode);
                return $this->failedResult($url, $strategy, $errorMsg);
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                return $this->failedResult($url, $strategy, 'Malformed JSON from PSI API');
            }

            return $this->parseResponse($url, $strategy, $data, $body);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'ETechFlow_IO PSI diagnostic failed',
                ['url' => $url, 'strategy' => $strategy, 'exception' => $e->getMessage()]
            );
            return $this->failedResult($url, $strategy, 'Unexpected error: ' . $e->getMessage());
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * Parse the (massive — ~1MB) PSI JSON into our compact DiagnosticResult.
     */
    private function parseResponse(string $url, string $strategy, array $data, string $rawJson): DiagnosticResult
    {
        $lighthouse = $data['lighthouseResult'] ?? [];
        $categories = $lighthouse['categories'] ?? [];
        $audits     = $lighthouse['audits'] ?? [];

        // Lab score: 0.0 - 1.0 → 0 - 100
        $scoreFloat = $categories['performance']['score'] ?? null;
        $score = is_numeric($scoreFloat) ? (int) round($scoreFloat * 100) : -1;

        // Lab Core Web Vitals
        $fcp = $this->extractNumericAudit($audits, 'first-contentful-paint', 0.001); // ms → s
        $lcp = $this->extractNumericAudit($audits, 'largest-contentful-paint', 0.001);
        $tbt = $this->extractNumericAudit($audits, 'total-blocking-time', 1.0);      // already ms
        $cls = $this->extractNumericAudit($audits, 'cumulative-layout-shift', 1.0);

        // Field data from CrUX — only present when the URL has enough Chrome user traffic.
        $loading = $data['loadingExperience'] ?? [];
        $fieldOverall = isset($loading['overall_category']) ? (string) $loading['overall_category'] : null;
        $fieldMetrics = $loading['metrics'] ?? [];
        $fieldLcp = isset($fieldMetrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'])
            ? (float) $fieldMetrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile']
            : null;
        $fieldInp = isset($fieldMetrics['INTERACTION_TO_NEXT_PAINT']['percentile'])
            ? (float) $fieldMetrics['INTERACTION_TO_NEXT_PAINT']['percentile']
            : null;
        $fieldCls = isset($fieldMetrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'])
            ? (float) $fieldMetrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100.0
            : null;

        // Failed audits → Recommendation list
        $recommendations = [];
        foreach ($audits as $auditId => $audit) {
            if (in_array($auditId, self::SKIP_AUDIT_IDS, true)) {
                continue;
            }
            $auditScore = $audit['score'] ?? null;
            // Lighthouse marks `score=null` for non-applicable audits.
            // `score=1.0` means passed. We want anything that didn't pass cleanly.
            if ($auditScore === null || $auditScore >= 0.9) {
                continue;
            }
            $impactMs = (float) ($audit['details']['overallSavingsMs'] ?? 0);
            $recommendations[] = new Recommendation(
                auditId:        (string) $auditId,
                title:          (string) ($audit['title'] ?? $auditId),
                description:    (string) ($audit['description'] ?? ''),
                impactSeconds:  $impactMs / 1000.0,
                etechflowFix:   $this->mapper->getFix((string) $auditId)
            );
        }
        // Sort by estimated savings DESC so the biggest wins surface first.
        usort($recommendations, fn(Recommendation $a, Recommendation $b)
            => $b->impactSeconds <=> $a->impactSeconds);

        return new DiagnosticResult(
            url:                  $url,
            strategy:             $strategy,
            performanceScore:     $score,
            labFcpSeconds:        $fcp,
            labLcpSeconds:        $lcp,
            labTbtMillis:         $tbt,
            labClsScore:          $cls,
            fieldLcpMillis:       $fieldLcp,
            fieldInpMillis:       $fieldInp,
            fieldClsScore:        $fieldCls,
            fieldOverallCategory: $fieldOverall,
            recommendations:      $recommendations,
            rawJson:              $rawJson
        );
    }

    /**
     * @param array<string, mixed> $audits
     */
    private function extractNumericAudit(array $audits, string $auditId, float $scaleFactor): ?float
    {
        $value = $audits[$auditId]['numericValue'] ?? null;
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value * $scaleFactor;
    }

    /**
     * Pull a useful error message out of Google's typical error envelope:
     *   { "error": { "code": 400, "message": "...", "status": "..." } }
     */
    private function extractApiError(string $body, int $statusCode): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            return sprintf('Google PSI API: %s (HTTP %d)', $decoded['error']['message'], $statusCode);
        }
        return sprintf('Google PSI API returned HTTP %d', $statusCode);
    }

    private function failedResult(string $url, string $strategy, string $message): DiagnosticResult
    {
        return new DiagnosticResult(
            url:                  $url,
            strategy:             $strategy,
            performanceScore:     -1,
            labFcpSeconds:        null,
            labLcpSeconds:        null,
            labTbtMillis:         null,
            labClsScore:          null,
            fieldLcpMillis:       null,
            fieldInpMillis:       null,
            fieldClsScore:        null,
            fieldOverallCategory: null,
            recommendations:      [],
            rawJson:              null,
            errorMessage:         $message
        );
    }
}
