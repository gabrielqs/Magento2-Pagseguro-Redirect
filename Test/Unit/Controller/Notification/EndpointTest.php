<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Controller\Notification;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Framework\View\Result\Page;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Exception\LocalizedException;
use \Gabrielqs\Pagseguro\Controller\Notification\Endpoint as Subject;
use \Gabrielqs\Pagseguro\Model\Notifications;

/**
 * Unit Testcase
 */
class EndpointTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var String
     */
    protected $className = null;

    /**
     * @var Context
     */
    protected $context = null;

    /**
     * @var Notifications
     */
    protected $notifications = null;

    /**
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * @var Subject
     */
    protected $originalSubject = null;

    /**
     * @var PageFactory
     */
    protected $pageFactory = null;

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
            ->setMethods(['_getNotificationCode'])
            ->getMock();

        $this->originalSubject = $this
            ->objectManager
            ->getObject($this->className, $arguments);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->notifications = $this
            ->getMockBuilder(Notifications::class)
            ->disableOriginalConstructor()
            ->setMethods(['getNotificationStatus', 'proccessNotificatonResult'])
            ->getMock();
        $arguments['notifications'] = $this->notifications;

        $this->pageFactory = $this
            ->getMockBuilder(PageFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $arguments['resultPageFactory'] = $this->pageFactory;

        return $arguments;
    }

    public function testExecuteReturnsPageAndProcessesTheRequestCorrectly()
    {
        $notificationCode = 'NAJE02B38X-392BSLAS9M2-MASKDF920AM';

        $this
            ->subject
            ->expects($this->once())
            ->method('_getNotificationCode')
            ->will($this->returnValue($notificationCode));

        $simpleXmlReturn = simplexml_load_string('<return><foo><bar>laga</bar><baz>tchururu</baz></foo></return>');

        $this
            ->notifications
            ->expects($this->once())
            ->method('getNotificationStatus')
            ->with($this->equalTo($notificationCode))
            ->will($this->returnValue($simpleXmlReturn));

        $this
            ->notifications
            ->expects($this->once())
            ->method('proccessNotificatonResult')
            ->with($this->equalTo($simpleXmlReturn));

        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
        $this
            ->pageFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($page));

        $return = $this->subject->execute();
        $this->assertInstanceOf(Page::class, $return);
    }

    public function testExecuteThrowsExceptionWhenNoNotificationFound()
    {
        $notificationCode = null;

        $this
            ->subject
            ->expects($this->once())
            ->method('_getNotificationCode')
            ->will($this->returnValue($notificationCode));

        $this->setExpectedException(LocalizedException::class);
        $this->subject->execute();
    }

}