<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Config
{

    public const ACTIVE_CONFIG_PATH = 'universign/configuration/active';
    public const ACTIVE_DEMO_CONFIG_PATH = 'universign/configuration/active_demo';
    public const API_URL_CONFIG_PATH = 'universign/configuration/api_url';
    public const API_KEY_CONFIG_PATH = 'universign/configuration/api_key';
    public const API_URL_TEST_CONFIG_PATH = 'universign/configuration/api_url_test';
    public const API_KEY_TEST_CONFIG_PATH = 'universign/configuration/api_key_test';
    public const PROD_MODE_CONFIG_PATH = 'universign/configuration/prod_mode';
    public const DEFAULT_TRANSACTION_NAME_CONFIG_PATH = 'universign/configuration/default_transaction_name';
    public const DEFAULT_DOCUMENT_NAME_CONFIG_PATH = 'universign/configuration/default_document_name';
    public const DEFAULT_COUNTRY_ID_CONFIG_PATH = 'universign/configuration/default_country_id';


    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private ScopeConfigInterface  $scopeConfig,
        private StoreManagerInterface $storeManager,
        private LoggerInterface       $logger,
    )
    {
    }

    /**
     *
     * @return bool
     */
    public function isUniversignActive(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::ACTIVE_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return bool
     */
    public function isDemoActive(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::ACTIVE_DEMO_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return bool
     */
    public function isProductionMode(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::PROD_MODE_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return string
     */
    public function getDefaultTransactionName(): string
    {
        return $this->scopeConfig->getValue(
            self::DEFAULT_TRANSACTION_NAME_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return string
     */
    public function getDefaultDocumentName(): string
    {
        return $this->scopeConfig->getValue(
            self::DEFAULT_DOCUMENT_NAME_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }
    /**
     *
     * @return string
     */
    public function getDefaultCountryId(): string
    {
        return $this->scopeConfig->getValue(
            self::DEFAULT_COUNTRY_ID_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        $path = self::API_URL_TEST_CONFIG_PATH;

        if ($this->isProductionMode()) {
            $path = self::API_URL_CONFIG_PATH;
        }

        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }

    /**
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        $path = self::API_KEY_TEST_CONFIG_PATH;

        if ($this->isProductionMode()) {
            $path = self::API_KEY_CONFIG_PATH;
        }

        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStore());
    }


    /**
     *
     * @return StoreInterface|null
     */
    protected function getStore(): ?StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }
}
