<?php

namespace Gabrielqs\Pagseguro\Model;

use \Magento\Payment\Model\Method\Logger;
use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\OrderFactory;
use \Magento\Framework\DataObjectFactory;
use \Magento\Framework\Model\Context;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;
use \Magento\Framework\HTTP\ZendClientFactory as HttpClientFactory;
use \Magento\Framework\HTTP\ZendClient as HttpClient;

class Notifications
{
    /**
     * Pagseguro Cancellation Source - Internal
     */
    const PAGSEGURO_CANCELATION_SOURCE_INTERNAL = 'INTERNAL';

    /**
     * Pagseguro Cancellation Source - External
     */
    const PAGSEGURO_CANCELATION_SOURCE_EXTERNAL = 'EXTERNAL';

    /**
     * Pagseguro Status - Pending Payment
     */
    const PAGSEGURO_STATUS_PENDING_PAYMENT = 1;

    /**
     * Pagseguro Status - Under Analysis
     */
    const PAGSEGURO_STATUS_UNDER_ANALYSIS = 2;

    /**
     * Pagseguro Status - Paid
     */
    const PAGSEGURO_STATUS_PAID = 3;

    /**
     * Pagseguro Status - Available for withdrawal
     */
    const PAGSEGURO_STATUS_AVAILABLE_FOR_WITHDRAWAL = 4;

    /**
     * Pagseguro Status - Under Dispute
     */
    const PAGSEGURO_STATUS_UNDER_DISPUTE = 5;

    /**
     * Pagseguro Status - Refunded
     */
    const PAGSEGURO_STATUS_REFUNDED = 6;

    /**
     * Pagseguro Status - Canceled
     */
    const PAGSEGURO_STATUS_CANCELED = 7;

    /**
     * Production Notification URL
     */
    const URL_NOTIFICATIONS_PRODUCTION = 'https://ws.pagseguro.uol.com.br/v2/transactions/notifications/';

    /**
     * Sandbox Notification URL
     */
    const URL_NOTIFICATIONS_SANDBOX = 'https://ws.sandbox.pagseguro.uol.com.br/v2/transactions/notifications/';

    /**
     * Data Object Factory
     * @var DataObjectFactory $_dataObjectFactory
     */
    protected $_dataObjectFactory = null;

    /**
     * Logger
     * @var Logger $_logger
     */
    protected $_logger = null;

    /**
     * OrderFactory
     * @var OrderFactory $_orderFactory
     */
    protected $_orderFactory= null;

    /**
     * Context
     * @var Context $_eventManager
     */
    protected $_context = null;

    /**
     * Application Event Dispatcher
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager = null;

    /**
     * HttpClientFactory
     * @var HttpClientFactory
     */
    protected $_httpClientFactory = null;

    /**
     * Redirect Helper
     * @var RedirectHelper
     */
    protected $_redirectHelper = null;

    /**
     * Notifications constructor.
     * @param Logger $logger
     * @param OrderFactory $orderFactory
     * @param Context $context
     * @param RedirectHelper $redirectHelper
     * @param DataObjectFactory $dataObjectFactory
     * @param HttpClientFactory $httpClientFactory
     */
    public function __construct(
        Logger $logger,
        OrderFactory $orderFactory,
        Context $context,
        RedirectHelper $redirectHelper,
        DataObjectFactory $dataObjectFactory,
        HttpClientFactory $httpClientFactory
    ) {
        $this->_logger = $logger;
        $this->_orderFactory = $orderFactory;
        $this->_redirectHelper = $redirectHelper;
        $this->_eventManager = $context->getEventDispatcher();
        $this->_dataObjectFactory = $dataObjectFactory;
        $this->_httpClientFactory = $httpClientFactory;
    }

    /**
     * Gets a new Zend HTTP Client
     * @return HttpClient
     */
    protected function _getHttpClient()
    {
        return $this->_httpClientFactory->create();
    }

    /**
     * Makes a request to Pagseguro API, which is sent by Pagseguro when some change happened on a transaction,
     * checking what has changed
     * @param string $notificationCode
     * @return \SimpleXMLElement
     */
    public function getNotificationStatus($notificationCode)
    {
        $url =  $this->_getNotificationUrl($notificationCode);
        $client = $this->_getHttpClient();
        $client->setUri($url);
        $client->setParameterGet('token', $this->_redirectHelper->getIntegrationToken());
        $client->setParameterGet('email', $this->_redirectHelper->getMerchantEmail());

        $client->request();
        $response = $client->getLastResponse()->getBody();

        $this->_logger->debug([
            'pagseguro_notification_code' => $notificationCode,
            'pagseguro_notification_return' => $response
        ]);

        return simplexml_load_string(trim($response));
    }

    /**
     * Returns loaded order for a given increment id
     * @param $incrementId
     * @return Order
     */
    protected function _loadOrder($incrementId)
    {
        return $this->_orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * Returns URL to retrieve notifications from pagseguro, changes depending on test mode
     * @param string $notificationCode
     * @return string
     */
    protected function _getNotificationUrl($notificationCode)
    {
        if ($this->_redirectHelper->isTest()) {
            $url = self::URL_NOTIFICATIONS_SANDBOX;
        } else {
            $url = self::URL_NOTIFICATIONS_PRODUCTION;
        }

        return $url . $notificationCode;
    }

    /**
     * Processes Pagseguro notification return. The XML is received when the order is created, and later on
     * when requesting for status update
     * @param \SimpleXMLElement $pagseguroXmlReturn
     * @param Order $order
     * @throws \Exception
     * @return void
     * @see https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-notificacoes.html#v2-item-servico-de-notificacoes
     */
    public function proccessNotificatonResult(\SimpleXMLElement $pagseguroXmlReturn, Order $order = null)
    {
        if (isset($pagseguroXmlReturn->error)) {
            $errMsg = __((string) $pagseguroXmlReturn->error->message);
            throw new \Exception(__('Problems found while processing a payment notification. %1(%2)',
                $errMsg, (string) $pagseguroXmlReturn->error->code));
        }

        if (isset($pagseguroXmlReturn->reference)) {
            /** @var Order $order */
            if ($order === null) {
                $order = $this->_loadOrder((string) $pagseguroXmlReturn->reference);
            }

            $status = (int) $pagseguroXmlReturn->status;

            $payment = $order->getPayment();
            $processedState = $this->processStatus($status);
            $message = $processedState->getMessage();

            if ($status == self::PAGSEGURO_STATUS_REFUNDED) {
                if ($order->canUnhold()) {
                    $order->unhold();
                }
                if ($order->canCancel()) {
                    $order->cancel();
                    $order->save();
                } else {
                    $payment->registerRefundNotification(floatval($pagseguroXmlReturn->grossAmount));
                    $order->addStatusHistoryComment(__('Refunded: the paid amount has been returned to the customer,' .
                        ' but the order was in a state which didn\'t allow cancelling'))
                        ->save();
                }
            }

            // Specifying the reason for the order to be canceled
            if ($status == self::PAGSEGURO_STATUS_CANCELED && isset($pagseguroXmlReturn->cancellationSource)) {
                $cancellationSource = (string) $pagseguroXmlReturn->cancellationSource;
                switch($cancellationSource)
                {
                    case self::PAGSEGURO_CANCELATION_SOURCE_INTERNAL:
                        $message .= ' ' . __('PagSeguro has canceled the transaction');
                        break;
                    case self::PAGSEGURO_CANCELATION_SOURCE_EXTERNAL:
                        $message .= ' ' . __('The transaction has been denied or canceled by the bank');
                        break;
                }
                $order->cancel();
            }

            if ($processedState->getStateChanged()) {
                $order->setState($processedState->getState())->save();
            }

            // Order has been paid
            if ($status == self::PAGSEGURO_STATUS_PAID) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->pay();
                $msg = __('Payment captured. TransactionID: %1', (string) $pagseguroXmlReturn->code);
                $invoice->addComment($msg);
                $invoice->sendEmail($this->_redirectHelper->isSendInvoiceEmail(),
                    'Payment successfully received.');
                $order->addStatusHistoryComment(sprintf('Invoice %1 successfully created.',
                    $invoice->getIncrementId()));
            }

            $order->addStatusHistoryComment($message);
            $payment->save();
            $order->save();
            $this->_eventManager->dispatch('pagseguro_proccess_notification_after', [
                'order' => $order,
                'payment'=> $payment,
                'result_xml' => $pagseguroXmlReturn,
            ]);
        } else {
            throw new \Exception(__('Invalid Return. Order reference not found.'));
        }
    }

    /**
     * Processes the notification status, deciding on the order new states, status and customer notification
     * @param string $statusCode
     * @return DataObject
     */
    public function processStatus($statusCode)
    {
        $return = $this->_dataObjectFactory->create();
        $return->setStateChanged(true);
        $return->setIsTransactionPending(true);
        switch((int) $statusCode)
        {
            case self::PAGSEGURO_STATUS_PENDING_PAYMENT:
                $return->setState(Order::STATE_PENDING_PAYMENT);
                $return->setIsCustomerNotified(true);
                $return->setMessage(__('Pending Payment: the customer has initiated the transaction, but so far' .
                    ' PagSeguro hasn\'t received no information about it.'));
                break;
            case self::PAGSEGURO_STATUS_UNDER_ANALYSIS:
                $return->setState(Order::STATE_PAYMENT_REVIEW);
                $return->setIsCustomerNotified(true);
                $return->setMessage(__('Analysis: the customer opted by paying with a credit card, and PagSeguro' .
                    ' is checking against frauds.'));
                break;
            case self::PAGSEGURO_STATUS_PAID:
                $return->setState(Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(true);
                $return->setMessage(__('Paid: the transaction has been paid by the customer and PagSeguro has received'.
                    ' a confirmation from the financial institution responsible for the transaction.'));
                $return->setIsTransactionPending(false);
                break;
            case self::PAGSEGURO_STATUS_AVAILABLE_FOR_WITHDRAWAL:
                $return->setMessage(
                    __('Available: the transaction has been paid and is now available for withdrawal.')
                );
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setIsTransactionPending(false);
                break;
            case self::PAGSEGURO_STATUS_UNDER_DISPUTE:
                $return->setState(Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage(__('Under dispute: the customer has opened a dispute.'));
                break;
            case self::PAGSEGURO_STATUS_REFUNDED:
                $return->setState(Order::STATE_CLOSED);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage(__('Refunded: the paid value has been refunded to the customer'));
                break;
            case self::PAGSEGURO_STATUS_CANCELED:
                $return->setState(Order::STATE_CANCELED);
                $return->setIsCustomerNotified(true);
                $return->setMessage(__('Cancelled: the transaction has been cancelled and has been not paid.'));
                break;
            default:
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setMessage(__('Invalid status code returned by PagSeguro (%1)', $statusCode));
        }
        return $return;
    }
}
