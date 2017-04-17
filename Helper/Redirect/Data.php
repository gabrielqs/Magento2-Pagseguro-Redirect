<?php

namespace Gabrielqs\Pagseguro\Helper\Redirect;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\ScopeInterface;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Framework\Exception\LocalizedException;
use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\UrlInterface;
use \Magento\Framework\UrlFactory;
use \Gabrielqs\Pagseguro\Model\Redirect;


class Data extends AbstractHelper
{
    /**
     * Store manager interface
     * @var StoreManagerInterface $_storeManager
     */
    protected $_storeManager = null;

    /**
     * Core store config
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig = null;

    /**
     * Url Model
     * @var UrlInterface
     */
    protected $urlModel;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param UrlFactory $urlFactory
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        UrlFactory $urlFactory
    ) {
        $this->_scopeConfig  = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->urlModel = $urlFactory->create();
        parent::__construct($context);
    }

    /**
     * Returns Pagseguro integration token
     *
     * @return string $token
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getIntegrationToken()
    {
        $return = null;
        if ($this->isTest()) {
            $return = $this->getConfigData('test_integration_token');
        } else {
            $return = $this->getConfigData('integration_token');
        }
        if (!$return) {
            throw new LocalizedException(__('Pagseguro integration token not yet configured'));
        }
        return $return;
    }

    /**
     * Returns Pagseguro Payment Method System Config
     *
     * @param string $field
     * @param null $storeId
     * @return array|string
     */
    public function getConfigData($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore(null);
        }
        $path = 'payment/' . $this->getMethodCode() . '/' . $field;
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns Pagseguro merchant email
     *
     * @return string $email
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMerchantEmail()
    {
        $return = null;
        if ($this->isTest()) {
            $return = $this->getConfigData('test_merchant_email');
        } else {
            $return = $this->getConfigData('merchant_email');
        }
        if (!$return) {
            throw new LocalizedException(__('Pagseguro merchant email not yet configured'));
        }
        return $return;
    }

    /**
     * Returns Pagseguro Redirect Method Code
     * @return string
     */
    public function getMethodCode()
    {
        return Redirect::CODE;
    }

    /**
     * Returns PagSeguro Redirect Pre-Redirect URL (internal)
     * @return string
     */
    public function getPreRedirectUrl()
    {
        return $this->urlModel->getUrl('pagseguro/redirect', ['_secure' => true]);
    }

    /**
     * Should send e-mail notifying invoice creation?
     * @return bool
     */
    public function isSendInvoiceEmail()
    {
        return (bool) $this->getConfigData('send_invoice_email');
    }

    /**
     * Are we in test mode?
     * @return bool
     */
    public function isTest()
    {
        return (bool) $this->getConfigData('test_mode_enabled');
    }

}