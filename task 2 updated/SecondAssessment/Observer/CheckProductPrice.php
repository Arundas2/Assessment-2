<?php
/**
 * @package Ceymox_SecondAssessment
 */
declare(strict_types=1);

namespace Ceymox\SecondAssessment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;

class CheckProductPrice implements ObserverInterface
{

    /**
     *
     * @var CustomerSession $customerSession
     */
    protected $customerSession;

    /**
     * constructor
     *
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        CustomerSession $customerSession,
    ) {
        $this->customerSession = $customerSession;
    }

    /**
     * Excecute Observer
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getProduct();

        if ($product->getPrice() > 100 && !$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('You need to login for adding any product worth 100 or more'));
        }
    }
}
