<?php

namespace Gabrielqs\Pagseguro\Controller\Redirect;

use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Action\Action;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Framework\Exception\LocalizedException;
use \Magento\Framework\Controller\Result\Redirect;

/**
 * Class Index
 * @package Gabrielqs\Pagseguro\Controller\Redirect
 */
class Index extends Action
{
    /**
     * Checkout Session
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * Index constructor.
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
        return parent::__construct($context);
    }

    /**
     * Gets result redirect
     * @return Redirect
     */
    protected function _getResultRedirect()
    {
        return $this->resultRedirectFactory->create();
    }

    /**
     * Responsible for redirecting the user to PagSeguro after a successful checkout
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        $payment = $lastOrder->getPayment();
        $payUrl = $payment->getAdditionalInformation('pagseguro_payment_url');
        if (!$payUrl) {
            throw new LocalizedException(__('We didn\'t receive a valid return from PagSeguro'));
        }
        return $this->_getResultRedirect()->setUrl($payUrl);
    }
}
