<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\ResourceModel\OptimizationLog;

use ETechFlow\ImageOptimizer\Model\OptimizationLog;
use ETechFlow\ImageOptimizer\Model\ResourceModel\OptimizationLog as OptimizationLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(OptimizationLog::class, OptimizationLogResource::class);
    }
}
