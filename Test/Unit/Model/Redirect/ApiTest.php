<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Model\Redirect;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\Order\Address as OrderAddress;
use \Magento\Sales\Model\Order\Item as OrderItem;
use \Gabrielqs\Pagseguro\Model\Redirect\Api;
use \Gabrielqs\Pagseguro\Model\Redirect\Api as Subject;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;

/**
 * Api Test Case
 */
class ApiTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test integration token
     */
    const TEST_INTEGRATION_TOKEN = '25fbb99741c739dd84d7b06ec78c9bac718838630f30b112d033ce2e621b34f3';

    /**
     * Test merchant email
     */
    const TEST_MERCHANT_EMAIL = 'gabrielqsteste@sandbox.pagseguro.com.br';

    /**
     * @var string
     */
    protected $className = null;

    /**
     * @var \ReflectionMethod
     */
    protected $getPagseguroApiBaseUrlMethod = null;

    /**
     * @var \ReflectionMethod
     */
    protected $formatFloatValue = null;

    /**
     * @var \ReflectionMethod
     */
    protected $formatWeightValue = null;

    /**
     * @var \ReflectionMethod
     */
    protected $getShippingTypeMethod = null;

    /**
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * @var Subject
     */
    protected $originalSubject = null;

    /**
     * @var RedirectHelper
     */
    protected $redirectHelper = null;

    /**
     * @var Subject
     */
    protected $splitPhoneMethod = null;

    /**
     * @var \ReflectionMethod
     */
    protected $subject = null;

    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->className = Subject::class;
        $this->subject = $this
            ->getMockBuilder($this->className)
            ->setMethods(['_makeRequest'])
            ->setConstructorArgs($this->getConstructorArguments())
            ->getMock();

        $this->originalSubject = $this->objectManager->getObject($this->className);
        $reflection = new \ReflectionClass($this->objectManager->getObject($this->className));
        $this->getShippingTypeMethod = $reflection->getMethod('_getShippingType');
        $this->getShippingTypeMethod->setAccessible(true);
        $this->getPagseguroApiBaseUrlMethod = $reflection->getMethod('_getPagseguroApiBaseUrl');
        $this->getPagseguroApiBaseUrlMethod->setAccessible(true);
        $this->splitPhoneMethod = $reflection->getMethod('_splitPhone');
        $this->splitPhoneMethod->setAccessible(true);
        $this->formatFloatValue = $reflection->getMethod('_formatFloatValue');
        $this->formatFloatValue->setAccessible(true);
        $this->formatWeightValue = $reflection->getMethod('_formatWeightValue');
        $this->formatWeightValue->setAccessible(true);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->redirectHelper = $this
            ->getMockBuilder(RedirectHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getIntegrationToken', 'isAvsActive', 'getMerchantEmail', 'isTest'])
            ->getMock();
        $arguments['redirectHelper'] = $this->redirectHelper;

        return $arguments;
    }

    public function dataProviderTestGetPaymentUrlReturnsExpectedValues()
    {
        return [
            [true, 'code1', Api::URL_REDIRECTION_TEST . 'code1'],
            [false, 'code2', Api::URL_REDIRECTION_PRODUCTION . 'code2'],
        ];
    }

    /**
     * @param $isTest
     * @param $code
     * @param $expectedUrl
     * @dataProvider dataProviderTestGetPaymentUrlReturnsExpectedValues
     */
    public function testGetPaymentUrlReturnsExpectedValues($isTest, $code, $expectedUrl)
    {
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isTest')
            ->will($this->returnValue($isTest));

        $this->assertEquals($expectedUrl, $this->subject->getPaymentUrl($code));
    }

    public function testGetPaymentUsesBillingAddressWhenOrderIsVirtual()
    {
        $address = $this
            ->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();;

        $order = $this
            ->getMockBuilder(Order::class)
            ->setConstructorArgs($this->objectManager->getConstructArguments(Order::class))
            ->setMethods(['getIsVirtual', 'getBillingAddress', 'getShippingAddress', 'getAllVisibleItems'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getIsVirtual')
            ->will($this->returnValue(true));

        $order
            ->expects($this->once())
            ->method('getBillingAddress')
            ->will($this->returnValue($address));

        $order
            ->expects($this->never())
            ->method('getShippingAddress');

        $order
            ->expects($this->once())
            ->method('getAllVisibleItems')
            ->will($this->returnValue([]));

        $this->subject
            ->expects($this->once())
            ->method('_makeRequest')
            ->will($this->returnValue('<laga><foo><bar>1</bar></foo></laga>'));

        $this->subject->getPaymentCode($order);
    }

    public function testGetPaymentUsesShippingAddressWhenOrderIsNotVirtual()
    {
        $address = $this
            ->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();;

        $order = $this
            ->getMockBuilder(Order::class)
            ->setConstructorArgs($this->objectManager->getConstructArguments(Order::class))
            ->setMethods(['getIsVirtual', 'getBillingAddress', 'getShippingAddress', 'getAllVisibleItems'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getIsVirtual')
            ->will($this->returnValue(false));

        $order
            ->expects($this->never())
            ->method('getBillingAddress');

        $order
            ->expects($this->once())
            ->method('getShippingAddress')
            ->will($this->returnValue($address));

        $order
            ->expects($this->once())
            ->method('getAllVisibleItems')
            ->will($this->returnValue([]));

        $this->subject
            ->expects($this->once())
            ->method('_makeRequest')
            ->will($this->returnValue('<laga><foo><bar>1</bar></foo></laga>'));

        $this->subject->getPaymentCode($order);
    }

    public function dataProviderTestGetPaymentReturnsFalseWhenWeDontHaveAValidResponseFromPagseguro()
    {
        return [
            [null],
            [''],
            ['<xml_with_no_code></xml_with_no_code>'],
            ['<xml_witheno_code></xml_witheno_code_invalid>'],
        ];
    }

    /**
     * @param $pagseguroResponse
     * @dataProvider dataProviderTestGetPaymentReturnsFalseWhenWeDontHaveAValidResponseFromPagseguro
     */
    public function testGetPaymentReturnsFalseWhenWeDontHaveAValidResponseFromPagseguro($pagseguroResponse)
    {
        $address = $this
            ->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();;

        $order = $this
            ->getMockBuilder(Order::class)
            ->setConstructorArgs($this->objectManager->getConstructArguments(Order::class))
            ->setMethods(['getIsVirtual', 'getBillingAddress', 'getShippingAddress', 'getAllVisibleItems'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getIsVirtual')
            ->will($this->returnValue(false));

        $order
            ->expects($this->never())
            ->method('getBillingAddress');

        $order
            ->expects($this->once())
            ->method('getShippingAddress')
            ->will($this->returnValue($address));

        $order
            ->expects($this->once())
            ->method('getAllVisibleItems')
            ->will($this->returnValue([]));

        $this->subject
            ->expects($this->once())
            ->method('_makeRequest')
            ->will($this->returnValue($pagseguroResponse));

        $this->assertFalse($this->subject->getPaymentCode($order));
    }

    protected function _getExpectedParams()
    {
        return [
            'email' => self::TEST_MERCHANT_EMAIL,
            'token' => self::TEST_INTEGRATION_TOKEN,
            'currency' => 'BRL',
            'reference' => '0000012130',
            'senderName' => 'Dilma Rousseff',
            'senderAreaCode' => '61',
            'senderPhone' => '981178050',
            'senderEmail' => 'drousseff@planalto.gov.br',
            'shippingType' => 1,
            'shippingAddressStreet' => 'SQN 205 Bloco G Apto',
            'shippingAddressNumber' => '504',
            'shippingAddressComplement' => 'Atrás da banca',
            'shippingAddressDistrict' => 'Asa Norte',
            'shippingAddressPostalCode' => '70983-020',
            'shippingAddressCity' => 'Brasília',
            'shippingAddressState' => 'DF',
            'shippingAddressCountry' => 'BRA',
            'itemId1' => 'nike-backpack-03832',
            'itemDescription1' => 'Nike Backpack',
            'itemAmount1' => '30.40',
            'itemQuantity1' => 1,
            'itemWeight1' => 1,
            'itemId2' => 'nike-shirt-04434',
            'itemDescription2' => 'Nike Shirt',
            'itemAmount2' => '80.00',
            'itemQuantity2' => 2,
            'itemWeight2' => '1',
            'itemId3' => 'nike-glasses-059390',
            'itemDescription3' => 'Nike Glasses',
            'itemAmount3' => '3324.80',
            'itemQuantity3' => 10,
            'itemWeight3' => '1'
        ];
    }

    public function testGetPaymentMakesRequestCorrectly()
    {
        $address = $this
            ->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStreetLine', 'getPostcode', 'getCity', 'getRegionCode', 'getCountryId',
                'getTelephone'])
            ->getMock();
        $address
            ->expects($this->exactly(4))
            ->method('getStreetLine')
            ->withConsecutive(
                [1],
                [2],
                [3],
                [4]
            )
            ->willReturnOnConsecutiveCalls(
                'SQN 205 Bloco G Apto',
                '504',
                'Atrás da banca',
                'Asa Norte'
            );
        $address
            ->expects($this->exactly(1))
            ->method('getPostcode')
            ->will($this->returnValue('70983-020'));
        $address
            ->expects($this->exactly(1))
            ->method('getCity')
            ->will($this->returnValue('Brasília'));
        $address
            ->expects($this->exactly(1))
            ->method('getRegionCode')
            ->will($this->returnValue('DF'));
        $address
            ->expects($this->exactly(1))
            ->method('getCountryId')
            ->will($this->returnValue('BRA'));
        $address
            ->expects($this->exactly(1))
            ->method('getTelephone')
            ->will($this->returnValue('\'(61) 98117-8050\''));


        $itemMethods = ['getBasePrice', 'getQtyOrdered', 'getChildrenItems', 'getWeight', 'getSku', 'getName'];

        $itemA = $this
            ->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->setMethods($itemMethods)
            ->getMock();
        $itemA
            ->expects($this->exactly(2))
            ->method('getBasePrice')
            ->will($this->returnValue(30.40));
        $itemA
            ->expects($this->exactly(1))
            ->method('getQtyOrdered')
            ->will($this->returnValue(1));
        $itemA
            ->expects($this->exactly(1))
            ->method('getChildrenItems')
            ->will($this->returnValue([]));
        $itemA
            ->expects($this->exactly(1))
            ->method('getWeight')
            ->will($this->returnValue(0.3));
        $itemA
            ->expects($this->exactly(1))
            ->method('getSku')
            ->will($this->returnValue('nike-backpack-03832'));
        $itemA
            ->expects($this->exactly(1))
            ->method('getName')
            ->will($this->returnValue('Nike Backpack'));

        $itemBChild = $this
            ->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->setMethods($itemMethods)
            ->getMock();
        $itemBChild
            ->expects($this->exactly(2))
            ->method('getBasePrice')
            ->will($this->returnValue(null));
        $itemBChild
            ->expects($this->exactly(1))
            ->method('getQtyOrdered')
            ->will($this->returnValue(2));
        $itemBChild
            ->expects($this->never())
            ->method('getChildrenItems');
        $itemBChild
            ->expects($this->never())
            ->method('getWeight');
        $itemBChild
            ->expects($this->never())
            ->method('getSku');
        $itemBChild
            ->expects($this->never())
            ->method('getName');

        $itemB = $this
            ->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->setMethods($itemMethods)
            ->getMock();
        $itemB
            ->expects($this->exactly(2))
            ->method('getBasePrice')
            ->will($this->returnValue(40));
        $itemB
            ->expects($this->exactly(1))
            ->method('getQtyOrdered')
            ->will($this->returnValue(2));
        $itemB
            ->expects($this->exactly(1))
            ->method('getChildrenItems')
            ->will($this->returnValue([$itemBChild]));
        $itemB
            ->expects($this->exactly(1))
            ->method('getWeight')
            ->will($this->returnValue(0.5));
        $itemB
            ->expects($this->exactly(1))
            ->method('getSku')
            ->will($this->returnValue('nike-shirt-04434'));
        $itemB
            ->expects($this->exactly(1))
            ->method('getName')
            ->will($this->returnValue('Nike Shirt'));

        $itemC = $this
            ->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->setMethods($itemMethods)
            ->getMock();
        $itemC
            ->expects($this->exactly(2))
            ->method('getBasePrice')
            ->will($this->returnValue(332.48));
        $itemC
            ->expects($this->exactly(1))
            ->method('getQtyOrdered')
            ->will($this->returnValue(10));
        $itemC
            ->expects($this->exactly(1))
            ->method('getChildrenItems')
            ->will($this->returnValue([]));
        $itemC
            ->expects($this->exactly(1))
            ->method('getWeight')
            ->will($this->returnValue(1.2));
        $itemC
            ->expects($this->exactly(1))
            ->method('getSku')
            ->will($this->returnValue('nike-glasses-059390'));
        $itemC
            ->expects($this->exactly(1))
            ->method('getName')
            ->will($this->returnValue('Nike Glasses'));

        $items = [$itemA, $itemB, $itemBChild, $itemC];

        $order = $this
            ->getMockBuilder(Order::class)
            ->setConstructorArgs($this->objectManager->getConstructArguments(Order::class))
            ->setMethods(['getIsVirtual', 'getBillingAddress', 'getShippingAddress', 'getAllVisibleItems',
                'getIncrementId', 'getCustomerName', 'getCustomerEmail'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getIsVirtual')
            ->will($this->returnValue(false));

        $order
            ->expects($this->never())
            ->method('getBillingAddress');

        $order
            ->expects($this->once())
            ->method('getShippingAddress')
            ->will($this->returnValue($address));

        $order
            ->expects($this->once())
            ->method('getAllVisibleItems')
            ->will($this->returnValue($items));

        $order
            ->expects($this->once())
            ->method('getIncrementId')
            ->will($this->returnValue('0000012130'));

        $order
            ->expects($this->once())
            ->method('getCustomerName')
            ->will($this->returnValue('Dilma Rousseff'));

        $order
            ->expects($this->once())
            ->method('getCustomerEmail')
            ->will($this->returnValue('drousseff@planalto.gov.br'));

        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('getMerchantEmail')
            ->will($this->returnValue(self::TEST_MERCHANT_EMAIL));
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('getIntegrationToken')
            ->will($this->returnValue(self::TEST_INTEGRATION_TOKEN));

        $this->subject
            ->expects($this->once())
            ->method('_makeRequest')
            ->with($this->_getExpectedParams())
            ->will($this->returnValue('<response><code>MAKSNCKD-39CMSLANA9-ASDFMSKASDASDFASDLFKMSD</code></response>'));

        $this->assertEquals('MAKSNCKD-39CMSLANA9-ASDFMSKASDASDFASDLFKMSD', $this->subject->getPaymentCode($order));
    }

    public function dataProviderTestGetPagseguroApiBaseUrl()
    {
        return [
            [true, Api::URL_API_TEST],
            [false, Api::URL_API_PRODUCTION]
        ];
    }

    /**
     * @param $isTest
     * @param $expectedUrl
     * @dataProvider dataProviderTestGetPagseguroApiBaseUrl
     */
    public function testGetPagseguroApiBaseUrl($isTest, $expectedUrl)
    {
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isTest')
            ->will($this->returnValue($isTest));
        $this->assertEquals($expectedUrl, $this->getPagseguroApiBaseUrlMethod->invoke($this->subject));
    }

    public function dataProviderTestGetShippingType()
    {
        return [
            ['gabrielqscorreios_40010', Api::SHIPPING_TYPE_SEDEX],
            ['gabrielqscorreios_40096', Api::SHIPPING_TYPE_SEDEX],
            ['correios_40215', Api::SHIPPING_TYPE_SEDEX],
            ['pedroteixeira_correios40290', Api::SHIPPING_TYPE_SEDEX],
            ['40045', Api::SHIPPING_TYPE_SEDEX],
            ['gabrielqscorreios_41106', Api::SHIPPING_TYPE_PAC],
            ['gabrielqscorreios_41068', Api::SHIPPING_TYPE_PAC],
            ['naoexiste', Api::SHIPPING_TYPE_NOT_SPECIFIED],
            [null, Api::SHIPPING_TYPE_NOT_SPECIFIED]
        ];
    }

    /**
     * @param $method
     * @param $expectedReturn
     * @dataProvider dataProviderTestGetShippingType
     */
    public function testGetShippingType($method, $expectedReturn)
    {
        $order = $this
            ->getMockBuilder(Order::class)
            ->setConstructorArgs($this->objectManager->getConstructArguments(Order::class))
            ->setMethods(['getShippingMethod'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getShippingMethod')
            ->will($this->returnValue($method));
        $this->assertEquals($expectedReturn, $this->getShippingTypeMethod->invoke($this->subject, $order));
    }

    public function dataProviderTestSplitPhone()
    {
        return [
            ['61981178050', '61', '981178050'],
            ['6198117-8050', '61', '981178050'],
            ['61 98117-8050', '61', '981178050'],
            ['61 981178050', '61', '981178050'],
            ['(61) 98117-8050', '61', '981178050'],
            ['(61) 981178050', '61', '981178050'],
            ['(61)981178050', '61', '981178050'],
            ['(61)98117-8050', '61', '981178050'],
            ['061981178050', '61', '981178050'],
            ['06198117-8050', '61', '981178050'],
            ['061 98117-8050', '61', '981178050'],
            ['061 981178050', '61', '981178050'],
            ['(061) 98117-8050', '61', '981178050'],
            ['(061) 981178050', '61', '981178050'],
            ['(061)981178050', '61', '981178050'],
            ['(061)98117-8050', '61', '981178050'],
            ['06181178050', '61', '81178050'],
            ['0618117-8050', '61', '81178050'],
            ['061 8117-8050', '61', '81178050'],
            ['061 81178050', '61', '81178050'],
            ['(061) 8117-8050', '61', '81178050'],
            ['(061) 81178050', '61', '81178050'],
            ['(061)81178050', '61', '81178050'],
            ['(061)8117-8050', '61', '81178050'],
            ['non numeric', null, null],
            ['1234', null, null],
            ['12341234123412341234', null, null],
            ['0123456789123', null, null],
        ];
    }

    /**
     * @param $preformattedphone
     * @param $areaCode
     * @param $phone
     * @dataProvider dataProviderTestSplitPhone
     */
    public function testSplitPhone($preformattedphone, $areaCode, $phone)
    {
        $expectedReturn = new \stdClass();
        $expectedReturn->areaCode = $areaCode;
        $expectedReturn->telephone = $phone;

        $this->assertEquals($expectedReturn, $this->splitPhoneMethod->invoke($this->subject, $preformattedphone));
    }

}
