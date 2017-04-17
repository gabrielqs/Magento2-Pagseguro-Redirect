<?php

namespace Gabrielqs\Pagseguro\Model\Redirect;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\CcConfig;
use Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;
use Magento\Framework\View\Asset\Repository as AssetRepo;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * AssetRepo
     * @var AssetRepo
     */
    protected $_assetRepo = null;

    /**
     * Redirect Helper
     * @var RedirectHelper
     */
    protected $_redirectHelper = null;

    /**
     * ConfigProvider constructor.
     *
     * @param RedirectHelper $redirectHelper
     * @param AssetRepo $assetRepo
     * @param array $methodCodes
     */
    public function __construct(
        RedirectHelper $redirectHelper,
        AssetRepo $assetRepo,
        $methodCodes = []
    ) {
        $this->_assetRepo = $assetRepo;
        $this->_redirectHelper = $redirectHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];

        if ($this->_redirectHelper->getConfigData('active')) {
            $checkoutImageAsset = $this->_assetRepo->createAsset('Gabrielqs_Pagseguro::images/checkout.png');
            $config['payment'][$this->_redirectHelper->getMethodCode()] = [
                'active'               => true,
                'pre_redirect_url'         => $this->_redirectHelper->getPreRedirectUrl(),
                'checkout_image'           => $checkoutImageAsset->getUrl()
            ];
        } else {
            $config['payment'][$this->_redirectHelper->getMethodCode()] = [
                'active'                                  => false,
            ];
        }

        return $config;
    }
}