<?php

namespace Gabrielqs\Pagseguro\Block\Redirect;

use Magento\Payment\Block\Info as AbstractInfo;
use Magento\Framework\View\Element\Template\Context;
use \Magento\Payment\Model\Config as PaymentConfig;
use Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;

class Info extends AbstractInfo
{
    /**
     * Redirect helper
     * @var RedirectHelper
     */
    protected $_redirectHelper = null;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PaymentConfig $paymentConfig
     * @param RedirectHelper $redirectHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        PaymentConfig $paymentConfig,
        RedirectHelper $redirectHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_redirectHelper = $redirectHelper;
    }

    /**
     * Adds specific information to the info block
     * @return string[]
     */
    public function getSpecificInformation()
    {
        $return = parent::getSpecificInformation();
        return $return;
    }
}