<?php
/**
 * Landofcoder
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the Landofcoder.com license that is
 * available through the world-wide-web at this URL:
 * https://landofcoder.com/license
 * 
 * DISCLAIMER
 * 
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 * 
 * @category   Landofcoder
 * @package    Lof_NewsletterAjax
 * @copyright  Copyright (c) 2020 Landofcoder (https://www.landofcoder.com/)
 * @license    https://www.landofcoder.com/LICENSE-1.0.html
 */

namespace Lof\NewsletterAjax\Plugin\Magento\Newsletter\Controller\Subscriber;

use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\Manager;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use Lof\NewsletterAjax\Helper\Data;
/**
 * Class NewAction
 *
 * @package Lof\NewsletterAjax\Plugin\Magento\Newsletter\Controller\Subscriber
 */
class NewAction extends \Magento\Newsletter\Controller\Subscriber\NewAction
{
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;
    /**
     * @var Manager
     */
    protected $messageManager;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * NewAction constructor.
     *
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param Manager $messageManager
     * @param Context $context
     * @param SubscriberFactory $subscriberFactory
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param CustomerUrl $customerUrl
     * @param CustomerAccountManagement $customerAccountManagement
     * @param Data $helperData
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Manager $messageManager,
        Context $context,
        SubscriberFactory $subscriberFactory,
        Session $customerSession,
        StoreManagerInterface $storeManager,
        CustomerUrl $customerUrl,
        CustomerAccountManagement $customerAccountManagement,
        Data $helperData
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->messageManager = $messageManager;
        $this->helperData = $helperData;
        parent::__construct(
            $context,
            $subscriberFactory,
            $customerSession,
            $storeManager,
            $customerUrl,
            $customerAccountManagement
        );
    }

    /**
     * Executes method around
     *
     * @param \Magento\Newsletter\Controller\Subscriber\NewAction $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function aroundExecute(\Magento\Newsletter\Controller\Subscriber\NewAction $subject, callable $proceed)
    {
        $is_enabled = $this->helperData->getGeneralConfig("enable");
        if($is_enabled){
            $isAjax = $this->request->isXmlHttpRequest();

            if ($isAjax) {
                $this->messageManager->getMessages(true);
                return $this->saveSubscription();
            }
        }
        return $proceed();
    }

    /**
     * Save subscription, validation used from parent class
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function saveSubscription()
    {
        $email = $this->request->getParam('email');
        $jsonFactory = $this->jsonFactory->create();

        $subscribed_text = $this->helperData->getGeneralConfig("is_subscribed_text");
        $thanksyou_text = $this->helperData->getGeneralConfig("thanksyou_text");
        $confirm_text = $this->helperData->getGeneralConfig("confirm_text");

        $subscribed_text = $subscribed_text?$subscribed_text:__('This email address is already subscribed.');
        $thanksyou_text = $thanksyou_text?$thanksyou_text:__('Thank you for your subscription.');
        $confirm_text = $confirm_text?$confirm_text:__('The confirmation request has been sent.');

        try {
            $this->validateEmailFormat($email);
            $this->validateGuestSubscription();
            $this->validateEmailAvailable($email);

            $subscriber = $this->_subscriberFactory->create()->loadByEmail($email);
            if ($subscriber->getId()
                && $subscriber->getSubscriberStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
            ) {
                $jsonData = [
                    'status' => 'error',
                    'message' => $subscribed_text
                ];

                return $jsonFactory->setData($jsonData);
            }

            $status = $this->_subscriberFactory->create()->subscribe($email);

            if ($status == \Magento\Newsletter\Model\Subscriber::STATUS_NOT_ACTIVE) {
                $jsonData = [
                    'status' => 'success',
                    'message' => $confirm_text
                ];
            } else {
                $jsonData = [
                    'status' => 'success',
                    'message' => $thanksyou_text
                ];
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $jsonData = [
                'status' => 'error',
                'message' => __('There was a problem with the subscription: %1', $e->getMessage())
            ];
        } catch (\Exception $e) {
            $jsonData = [
                'status' => 'error',
                'message' => __('Something went wrong with the subscription.')
            ];
        }

        return $jsonFactory->setData($jsonData);
    }
}
