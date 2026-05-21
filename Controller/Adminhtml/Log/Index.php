<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'ETechFlow_ImageOptimizer::log';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('ETechFlow_ImageOptimizer::log');
        $page->getConfig()->getTitle()->prepend(__('Image Optimization Log'));
        return $page;
    }
}
