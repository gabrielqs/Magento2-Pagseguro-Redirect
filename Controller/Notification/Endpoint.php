<?php
namespace Gabrielqs\Pagseguro\Controller\Notification;

use Magento\Framework\Exception\LocalizedException;
use \Magento\Payment\Model\Method\Logger;
use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Framework\View\Result\Page;
use \Gabrielqs\Pagseguro\Model\Notifications;

class Endpoint extends Action
{
    /**
     * Result Page Factory
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * Notifications
     * @var Notifications
     */
    protected $_notifications;

    /**
     * Logger
     * @var Logger $_logger
     */
    protected $_logger = null;

    /**
     * Endpoint constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Notifications $notifications
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Notifications $notifications,
        Logger $logger
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_notifications = $notifications;
        $this->_logger = $logger;
        return parent::__construct($context);
    }

    /**
     * Gets notification code from request
     * @return string|null
     */
    protected function _getNotificationCode()
    {
        return $this->getRequest()->getPost('notificationCode');
    }

    /**
     * Treats Pagseguro notification return
     * @return Page
     * @throws LocalizedException
     */
    public function execute()
    {
        $notificationCode = $this->_getNotificationCode();
        if (!$notificationCode) {
            throw new LocalizedException(__('No notification code found in request.'));
        }
        $response = $this->_notifications->getNotificationStatus($notificationCode);
        $this->_notifications->proccessNotificatonResult($response);

        return $this->_resultPageFactory->create();
    }
}
