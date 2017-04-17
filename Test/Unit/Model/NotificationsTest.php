<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Model;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\Order\Payment;
use \Magento\Sales\Model\Order\Invoice;
use \Magento\Framework\HTTP\ZendClient as HttpClient;
use \Magento\Framework\DataObject;
use \Magento\Framework\DataObjectFactory;
use \Gabrielqs\Pagseguro\Model\Notifications as Subject;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;

/**
 * Unit Testcase
 */
class NotificationsTest extends \PHPUnit_Framework_TestCase
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
     * @var String
     */
    protected $className = null;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory = null;

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
    protected $subject = null;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->className = Subject::class;
        $arguments = $this->getConstructorArguments();

        $this->subject = $this
            ->getMockBuilder($this->className)
            ->setConstructorArgs($arguments)
            ->setMethods(['_getHttpClient', '_loadOrder'])
            ->getMock();

        $this->originalSubject = $this
            ->objectManager
            ->getObject($this->className, $arguments);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->redirectHelper = $this
            ->getMockBuilder(RedirectHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['getIntegrationToken', 'getMerchantEmail', 'isTest', 'isSendInvoiceEmail'])
            ->getMock();
        $arguments['redirectHelper'] = $this->redirectHelper;

        $this->dataObjectFactory = $this
            ->getMockBuilder(DataObjectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $arguments['dataObjectFactory'] = $this->dataObjectFactory;

        return $arguments;
    }

    public function dataProviderTestGetNotificationStatus()
    {
        return [
            [true, Subject::URL_NOTIFICATIONS_SANDBOX],
            [false, Subject::URL_NOTIFICATIONS_PRODUCTION]
        ];
    }

    /**
     * @param $isTest
     * @param $url
     * @dataProvider dataProviderTestGetNotificationStatus
     */
    public function testGetNotificationStatus($isTest, $url)
    {
        $notificationCode = 'AMD09MD9ADMCB39-MDKASDMCOSDMCDSL0-03982NMC9DD8C0AMC';

        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isTest')
            ->will($this->returnValue($isTest));
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

        $lastResponse = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBody'])
            ->getMock();
        $lastResponse
            ->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue('<response><foo><bar>1</bar><baz>2</baz></foo></response>'));

        $httpClient = $this
            ->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['request', 'getLastResponse', 'setUri', 'setParameterGet'])
            ->getMock();
        $httpClient
            ->expects($this->once())
            ->method('setUri')
            ->with($url . $notificationCode);
        $httpClient
            ->expects($this->once())
            ->method('getLastResponse')
            ->will($this->returnValue($lastResponse));
        $httpClient
            ->expects($this->exactly(2))
            ->method('setParameterGet')
            ->withConsecutive(
                ['token', self::TEST_INTEGRATION_TOKEN],
                ['email', self::TEST_MERCHANT_EMAIL]
            )
            ->willReturnOnConsecutiveCalls(
                $httpClient,
                $httpClient
            );

        $this
            ->subject
            ->expects($this->once())
            ->method('_getHttpClient')
            ->willReturn($httpClient);

        $this->subject->getNotificationStatus($notificationCode);
    }

    public function testProccessNotificatonResultThrowsExceptionOnError()
    {
        $xml = new \SimpleXMLElement('<return>
            <error>
                <message>Error Message</message>
                <code>404</code>
            </error>
        </return>');
        $this->setExpectedException(\Exception::class);
        $this->subject->proccessNotificatonResult($xml);
    }

    public function testProccessNotificatonResultThrowsExceptionOnNoReference()
    {
        $xml = new \SimpleXMLElement('<return>
            <notareference>laga</notareference>
        </return>');
        $this->setExpectedException(\Exception::class);
        $this->subject->proccessNotificatonResult($xml);
    }

    public function dataProviderTestProccessNotificatonResultRefunded()
    {
        return [
            [true, $this->once(), true, $this->once(), $this->once()],
            [false, $this->never(), false, $this->never(), $this->exactly(2)],
        ];
    }

    /**
     * @param $canUnhold
     * @param $unholdExpects
     * @param $canCancel
     * @param $cancelExpects
     * @param $addStatusHistoryExpects
     * @dataProvider dataProviderTestProccessNotificatonResultRefunded
     */
    public function testProccessNotificatonResultRefunded(
        $canUnhold,
        $unholdExpects,
        $canCancel,
        $cancelExpects,
        $addStatusHistoryExpects
    ) {

        $payment = $this
            ->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->setMethods(['save', 'registerRefundNotification'])
            ->getMock();

        if (!$canCancel) {
            $payment
                ->expects($this->once())
                ->method('registerRefundNotification')
                ->with(346.20);
        }

        $order = $this
            ->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment', 'addStatusHistoryComment', 'save', 'canCancel', 'cancel', 'canUnhold',
                'unhold'])
            ->getMock();
        $order
            ->expects($this->once())
            ->method('getPayment')
            ->will($this->returnValue($payment));
        $order
            ->expects($this->once())
            ->method('canUnhold')
            ->will($this->returnValue($canUnhold));
        $order
            ->expects($unholdExpects)
            ->method('unhold');
        $order
            ->expects($this->once())
            ->method('canCancel')
            ->will($this->returnValue($canCancel));
        $order
            ->expects($cancelExpects)
            ->method('unhold');
        if (!$canCancel) {
            $order
                ->expects($addStatusHistoryExpects)
                ->method('addStatusHistoryComment')
                ->will($this->returnValue($order));
        }

        $this
            ->subject
            ->expects($this->once())
            ->method('_loadOrder')
            ->will($this->returnValue($order));

        $dataObject = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this
            ->dataObjectFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($dataObject));

        $xml = new \SimpleXMLElement('<return>
            <reference>0000012102</reference>
            <status>' . Subject::PAGSEGURO_STATUS_REFUNDED . '</status>
            <grossAmount>346.20</grossAmount>
        </return>');
        $this->subject->proccessNotificatonResult($xml);
    }

    public function dataProviderTestProccessNotificatonResultPaid()
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @param $sendInvoiceEmail
     * @dataProvider dataProviderTestProccessNotificatonResultPaid
     */
    public function testProccessNotificatonResultPaid($sendInvoiceEmail)
    {
        $this
            ->redirectHelper
            ->expects($this->once())
            ->method('isSendInvoiceEmail')
            ->will($this->returnValue($sendInvoiceEmail));

        $payment = $this
            ->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->setMethods(['save', 'registerRefundNotification'])
            ->getMock();

        $invoice = $this
            ->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->setMethods(['register', 'pay', 'addComment', 'sendEmail', 'getIncrementId', 'getOrder'])
            ->getMock();
        $invoice
            ->expects($this->once())
            ->method('register')
            ->will($this->returnValue($invoice));
        $invoice
            ->expects($this->once())
            ->method('pay')
            ->will($this->returnValue($invoice));
        $invoice
            ->expects($this->once())
            ->method('addComment');
        $invoice
            ->expects($this->once())
            ->method('sendEmail');
        $invoice
            ->expects($this->once())
            ->method('sendEmail')
            ->with($sendInvoiceEmail, 'Payment successfully received.');

        $order = $this
            ->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment', 'addStatusHistoryComment', 'save', 'prepareInvoice'])
            ->getMock();
        $order
            ->expects($this->once())
            ->method('getPayment')
            ->will($this->returnValue($payment));
        $order
            ->expects($this->once())
            ->method('prepareInvoice')
            ->will($this->returnValue($invoice));
        $order
            ->expects($this->exactly(2))
            ->method('addStatusHistoryComment')
            ->will($this->returnValue($payment));
        $order
            ->expects($this->once())
            ->method('save')
            ->will($this->returnValue($payment));

        $this
            ->subject
            ->expects($this->once())
            ->method('_loadOrder')
            ->will($this->returnValue($order));

        $dataObject = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this
            ->dataObjectFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($dataObject));

        $xml = new \SimpleXMLElement('<return>
            <reference>0000012102</reference>
            <status>' . Subject::PAGSEGURO_STATUS_PAID . '</status>
            <grossAmount>346.20</grossAmount>
        </return>');
        $this->subject->proccessNotificatonResult($xml);
    }

    public function dataProviderTestProccessNotificatonResultCanceled()
    {
        return [
            [Subject::PAGSEGURO_CANCELATION_SOURCE_INTERNAL, true, $this->exactly(2)],
            [Subject::PAGSEGURO_CANCELATION_SOURCE_EXTERNAL, false, $this->once()],
        ];
    }

    /**
     * @param $cancellationSource
     * @param $stateChanged
     * @param $saveExpects
     * @dataProvider dataProviderTestProccessNotificatonResultCanceled
     */
    public function testProccessNotificatonResultCanceled($cancellationSource, $stateChanged, $saveExpects)
    {
        $payment = $this
            ->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->setMethods(['save', 'registerRefundNotification'])
            ->getMock();

        $order = $this
            ->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment', 'addStatusHistoryComment', 'save', 'cancel', 'setState'])
            ->getMock();
        $order
            ->expects($this->once())
            ->method('getPayment')
            ->will($this->returnValue($payment));
        $order
            ->expects($this->once())
            ->method('cancel')
            ->will($this->returnValue($order));

        if ($stateChanged) {
            $order
                ->expects($this->once())
                ->method('setState')
                ->with(Order::STATE_CANCELED)
                ->will($this->returnValue($order));
        }

        $order
            ->expects($saveExpects)
            ->method('save');

        $this
            ->subject
            ->expects($this->once())
            ->method('_loadOrder')
            ->will($this->returnValue($order));

        $dataObject = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStateChanged'])
            ->getMock();

        $dataObject
            ->expects($this->once())
            ->method('getStateChanged')
            ->will($this->returnValue($stateChanged));

        $this
            ->dataObjectFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($dataObject));

        $xml = new \SimpleXMLElement('<return>
            <reference>0000012102</reference>
            <status>' . Subject::PAGSEGURO_STATUS_CANCELED . '</status>
            <cancellationSource>' . $cancellationSource . '</cancellationSource>
            <grossAmount>346.20</grossAmount>
        </return>');
        $this->subject->proccessNotificatonResult($xml);
    }

    public function dataProviderTestProcessStatus()
    {
        return [
            [Subject::PAGSEGURO_STATUS_PENDING_PAYMENT, Order::STATE_PENDING_PAYMENT, $this->once()],
            [Subject::PAGSEGURO_STATUS_UNDER_ANALYSIS, Order::STATE_PAYMENT_REVIEW, $this->once()],
            [Subject::PAGSEGURO_STATUS_PAID, Order::STATE_PROCESSING, $this->once()],
            [Subject::PAGSEGURO_STATUS_AVAILABLE_FOR_WITHDRAWAL, null, $this->never()],
            [Subject::PAGSEGURO_STATUS_UNDER_DISPUTE, Order::STATE_PROCESSING, $this->once()],
            [Subject::PAGSEGURO_STATUS_REFUNDED, Order::STATE_CLOSED, $this->once()],
            [Subject::PAGSEGURO_STATUS_CANCELED, Order::STATE_CANCELED, $this->once()],
            ['doesnt exist', null, $this->never()],
        ];
    }

    /**
     * @param $statusCode
     * @param $orderState
     * @dataProvider dataProviderTestProcessStatus
     */
    public function testProcessStatus($statusCode, $orderState, $expectedSetState)
    {
        $dataObject = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['setState'])
            ->getMock();

        $dataObject
            ->expects($expectedSetState)
            ->method('setState')
            ->with($orderState);

        $this
            ->dataObjectFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($dataObject));

        $this->subject->processStatus($statusCode);
    }
}