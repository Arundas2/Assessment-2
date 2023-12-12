<?php
namespace Ceymox\SecondAssessment\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Customer\Model\Session as CustomerSession;

class AddToCart implements ResolverInterface
{
    protected $quoteRepository;
    protected $valueFactory;
    protected $customerSession;

    public function __construct(
        QuoteRepository $quoteRepository,
        ValueFactory $valueFactory,
        CustomerSession $customerSession
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->valueFactory = $valueFactory;
        $this->customerSession = $customerSession;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cartId = $args['input']['cart_id'];
        $cartItems = $args['input']['cart_items'];

        foreach ($cartItems as $cartItem) {
            $sku = $cartItem['sku'];
            $quantity = $cartItem['quantity'];

            if ($this->ProductPrice($sku) && !$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('You need to log-in for adding any product worth 100 or more'));
            }
        }

        $result = $this->valueFactory->create($value, $context, $info, 'cart');
        return ['cart' => $result];
    }

    private function ProductPrice($sku)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $product = $objectManager->create(\Magento\Catalog\Model\Product::class)->loadByAttribute('sku', $sku);

        if ($product && $product->getFinalPrice() > 100) {
            return true;
        }

        return false;
    }

}
