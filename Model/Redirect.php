<?php

namespace Gabrielqs\Pagseguro\Model;

use \Magento\Framework\Exception\LocalizedException;
use \Magento\Payment\Model\InfoInterface;
use \Magento\Framework\Model\Context;
use \Magento\Framework\Registry;
use \Magento\Framework\Api\ExtensionAttributesFactory;
use \Magento\Framework\Api\AttributeValueFactory;
use \Magento\Payment\Helper\Data;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Payment\Model\Method\Logger;
use \Magento\Framework\Model\ResourceModel\AbstractResource;
use \Magento\Framework\Data\Collection\AbstractDb;
use \Magento\Directory\Model\CountryFactory;
use \Magento\Quote\Api\Data\CartInterface;
use \Magento\Payment\Model\Method\AbstractMethod as AbstractPaymentMethod;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;
use \Gabrielqs\Pagseguro\Model\Redirect\Api as Api;


/**
 * Pagseguro Redirect Credit Card Payment Method
 */
class Redirect extends AbstractPaymentMethod
{

    const CODE = 'pagseguro_redirect';

    /**
     * Redirect Info Block
     * @var string
     */
    protected $_infoBlockType = 'Gabrielqs\Pagseguro\Block\Redirect\Info';

    /**
     * Redirect API
     * @var Api
     */
    protected $_api                         = null;

    /**
     * Can Authorize
     * @var bool
     */
    protected $_canAuthorize                = false;

    /**
     * Can Capture
     * @var bool
     */
    protected $_canCapture                  = false;

    /**
     * Can Capture Partial
     * @var bool
     */
    protected $_canCapturePartial           = false;

    /**
     * Can Order
     * @var bool
     */
    protected $_canOrder                    = true;

    /**
     * Can Refund
     * @var bool
     */
    protected $_canRefund                   = false;

    /**
     * Can Refund Partial
     * @var bool
     */
    protected $_canRefundInvoicePartial     = false;

    /**
     * Pagseguro Payment Method Code
     * @var string
     */
    protected $_code                        = self::CODE;

    /**
     * Is Payment Gateway?
     * @var bool
     */
    protected $_isGateway                   = true;

    /**
     * Is Offline Payment=
     * @var bool
     */
    protected $_isOffline                   = true;

    /**
     * Supported Currency Codes
     * @var string[]
     */
    protected $_supportedCurrencyCodes      = ['BRL'];

    /**
     * Country Factory
     * @var CountryFactory|null
     */
    protected $_countryFactory              = null;

    /**
     * Pagseguro Helper
     * @var RedirectHelper|null
     */
    protected $_redirectHelper            = null;

    /**
     * Pagseguro Helper
     * @var RedirectHelper|null
     */
    protected $_installmentsHelper          = null;

    /**
     * Pagseguro constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param RedirectHelper $redirectHelper
     * @param CountryFactory $countryFactory
     * @param Api $api
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        RedirectHelper $redirectHelper,
        CountryFactory $countryFactory,
        Api $api,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_countryFactory = $countryFactory;
        $this->_redirectHelper = $redirectHelper;
        $this->_api = $api;
        return parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, (array) $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (!$this->getConfigData('active')) {
            return false;
        }
        
        if ($quote && ($quote->getBaseGrandTotal() <= $this->getConfigData('min_order_total') ||
                (
                    $this->getConfigData('max_order_total') &&
                    $quote->getBaseGrandTotal() > $this->getConfigData('max_order_total')
                )
            )
        ) {
            return false;
        }

        if ((!$this->_redirectHelper->getMerchantEmail() || !$this->_redirectHelper->getIntegrationToken()) &&
            (!$this->_redirectHelper->isTest())) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Payment Order
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function order(InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $code = $this->_api->getPaymentCode($order);
        $paymentUrl = $this->_api->getPaymentUrl($code);

        if (!$code || !$paymentUrl) {
            throw new LocalizedException(__('We are sorry, but we couldn\'t process your payment now.' .
                ' Please try again later.'));
        }

        $lastRequest = $this->_api->getLastRequest();
        $lastResponse = $this->_api->getLastResponse();

        $payment
            ->setAmount($amount)
            ->setStatus(self::STATUS_SUCCESS)
            ->setIsTransactionPending(false)
            ->setAdditionalInformation('pagseguro_code', $code)
            ->setAdditionalInformation('pagseguro_payment_url', $paymentUrl)
            ->setAdditionalInformation('pagseguro_request', $lastRequest)
            ->setAdditionalInformation('pagseguro_response', $lastResponse);

        return $this;
    }
}
