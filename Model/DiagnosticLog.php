<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model;

use ETechFlow\ImageOptimizer\Model\ResourceModel\DiagnosticLog as DiagnosticLogResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Lightweight entity for the diagnostic-runs table. Used only by the
 * DiagnosticLogRepository when writing a finished PsiClient result.
 */
class DiagnosticLog extends AbstractModel
{
    public const STATUS_OK     = 'ok';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(DiagnosticLogResource::class);
    }
}
