<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Psi;

use ETechFlow\ImageOptimizer\Model\Data\DiagnosticResult;
use ETechFlow\ImageOptimizer\Model\DiagnosticLog;
use ETechFlow\ImageOptimizer\Model\ResourceModel\DiagnosticLog as DiagnosticLogResource;
use Psr\Log\LoggerInterface;

/**
 * Persists DiagnosticResult to the etechflow_io_diagnostic_log table.
 *
 * Failure to write never affects the caller — the diagnostic result is
 * already in hand; logging is best-effort for the future timeline graph.
 */
class DiagnosticLogger
{
    public function __construct(
        private readonly DiagnosticLogResource $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function log(DiagnosticResult $result): void
    {
        try {
            $this->resource->getConnection()->insert(
                $this->resource->getMainTable(),
                [
                    'url'               => $result->url,
                    'strategy'          => $result->strategy,
                    'performance_score' => $result->performanceScore >= 0 ? $result->performanceScore : null,
                    'lab_fcp_seconds'   => $result->labFcpSeconds,
                    'lab_lcp_seconds'   => $result->labLcpSeconds,
                    'lab_tbt_ms'        => $result->labTbtMillis,
                    'lab_cls'           => $result->labClsScore,
                    'field_lcp_ms'      => $result->fieldLcpMillis,
                    'field_inp_ms'      => $result->fieldInpMillis,
                    'field_cls'         => $result->fieldClsScore,
                    'field_category'    => $result->fieldOverallCategory,
                    'status'            => $result->failed() ? DiagnosticLog::STATUS_FAILED : DiagnosticLog::STATUS_OK,
                    'error_message'     => $result->errorMessage,
                    'raw_json'          => $result->rawJson,
                    'run_at'            => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'ETechFlow_IO failed to log a PSI diagnostic',
                ['url' => $result->url, 'exception' => $e->getMessage()]
            );
        }
    }
}
