<?php

namespace Gabrielqs\Pagseguro\Test\Unit\Helper\Redirect;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Gabrielqs\Pagseguro\Helper\Redirect\Data as RedirectHelper;
use \Magento\Framework\Exception\LocalizedException;

/**
 * DataTest, Redirect Helper Testcase
 */
class DataTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var RedirectHelper
     */
    protected $helper = null;

    protected function setUp()
    {
        $objectManagerHelper = new ObjectManager($this);
        $className = 'Gabrielqs\Pagseguro\Helper\Redirect\Data';
        $arguments = $objectManagerHelper->getConstructArguments($className);
        $this->helper = $this->getMock($className, ['getConfigData'], $arguments);
    }

    public function testIsTestRetrievesCorrectKey()
    {
        $this
            ->helper
            ->expects($this->once())
            ->method('getConfigData')
            ->with('test_mode_enabled')
            ->willReturn($this->returnValue(true));

        $this
            ->helper
            ->isTest();
    }

    public function testReturnsCorrectMethodCode()
    {
        $this->assertEquals('pagseguro_redirect', $this->helper->getMethodCode());
    }

    public function testGetMerchantEmailTestMode()
    {
        /*
         * Test mode enabled, should retrieve test_merchant_email
         */
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('test_merchant_email'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                'test_merchant_email'
            );

        $this
            ->helper
            ->getMerchantEmail();
    }

    public function testGetMerchantEmailThrowsExceptionWhenItGetsAnEmptyValue()
    {
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('merchant_email'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                ''
            );

        $this->setExpectedException(LocalizedException::class);

        $this
            ->helper
            ->getMerchantEmail();
    }

    public function testGetMerchantEmailNonTestMode()
    {
        /*
         * Test mode disabled, should retrieve merchant_email path from config
         */
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('merchant_email'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                'merchant_email'
            );

        $this
            ->helper
            ->getMerchantEmail();
    }

    public function testGetIntegrationTokenTestMode()
    {
        /*
         * Test mode enabled, should retrieve test_merchant_email
         */
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('test_integration_token'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                'test_key'
            );

        $this
            ->helper
            ->getIntegrationToken();
    }

    public function testGetIntegrationTokenNonTestMode()
    {
        /*
         * Test mode disabled, should retrieve merchant_email path from config
         */
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('integration_token'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                'production_key'
            );

        $this
            ->helper
            ->getIntegrationToken();
    }

    public function testGetIntegrationTokenThrowsExceptionWhenItGetsAnEmptyValue()
    {
        $this->helper
            ->expects($this->exactly(2))
            ->method('getConfigData')
            ->withConsecutive(
                [$this->equalTo('test_mode_enabled'), $this->equalTo(null)],
                [$this->equalTo('integration_token'), $this->equalTo(null)]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                ''
            );

        $this->setExpectedException(LocalizedException::class);

        $this
            ->helper
            ->getIntegrationToken();
    }
}

