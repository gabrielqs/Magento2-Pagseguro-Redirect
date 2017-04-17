<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Controller\Redirect;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Framework\Controller\Result\Redirect;
use \Magento\Framework\DataObject;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Exception\LocalizedException;
use \Gabrielqs\Pagseguro\Controller\Redirect\Index as Subject;

/**
 * Unit Testcase
 */
class IndexTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession = null;

    /**
     * @var String
     */
    protected $className = null;

    /**
     * @var Context
     */
    protected $context = null;

    /**
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * @var Subject
     */
    protected $originalSubject = null;

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
            ->setMethods(['_getResultRedirect'])
            ->getMock();

        $this->originalSubject = $this
            ->objectManager
            ->getObject($this->className, $arguments);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->checkoutSession = $this
            ->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->setMethods(['getLastRealOrder'])
            ->getMock();
        $arguments['checkoutSession'] = $this->checkoutSession;

        return $arguments;
    }

    public function testExecuteReturnsRedirect()
    {
        $resultRedirect = $this
            ->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->setMethods(['setUrl'])
            ->getMock();

        $resultRedirect
            ->expects($this->once())
            ->method('setUrl')
            ->with('http://pagseguro.com.br/')
            ->will($this->returnValue($resultRedirect));

        $payment = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAdditionalInformation'])
            ->getMock();

        $payment
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('pagseguro_payment_url')
            ->will($this->returnValue('http://pagseguro.com.br/'));

        $order = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getPayment')
            ->will($this->returnValue($payment));

        $this
            ->checkoutSession
            ->expects($this->once())
            ->method('getLastRealOrder')
            ->will($this->returnValue($order));

        $this
            ->subject
            ->expects($this->once())
            ->method('_getResultRedirect')
            ->will($this->returnValue($resultRedirect));

        $return = $this->subject->execute();
        $this->assertInstanceOf(Redirect::class, $return);
    }

    public function testExecuteThrowsExceptionWhenNoUrlIsSet()
    {
        $payment = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAdditionalInformation'])
            ->getMock();

        $payment
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('pagseguro_payment_url')
            ->will($this->returnValue(null));

        $order = $this
            ->getMockBuilder(DataObject::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPayment'])
            ->getMock();

        $order
            ->expects($this->once())
            ->method('getPayment')
            ->will($this->returnValue($payment));

        $this
            ->checkoutSession
            ->expects($this->once())
            ->method('getLastRealOrder')
            ->will($this->returnValue($order));

        $this->setExpectedException(LocalizedException::class);

        $this->subject->execute();
    }
}