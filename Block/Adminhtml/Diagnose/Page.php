<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Block\Adminhtml\Diagnose;

use ETechFlow\ImageOptimizer\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders the Diagnose admin page: URL input, strategy toggle, Run button,
 * and an empty result container that the inline JS fills via AJAX.
 *
 * Note: `$formKey` is renamed to `$diagnoseFormKey` because the parent
 * Magento\Backend\Block\Template ALREADY declares a non-readonly `$formKey`
 * property. PHP 8.1+ rejects redeclaring a parent property as readonly,
 * so we use a distinct name instead.
 */
class Page extends Template
{
    private Config $config;
    private StoreManagerInterface $storeManager;
    private FormKey $diagnoseFormKey;

    public function __construct(
        Context $context,
        Config $config,
        StoreManagerInterface $storeManager,
        FormKey $diagnoseFormKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->diagnoseFormKey = $diagnoseFormKey;
    }

    public function getDefaultStrategy(): string
    {
        return $this->config->getPsiDefaultStrategy();
    }

    /**
     * Default URL = the current store's base URL. Admin can change it
     * to any URL they want to test.
     */
    public function getDefaultUrl(): string
    {
        try {
            return rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getRunActionUrl(): string
    {
        return $this->getUrl('etechflow_io/diagnose/run');
    }

    /**
     * Return the form key — child-method override that uses our injected
     * FormKey, not the parent class's `$formKey` property.
     */
    public function getFormKey(): string
    {
        return $this->diagnoseFormKey->getFormKey();
    }

    public function hasApiKey(): bool
    {
        return $this->config->getGooglePsiApiKey() !== '';
    }

    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'etechflow_io']);
    }
}
