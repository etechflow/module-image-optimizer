<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Source;

use ETechFlow\ImageOptimizer\Model\OptimizationLog;
use Magento\Framework\Data\OptionSourceInterface;

class StatusOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => OptimizationLog::STATUS_OK,      'label' => __('OK')],
            ['value' => OptimizationLog::STATUS_FAILED,  'label' => __('Failed')],
            ['value' => OptimizationLog::STATUS_SKIPPED, 'label' => __('Skipped')],
        ];
    }
}
