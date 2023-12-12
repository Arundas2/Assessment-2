<?php
/**
 * @package Ceymox_SecondAssessment
 */
declare(strict_types=1);

namespace Ceymox\SecondAssessment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Message\ManagerInterface as MessageManager;

class CheckProductPrice implements ObserverInterface
{

    /**
     *
     * @var CustomerSession $customerSession
     */
    protected $customerSession;

    /**
     *
     * @var MessageManager $messageManager
     */
    protected $messageManager;

    /**
     * constructor
     *
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        CustomerSession $customerSession,
        MessageManager $messageManager
    ) {
        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
    }

    /**
     * Excecute Observer 
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getData('quote_item');
        $product = $item->getProduct();

        if ($product->getFinalPrice() > 100 && !$this->customerSession->isLoggedIn()) {
            $this->messageManager->addError('You need to login for adding any product worth 100 or more');
            $item->getQuote()->removeItem($item->getId());
        }
    }
}
