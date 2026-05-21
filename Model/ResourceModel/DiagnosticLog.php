<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DiagnosticLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_io_diagnostic_log', 'log_id');
    }
}
