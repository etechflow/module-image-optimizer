<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OptimizationLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_io_optimization_log', 'log_id');
    }
}
