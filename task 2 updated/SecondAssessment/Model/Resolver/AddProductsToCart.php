<?php
/**
 * @package Ceymox_SecondAssessment
 */
declare(strict_types=1);

namespace Ceymox\SecondAssessment\Model\Resolver;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Magento\Quote\Model\Cart\Data\AddProductsToCartOutput;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\QuoteMutexInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\Processor\ItemDataProcessorInterface;
use Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class AddProductsToCart implements ResolverInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var AddProductsToCartService
     */
    private $addProductsToCartService;

    /**
     * @var QuoteMutexInterface
     */
    private $quoteMutex;

    /**
     * @var PrecursorInterface|null
     */
    private $cartItemPrecursor;

    /**
     * @param GetCartForUser $getCartForUser
     * @param AddProductsToCartService $addProductsToCart
     * @param ItemDataProcessorInterface $itemDataProcessor
     * @param QuoteMutexInterface $quoteMutex
     * @param PrecursorInterface|null $cartItemPrecursor
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        AddProductsToCartService $addProductsToCart,
        ItemDataProcessorInterface $itemDataProcessor,
        QuoteMutexInterface $quoteMutex,
        PrecursorInterface $cartItemPrecursor = null,
        ProductRepositoryInterface $productRepository
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->addProductsToCartService = $addProductsToCart;
        $this->quoteMutex = $quoteMutex;
        $this->cartItemPrecursor = $cartItemPrecursor ?: ObjectManager::getInstance()->get(PrecursorInterface::class);
        $this->productRepository = $productRepository;
    }

    /**
     * Resolver function
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolverInfo $info
     * @param array $value
     * @param array $args
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }
        if (empty($args['cartItems']) || !is_array($args['cartItems'])
        ) {
            throw new GraphQlInputException(__('Required parameter "cartItems" is missing'));
        }

        return $this->quoteMutex->execute(
            [$args['cartId']],
            \Closure::fromCallable([$this, 'run']),
            [$context, $args]
        );
    }

    /**
     * Run the resolver.
     *
     * @param ContextInterface $context
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function run($context, ?array $args): array
    {
        $maskedCartId = $args['cartId'];
        $cartItemsData = $args['cartItems'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $cartItemsData = $this->cartItemPrecursor->process($cartItemsData, $context);
        $cartItems = [];
        foreach ($cartItemsData as $cartItemData) {
            $cartItems[] = (new CartItemFactory())->create($cartItemData);
        }

        $this->productPrices($context, $cartItems);

        $addProductsToCartOutput = $this->addProductsToCartService->execute($maskedCartId, $cartItems);

        return [
            'cart' => [
                'model' => $addProductsToCartOutput->getCart(),
            ],
            'user_errors' => array_map(
                function (Error $error) {
                    return [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                        'path' => [$error->getCartItemPosition()]
                    ];
                },
                array_merge($addProductsToCartOutput->getErrors(), $this->cartItemPrecursor->getErrors())
            )
        ];
    }

    /**
     * Checking Product price
     *
     * @param ContextInterface $context
     * @param array $cartItems
     */
    private function productPrices(ContextInterface $context, array $cartItems)
    {
        foreach ($cartItems as $cartItem) {
            $productSku = $cartItem->getSku();
            $userid = $context->getUserId();
            $customerIsLoggedIn = $context->getUserId() !== null;
            $product = $this->productRepository->get($productSku);
            $productPrice = $product->getFinalPrice();

            if ($productPrice > 100 && ($userid == 0)) {
                throw new GraphQlInputException(__('You need to log-in for adding any product worth 100 or more'));
            }
        }
    }
}
