<?php

namespace Gabrielqs\Pagseguro\Model\Redirect;

use \Magento\Sales\Model\Order;
use \Magento\Framework\HTTP\ZendClient as HttpClient;
use \Magento\Framework\HTTP\ZendClientFactory as HttpClientFactory;
use \Gabrielqs\PagSeguro\Helper\Redirect\Data as RedirectHelper;

class Api
{
    /**
     * Shipping Type PAC
     */
    const SHIPPING_TYPE_PAC = 1;

    /**
     * Shipping Type SEDEX
     */
    const SHIPPING_TYPE_SEDEX = 1;

    /**
     * Shipping Type Not specified
     */
    const SHIPPING_TYPE_NOT_SPECIFIED = 1;

    /**
     * URL API Production
     */
    const URL_API_PRODUCTION = 'https://ws.pagseguro.uol.com.br/v2/checkout';

    /**
     * URL API Test
     */
    const URL_API_TEST = 'https://ws.sandbox.pagseguro.uol.com.br/v2/checkout';

    /**
     * URL Redirection Production
     */
    const URL_REDIRECTION_PRODUCTION = 'https://pagseguro.uol.com.br/v2/checkout/payment.html?code=';

    /**
     * URL Redirection Test
     */
    const URL_REDIRECTION_TEST = 'https://sandbox.pagseguro.uol.com.br/v2/checkout/payment.html?code=';

    /**
     * Last Request
     * @var string
     */
    protected $_lastRequest = null;

    /**
     * Last Response
     * @var string
     */
    protected $_lastResponse = null;

    /**
     * Redirect Helper
     * @var RedirectHelper
     */
    protected $_redirectHelper = null;

    /**
     * Api constructor.
     * @param RedirectHelper $redirectHelper
     */
    public function __construct(
        RedirectHelper $redirectHelper
    ) {
        $this->_redirectHelper = $redirectHelper;
    }

    /**
     * Formats a float value into the notation used by PagSeguro
     * @param float $value
     * @return string
     */
    protected function _formatFloatValue($value)
    {
        $value = (float) $value;
        return number_format($value, 2, '.', '');
    }

    /**
     * Formats a float weight value into the notation used by PagSeguro
     * @param float $value
     * @return string
     */
    protected function _formatWeightValue($value)
    {
        $value = (float) $value;
        $return = number_format($value, 0, '.', '');
        if (!$return) {
            $return = 1;
        }
        return $return;
    }

    /**
     * Gets Pagseguro Api base URL
     * @return string
     */
    protected function _getPagseguroApiBaseUrl()
    {
        if ($this->_redirectHelper->isTest()) {
            $return = self::URL_API_TEST;
        } else {
            $return = self::URL_API_PRODUCTION;
        }
        return $return;
    }

    /**
     * Connects to PagSeguro and creates a code for an order
     * The code will be used later on when redirecting the user to finish the payment
     * @param Order $order
     * @return string
     */
    protected function _makePagseguroPayment(Order $order)
    {
        $address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();
        $splitPhone = $this->_splitPhone($address->getTelephone());

        $params = [
            'email' => $this->_redirectHelper->getMerchantEmail(),
            'token' => $this->_redirectHelper->getIntegrationToken(),
            'currency' => 'BRL',
            'reference' => $order->getIncrementId(),
            'senderName' => $order->getCustomerName(),
            'senderAreaCode' => $splitPhone->areaCode,
            'senderPhone' => $splitPhone->telephone,
            'senderEmail' => $order->getCustomerEmail(),
            'shippingType' => $this->_getShippingType($order),
            'shippingAddressStreet' => $address->getStreetLine(1),
            'shippingAddressNumber' => $address->getStreetLine(2),
            'shippingAddressComplement' => $address->getStreetLine(3),
            'shippingAddressDistrict' => $address->getStreetLine(4),
            'shippingAddressPostalCode' => $address->getPostcode(),
            'shippingAddressCity' => $address->getCity(),
            'shippingAddressState' => $address->getRegionCode(),
            'shippingAddressCountry' => $address->getCountryId(),
        ];

        $items = $order->getAllVisibleItems();
        $i = 0;
        foreach ($items as $item) {
            if (((float)$item->getBasePrice() == 0)) {
                continue;
            }
            $itemQty = $item->getQtyOrdered();
            if ($children = $item->getChildrenItems()) {
                $itemPrice = $item->getBasePrice() * $itemQty;
                $itemWeight = $item->getWeight();
                foreach ($children as $child) {
                    $itemPrice += $child->getBasePrice() * $child->getQtyOrdered();
                }
            } else {
                $itemPrice = $item->getBasePrice() * $itemQty;
                $itemWeight = $item->getWeight();
            }
            $i++;
            $params['itemId' . $i] = $item->getSku();
            $params['itemDescription' . $i] = $item->getName();
            $params['itemAmount' . $i] = $this->_formatFloatValue($itemPrice);
            $params['itemQuantity' . $i] = $itemQty;
            $params['itemWeight' . $i] = $this->_formatWeightValue($itemWeight);
        }

        $response = $this->_makeRequest($params);
        return $response;
    }

    /**
     * Returns Last Request from the HTTP Client
     * @return string
     */
    public function getLastRequest()
    {
        return $this->_lastRequest;
    }

    /**
     * Returns Last Response from the HTTP Client
     * @return string
     */
    public function getLastResponse()
    {
        return $this->_lastResponse;
    }

    /**
     * Returns the URL for which the user will be redirected for paying
     * @param Order $order
     * @return string
     */
    public function getPaymentCode(Order $order)
    {
        $return = false;
        $paymentResponse = $this->_makePagseguroPayment($order);
        libxml_use_internal_errors(true);
        $paymentResponseXml = simplexml_load_string($paymentResponse);
        if (isset($paymentResponseXml->code) && $code = ((string) $paymentResponseXml->code)) {
            $return = $code;
        }
        libxml_clear_errors();
        return $return;
    }

    /**
     * Returns the URL for which the user will be redirected for paying
     * @param string $code
     * @return string
     */
    public function getPaymentUrl($code)
    {
        if ($this->_redirectHelper->isTest()) {
            $return = self::URL_REDIRECTION_TEST;
        } else {
            $return = self::URL_REDIRECTION_PRODUCTION;
        }
        $return .= $code;
        return $return;
    }

    /**
     * Returns the shipping code for a given order
     * @param Order $order
     * @return string
     */
    protected function _getShippingType(Order $order)
    {
        $shippingMethodCode = $order->getShippingMethod();
        if (preg_match('/40010/', $shippingMethodCode) || preg_match('/40096/', $shippingMethodCode) ||
            preg_match('/40215/', $shippingMethodCode) || preg_match('/40290/', $shippingMethodCode) ||
            preg_match('/40045/', $shippingMethodCode) ) {
            # Sedex
            $return = self::SHIPPING_TYPE_SEDEX;
        } else if (preg_match('/41106/', $shippingMethodCode) || preg_match('/41068/', $shippingMethodCode)) {
            # PAC
            $return = self::SHIPPING_TYPE_PAC;
        } else {
            # Not specified
            $return = self::SHIPPING_TYPE_NOT_SPECIFIED;
        }
        return $return;
    }

    /**
     * Makes a request for the Pagseguro Payment Api
     * @param array $params
     * @return string
     */
    protected function _makeRequest($params)
    {
        $postQuery =  http_build_query($params, '', '&');
        $postHeaders = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'Content-length: ' . strlen($postQuery)
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getPagseguroApiBaseUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postQuery);
        $result = curl_exec($ch);

        $this->_lastRequest = $postQuery;
        $this->_lastResponse = $result;

        return $result;
    }

    /**
     * Splits a brazilian phone number in it's area code and actual phone number
     * @param string $phone
     * @return \stdClass
     */
    protected function _splitPhone($phone)
    {
        # Return
        $return = new \stdClass();
        $return->areaCode = null;
        $return->telephone = null;

        # Removing non special chars
        if (!is_numeric( $phone )) {
            $phone = preg_replace( '#[^\d]#', "", $phone );
        }

        # In case we can't find a decent phone, we shouldn't proceed
        # Pagseguro won't accept a payment if it doesn't have a valid phone number
        if (strlen( $phone ) < 10 || strlen( $phone ) > 12) {
            return $return;
        }

        # In case the first digit is a 0, we remove it.
        if ((strlen($phone) == 12 || strlen($phone) == 11) && (substr( $phone, 0, 1 ) == 0)) {
            $phone = substr($phone, 1, (strlen($phone)-1));
        }

        # In case we can't find a decent phone, we shouldn't proceed as we don't know how to handle it
        if (strlen($phone) != 10 && strlen($phone) != 11) {
            return $return;
        }

        # Final tests
        if ( is_numeric($phone) && ( ( strlen($phone) == 10 ) || ( strlen($phone) == 11 ) ) ) {
            $return->areaCode = substr($phone, 0, 2);
            $return->telephone = substr($phone, 2, (strlen($phone) - 2 ));
        } else {
            return $return;
        }

        return $return;
    }
}