<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Centralised reader for the module's admin config + license-aware gate.
 *
 * isEnabled() returns false when EITHER the admin "Module Enabled" toggle
 * is No OR the license isn't valid. Calling code just checks isEnabled().
 */
class Config
{
    public const XML_PATH_ENABLED          = 'etechflow_io/general/enabled';
    public const XML_PATH_QUALITY          = 'etechflow_io/general/quality';
    public const XML_PATH_ENGINE_ORDER     = 'etechflow_io/general/engine_order';
    public const XML_PATH_BATCH_SIZE       = 'etechflow_io/general/batch_size';
    public const XML_PATH_PICTURE_ENABLED  = 'etechflow_io/output/picture_block';
    public const XML_PATH_LAZY_LOAD        = 'etechflow_io/output/lazy_load';
    public const XML_PATH_PATHS_PRODUCT    = 'etechflow_io/coverage/product_cache';
    public const XML_PATH_PATHS_CATEGORY   = 'etechflow_io/coverage/category';
    public const XML_PATH_PATHS_CMS        = 'etechflow_io/coverage/cms';

    public const XML_PATH_PSI_API_KEY      = 'etechflow_io/psi/api_key';
    public const XML_PATH_PSI_STRATEGY     = 'etechflow_io/psi/default_strategy';
    public const XML_PATH_PSI_TIMEOUT      = 'etechflow_io/psi/timeout_seconds';

    /**
     * Quality default — sweet spot between file size and visible quality.
     * 80 is the value most competitor modules also default to; cwebp's
     * own docs recommend 75-85 for photo content.
     */
    private const DEFAULT_QUALITY = 80;

    private const DEFAULT_BATCH_SIZE = 200;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    public function isEnabled(): bool
    {
        if (!$this->licenseValidator->isValid()) {
            return false;
        }
        return $this->isAdminEnabled();
    }

    public function isAdminEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getQuality(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_QUALITY, ScopeInterface::SCOPE_STORE);
        if ($value < 1 || $value > 100) {
            return self::DEFAULT_QUALITY;
        }
        return $value;
    }

    /**
     * Comma-separated list of engine names in priority order.
     * Default: cwebp,imagick,gd — try fastest binary first, fall back to ext.
     *
     * @return string[]
     */
    public function getEngineOrder(): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_ENGINE_ORDER, ScopeInterface::SCOPE_STORE);
        if ($value === '') {
            return ['cwebp', 'imagick', 'gd'];
        }
        $parts = array_filter(array_map('trim', explode(',', strtolower($value))));
        return $parts ?: ['cwebp', 'imagick', 'gd'];
    }

    public function getBatchSize(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE, ScopeInterface::SCOPE_STORE);
        return $value > 0 ? $value : self::DEFAULT_BATCH_SIZE;
    }

    public function isPictureBlockEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PICTURE_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function isLazyLoadEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_LAZY_LOAD, ScopeInterface::SCOPE_STORE);
    }

    public function isProductCacheCovered(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PATHS_PRODUCT, ScopeInterface::SCOPE_STORE);
    }

    public function isCategoryCovered(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PATHS_CATEGORY, ScopeInterface::SCOPE_STORE);
    }

    public function isCmsCovered(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PATHS_CMS, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Stored encrypted in the DB via Magento\Config\Model\Config\Backend\Encrypted.
     * The Encryptor service decrypts it when ScopeConfigInterface reads. Caller
     * just receives the plaintext API key as a string.
     */
    public function getGooglePsiApiKey(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_PSI_API_KEY, ScopeInterface::SCOPE_STORE);
        return trim((string) $value);
    }

    /**
     * Either 'mobile' or 'desktop'. Mobile is Google's mobile-first indexing
     * default and what we use unless the merchant changes it.
     */
    public function getPsiDefaultStrategy(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_PSI_STRATEGY, ScopeInterface::SCOPE_STORE);
        return in_array($value, ['mobile', 'desktop'], true) ? $value : 'mobile';
    }

    public function getPsiTimeoutSeconds(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_PSI_TIMEOUT, ScopeInterface::SCOPE_STORE);
        return $value > 0 ? $value : 90;
    }
}
