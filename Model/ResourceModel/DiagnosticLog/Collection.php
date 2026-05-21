<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\ResourceModel\DiagnosticLog;

use ETechFlow\ImageOptimizer\Model\DiagnosticLog;
use ETechFlow\ImageOptimizer\Model\ResourceModel\DiagnosticLog as DiagnosticLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(DiagnosticLog::class, DiagnosticLogResource::class);
    }
}
