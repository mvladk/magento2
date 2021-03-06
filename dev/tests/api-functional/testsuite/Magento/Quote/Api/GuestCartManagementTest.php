<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Quote\Api;

use Magento\TestFramework\TestCase\WebapiAbstract;

class GuestCartManagementTest extends WebapiAbstract
{
    const SERVICE_VERSION = 'V1';
    const SERVICE_NAME = 'quoteGuestCartManagementV1';
    const RESOURCE_PATH = '/V1/guest-carts/';

    protected $createdQuotes = [];

    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    protected $objectManager;

    protected function setUp()
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
    }

    public function testCreate()
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'CreateEmptyCart',
            ],
        ];

        $requestData = ['storeId' => 1];
        $quoteId = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue(strlen($quoteId) >= 32);
        $this->createdQuotes[] = $quoteId;
    }

    public function tearDown()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote');
        foreach ($this->createdQuotes as $quoteId) {
            $quote->load($quoteId);
            $quote->delete();
            /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
            $quoteIdMask = $this->objectManager->create('Magento\Quote\Model\QuoteIdMask');
            $quoteIdMask->delete($quote->getId());
        }
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/quote.php
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testAssignCustomer()
    {
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');
        $cartId = $quote->getId();
        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Quote\Model\QuoteIdMaskFactory')
            ->create();
        $quoteIdMask->load($cartId);
        //Use masked cart Id
        $cartId = $quoteIdMask->getMaskedId();

        /** @var $repository \Magento\Customer\Api\CustomerRepositoryInterface */
        $repository = $this->objectManager->create('Magento\Customer\Api\CustomerRepositoryInterface');
        /** @var $customer \Magento\Customer\Api\Data\CustomerInterface */
        $customer = $repository->getById(1);
        $customerId = $customer->getId();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/guest-carts/' . $cartId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => 'V1',
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
            ],
        ];

        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];
        // Cart must be anonymous (see fixture)
        $this->assertEmpty($quote->getCustomerId());

        $this->assertTrue($this->_webApiCall($serviceInfo, $requestData));
        // Reload target quote
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');
        $this->assertEquals(0, $quote->getCustomerIsGuest());
        $this->assertEquals($customer->getId(), $quote->getCustomerId());
        $this->assertEquals($customer->getFirstname(), $quote->getCustomerFirstname());
        $this->assertEquals($customer->getLastname(), $quote->getCustomerLastname());
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/quote.php
     * @expectedException \Exception
     */
    public function testAssignCustomerThrowsExceptionIfThereIsNoCustomerWithGivenId()
    {
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');
        $cartId = $quote->getId();
        $customerId = 9999;
        $serviceInfo = [
            'soap' => [
                'serviceVersion' => 'V1',
                'service' => self::SERVICE_NAME,
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
            ],
            'rest' => [
                'resourcePath' => '/V1/guest-carts/' . $cartId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
        ];
        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];

        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @expectedException \Exception
     */
    public function testAssignCustomerThrowsExceptionIfThereIsNoCartWithGivenId()
    {
        $cartId = 9999;
        $customerId = 1;
        $serviceInfo = [
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => 'V1',
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
            ],
            'rest' => [
                'resourcePath' => '/V1/guest-carts/' . $cartId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
        ];
        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];

        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/quote_with_customer.php
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot assign customer to the given cart. The cart is not anonymous.
     */
    public function testAssignCustomerThrowsExceptionIfTargetCartIsNotAnonymous()
    {
        /** @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->objectManager->create('Magento\Customer\Model\Customer')->load(1);
        $customerId = $customer->getId();
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');
        $cartId = $quote->getId();

        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Quote\Model\QuoteIdMaskFactory')
            ->create();
        $quoteIdMask->load($cartId);
        //Use masked cart Id
        $cartId = $quoteIdMask->getMaskedId();

        $serviceInfo = [
            'rest' => [
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
                'resourcePath' => '/V1/guest-carts/' . $cartId,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => 'V1',
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
            ],
        ];

        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];
        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/quote.php
     * @magentoApiDataFixture Magento/Customer/_files/customer_non_default_website_id.php
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot assign customer to the given cart. The cart belongs to different store.
     */
    public function testAssignCustomerThrowsExceptionIfCartIsAssignedToDifferentStore()
    {
        $repository = $this->objectManager->create('Magento\Customer\Api\CustomerRepositoryInterface');
        /** @var $customer \Magento\Customer\Api\Data\CustomerInterface */
        $customer = $repository->getById(1);
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');

        $customerId = $customer->getId();
        $cartId = $quote->getId();

        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Quote\Model\QuoteIdMaskFactory')
            ->create();
        $quoteIdMask->load($cartId);
        //Use masked cart Id
        $cartId = $quoteIdMask->getMaskedId();

        $serviceInfo = [
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => 'V1',
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
            ],
            'rest' => [
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
                'resourcePath' => '/V1/guest-carts/' . $cartId,
            ],
        ];

        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];
        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Sales/_files/quote.php
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot assign customer to the given cart. Customer already has active cart.
     */
    public function testAssignCustomerThrowsExceptionIfCustomerAlreadyHasActiveCart()
    {
        /** @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->objectManager->create('Magento\Customer\Model\Customer')->load(1);
        // Customer has a quote with reserved order ID test_order_1 (see fixture)
        /** @var $customerQuote \Magento\Quote\Model\Quote */
        $customerQuote = $this->objectManager->create('Magento\Quote\Model\Quote')
            ->load('test_order_1', 'reserved_order_id');
        $customerQuote->setIsActive(1)->save();
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test01', 'reserved_order_id');

        $cartId = $quote->getId();

        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Quote\Model\QuoteIdMaskFactory')
            ->create();
        $quoteIdMask->load($cartId);
        //Use masked cart Id
        $cartId = $quoteIdMask->getMaskedId();

        $customerId = $customer->getId();

        $serviceInfo = [
            'soap' => [
                'service' => self::SERVICE_NAME,
                'operation' => self::SERVICE_NAME . 'AssignCustomer',
                'serviceVersion' => 'V1',
            ],
            'rest' => [
                'resourcePath' => '/V1/guest-carts/' . $cartId,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
        ];

        $requestData = [
            'cartId' => $cartId,
            'customerId' => $customerId,
            'storeId' => 1,
        ];
        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_check_payment.php
     */
    public function testPlaceOrder()
    {
        /** @var $quote \Magento\Quote\Model\Quote */
        $quote = $this->objectManager->create('Magento\Quote\Model\Quote')->load('test_order_1', 'reserved_order_id');
        $cartId = $quote->getId();
        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Quote\Model\QuoteIdMaskFactory')
            ->create();
        $quoteIdMask->load($cartId);
        //Use masked cart Id
        $cartId = $quoteIdMask->getMaskedId();

        $serviceInfo = [
            'soap' => [
                'service' => 'quoteGuestCartManagementV1',
                'operation' => 'quoteGuestCartManagementV1PlaceOrder',
                'serviceVersion' => 'V1',
            ],
            'rest' => [
                'resourcePath' => '/V1/guest-carts/' . $cartId . '/order',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_PUT,
            ],
        ];

        $orderId = $this->_webApiCall($serviceInfo, ['cartId' => $cartId]);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->create('Magento\Sales\Model\Order')->load($orderId);
        $items = $order->getAllItems();
        $this->assertCount(1, $items);
        $this->assertEquals('Simple Product', $items[0]->getName());
    }
}
