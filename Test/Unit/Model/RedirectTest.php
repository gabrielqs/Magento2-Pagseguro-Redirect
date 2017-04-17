<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Model;

use \Magento\Framework\Exception\LocalizedException;
use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Framework\DataObject;
use \Gabrielqs\Pagseguro\Model\Redirect as Subject;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;
use \Gabrielqs\Pagseguro\Model\Redirect\Api;

/**
 * Redirect Test Case
 */
class RedirectTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Api
     */
    protected $api = null;

    /**
     * @var string
     */
    protected $className = null;

    /**
     * @var DataObject
     */
    protected $dataObject = null;

    /**
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * @var Api
     */
    protected $originalSubject = null;

    /**
     * @var RedirectHelper
     */
    protected $redirectHelper = null;

    /**
     * @var Api
     */
    protected $subject = null;

    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->className = Subject::class;

        $this->subject = $this
            ->getMockBuilder($this->className)
            ->setMethods(['getInfoInstance', '_getApi', 'canRefund', 'getConfigData'])
            ->setConstructorArgs($this->getConstructorArguments())
            ->getMock();

        $this->dataObject = $this
            ->getMockBuilder('\Magento\Framework\DataObject')
            ->setMethods(['getAdditionalData'])
            ->getMock();

        $this
            ->subject
            ->expects($this->any())
            ->method('_getApi')
            ->will($this->returnValue($this->api));

        $this->originalSubject = $this->objectManager->getObject($this->className);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->redirectHelper = $this
            ->getMockBuilder(RedirectHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getIntegrationToken', 'getMerchantEmail', 'isTest'])
            ->getMock();
        $arguments['redirectHelper'] = $this->redirectHelper;

        $this->api = $this
            ->getMockBuilder(Api::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPaymentCode', 'getPaymentUrl', 'getLastRequest', 'getLastResponse'])
            ->getMock();
        $arguments['api'] = $this->api;

        return $arguments;
    }


    public function testCanUseForCurrencySupported()
    {
        $this->assertTrue($this->subject->canUseForCurrency('BRL'));
    }

    public function testCanUseForCurrencyUnsupported()
    {
        $this->assertNotTrue($this->subject->canUseForCurrency('USD'));
    }


    public function testIsAvailableShouldReturnFalseWhenMethodIsNotActive()
    {
        $this
            ->subject
            ->expects($this->once())
            ->method('getConfigData')
            ->with('active')
            ->will($this->returnValue(false));

        $this->assertNotTrue($this->subject->isAvailable());
    }

    public function testIsAvailableShouldReturnFalseWhenNoQuoteAvailable()
    {
        $this
            ->subject
            ->expects($this->once())
            ->method('getConfigData')
            ->with('active')
            ->will($this->returnValue(true));

        $quote = null;

        $this->assertNotTrue($this->subject->isAvailable($quote));
    }

    public function testIsAvailableShouldReturnFalseWhenGrandTotalIsLessThanMinimum()
    {
        $this
            ->subject
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                ['active', null],
                ['min_order_total', null]
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(true),
                $this->returnValue(50)
            );

        $quote = $this
            ->getMockBuilder('\Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(['getBaseGrandTotal'])
            ->getMock();
        $quote
            ->expects($this->once())
            ->method('getBaseGrandTotal')
            ->will($this->returnValue(20));

        $this->assertNotTrue($this->subject->isAvailable($quote));
    }

    public function testIsAvailableShouldReturnFalseWhenGrandTotalIsGreaterThanMaximumAndMaximumIsSet()
    {
        $this
            ->subject
            ->expects($this->exactly(4))
            ->method('getConfigData')
            ->withConsecutive(
                ['active', null],
                ['min_order_total', null],
                ['max_order_total', null],
                ['max_order_total', null]
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(true),
                $this->returnValue(5),
                $this->returnValue(10000),
                $this->returnValue(10000)
            );

        $quote = $this
            ->getMockBuilder('\Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(['getBaseGrandTotal'])
            ->getMock();
        $quote
            ->expects($this->exactly(2))
            ->method('getBaseGrandTotal')
            ->will($this->returnValue(100000));

        $this->assertNotTrue($this->subject->isAvailable($quote));
    }

    public function testIsAvailableShouldReturnFalseWhenNotInTestModeAndConfigurationHasNotBeenMadeCompanyId()
    {
        $this
            ->subject
            ->expects($this->exactly(4))
            ->method('getConfigData')
            ->withConsecutive(
                ['active', null],
                ['min_order_total', null],
                ['max_order_total', null],
                ['max_order_total', null]
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(true),
                $this->returnValue(5),
                $this->returnValue(999999),
                $this->returnValue(999999)
            );

        $quote = $this
            ->getMockBuilder('\Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(['getBaseGrandTotal'])
            ->getMock();
        $quote
            ->expects($this->exactly(2))
            ->method('getBaseGrandTotal')
            ->will($this->returnValue(1000));

        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('getMerchantEmail')
            ->will($this->returnValue(''));
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isTest')
            ->will($this->returnValue(false));

        $this->assertNotTrue($this->subject->isAvailable($quote));
    }

    public function testIsAvailableShouldReturnFalseWhenNotInTestModeAndConfigurationHasNotBeenMadeAccessKey()
    {
        $this
            ->subject
            ->expects($this->exactly(4))
            ->method('getConfigData')
            ->withConsecutive(
                ['active', null],
                ['min_order_total', null],
                ['max_order_total', null],
                ['max_order_total', null]
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue(true),
                $this->returnValue(5),
                $this->returnValue(999999),
                $this->returnValue(999999)
            );

        $quote = $this
            ->getMockBuilder('\Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(['getBaseGrandTotal'])
            ->getMock();
        $quote
            ->expects($this->exactly(2))
            ->method('getBaseGrandTotal')
            ->will($this->returnValue(1000));

        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('getMerchantEmail')
            ->will($this->returnValue('123123123'));
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('getIntegrationToken')
            ->will($this->returnValue(''));
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isTest')
            ->will($this->returnValue(false));

        $this->assertNotTrue($this->subject->isAvailable($quote));
    }


    public function testOrderCallsApiAndSetsInfoToPayment()
    {
        $code = 'MAKSNE-02934MX-320923-C0S9CSD23';
        $paymentUrl = 'http://pagseguro.com.br/pay/' . $code;
        $lastRequest = 'param1=lala&param2=lala';
        $lastResponse = '<xmlReturn><foo><bar>1</bar><baz>laga</baz></foo></xmlReturn>';

        $order = $this
            ->getMock('Magento\Sales\Model\Order', [], [], '', false);

        $this
            ->api
            ->expects($this->once())
            ->method('getPaymentCode')
            ->with($this->equalTo($order))
            ->will($this->returnValue($code));

        $this
            ->api
            ->expects($this->once())
            ->method('getPaymentUrl')
            ->with($this->equalTo($code))
            ->will($this->returnValue($paymentUrl));

        $this
            ->api
            ->expects($this->once())
            ->method('getLastRequest')
            ->will($this->returnValue($lastRequest));

        $this
            ->api
            ->expects($this->once())
            ->method('getLastResponse')
            ->will($this->returnValue($lastResponse));

        $payment = $this
            ->getMock('Magento\Sales\Model\Order\Payment', [
                'getOrder',
                'setAmount',
                'setStatus',
                'setIsTransactionPending',
                'setAdditionalInformation'
            ], [], '', false);

        $payment
            ->expects($this->any())
            ->method('getOrder')
            ->will($this->returnValue($order));

        $payment
            ->expects($this->any())
            ->method('setAmount')
            ->with(490.38)
            ->will($this->returnValue($payment));

        $payment
            ->expects($this->any())
            ->method('setStatus')
            ->with(Subject::STATUS_SUCCESS)
            ->will($this->returnValue($payment));
        $payment
            ->expects($this->any())
            ->method('setIsTransactionPending')
            ->with(false)
            ->will($this->returnValue($payment));
        $payment
            ->expects($this->exactly(4))
            ->method('setAdditionalInformation')
            ->withConsecutive(
                ['pagseguro_code', $code],
                ['pagseguro_payment_url', $paymentUrl],
                ['pagseguro_request', $lastRequest],
                ['pagseguro_response', $lastResponse]
            )
            ->willReturnOnConsecutiveCalls(
                $this->returnValue($payment),
                $this->returnValue($payment),
                $this->returnValue($payment),
                $this->returnValue($payment)
            );

        $this->subject->order($payment, 490.38);
    }

    public function dataProviderTestOrderThrowsExceptionWhenNoCodeReturned()
    {
        return [
            [null, null],
            ['MAKSNE-02934MX-320923-C0S9CSD23', null],
            [null, 'http://pagseguro.com.br/pay/MAKSNE-02934MX-320923-C0S9CSD23'],
        ];
    }

    /**
     * @param $code
     * @param $paymentUrl
     * @dataProvider dataProviderTestOrderThrowsExceptionWhenNoCodeReturned
     */
    public function testOrderThrowsExceptionWhenNoCodeReturned($code, $paymentUrl)
    {
        $order = $this
            ->getMock('Magento\Sales\Model\Order', [], [], '', false);

        $this
            ->api
            ->expects($this->once())
            ->method('getPaymentCode')
            ->with($this->equalTo($order))
            ->will($this->returnValue($code));

        $this
            ->api
            ->expects($this->once())
            ->method('getPaymentUrl')
            ->with($this->equalTo($code))
            ->will($this->returnValue($paymentUrl));

        $payment = $this
            ->getMock('Magento\Sales\Model\Order\Payment', [
                'getOrder',
                'setAmount',
                'setStatus',
                'setIsTransactionPending',
                'setAdditionalInformation'
            ], [], '', false);

        $payment
            ->expects($this->any())
            ->method('getOrder')
            ->will($this->returnValue($order));

        $this->setExpectedException(LocalizedException::class);

        $this->subject->order($payment, 490.38);
    }
}