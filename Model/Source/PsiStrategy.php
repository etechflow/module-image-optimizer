<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PsiStrategy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'mobile',  'label' => __('Mobile (recommended — mobile-first indexing)')],
            ['value' => 'desktop', 'label' => __('Desktop')],
        ];
    }
}
