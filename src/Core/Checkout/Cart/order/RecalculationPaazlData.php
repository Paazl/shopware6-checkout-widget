<?php declare(strict_types=1);

namespace PaazlCheckoutWidget\Core\Checkout\Cart\order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\OrderRecalculationException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class RecalculationPaazlData extends RecalculationService
{
    protected EntityRepositoryInterface $orderRepository;

    protected OrderConverter $orderConverter;

    protected CartService $cartService;

    protected EntityRepositoryInterface $productRepository;

    protected EntityRepositoryInterface $orderAddressRepository;

    protected EntityRepositoryInterface $customerAddressRepository;

    protected Processor $processor;

    private CartRuleLoader $cartRuleLoader;

    private PromotionItemBuilder $promotionItemBuilder;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        OrderConverter $orderConverter,
        CartService $cartService,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $orderAddressRepository,
        EntityRepositoryInterface $customerAddressRepository,
        Processor $processor,
        CartRuleLoader $cartRuleLoader,
        PromotionItemBuilder $promotionItemBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderConverter = $orderConverter;
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->processor = $processor;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->promotionItemBuilder = $promotionItemBuilder;
    }

    /**
     * @throws InvalidOrderException
     * @throws OrderRecalculationException
     * @throws CustomerNotLoggedInException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function recalculateOrder(string $orderId, Context $context): void
    {
        $order = $this->fetchOrder($orderId, $context);

        $this->validateOrder($order, $orderId);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $recalculatedCart = $this->refresh($cart, $salesChannelContext);

        $conversionContext = (new OrderConversionContext())
            ->setIncludeCustomer(false)
            ->setIncludeBillingAddress(false)
            ->setIncludeDeliveries(true)
            ->setIncludeTransactions(false)
            ->setIncludeOrderDate(false);

        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $orderData['customFields'] = $order->getCustomFields();
        $orderData['id'] = $order->getId();

        // change scope to be able to write protected state fields of transactions and deliveries
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            $this->orderRepository->upsert([$orderData], $context);
        });
    }

    private function refresh(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = new CartBehavior($context->getPermissions());

        // all prices are now prepared for calculation -  starts the cart calculation
        return $this->processor->process($cart, $context, $behavior);
    }

    public function addProductToOrder(string $orderId, string $productId, int $quantity, Context $context): void
    {
        $this->validateProduct($productId, $context);
        $lineItem = (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity))
            ->setRemovable(true)
            ->setStackable(true);

        $order = $this->fetchOrder($orderId, $context);

        $this->validateOrder($order, $orderId);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);

        $cart->add($lineItem);

        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $new = $cart->get($lineItem->getId());
        if ($new) {
            $this->addProductToDeliveryPosition($new, $recalculatedCart);
        }

        $conversionContext = (new OrderConversionContext())
            ->setIncludeCustomer(false)
            ->setIncludeBillingAddress(false)
            ->setIncludeDeliveries(true)
            ->setIncludeTransactions(false)
            ->setIncludeOrderDate(false);

        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);
        $orderData['customFields'] = $order->getCustomFields();
        $orderData['id'] = $order->getId();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            $this->orderRepository->upsert([$orderData], $context);
        });
    }

    private function addProductToDeliveryPosition(LineItem $item, Cart $cart): void
    {
        if ($cart->getDeliveries()->count() <= 0) {
            return;
        }

        $delivery = $cart->getDeliveries()->first();
        if (!$delivery) {
            return;
        }

        $position = new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), $item->getPrice(), $delivery->getDeliveryDate());

        $delivery->getPositions()->add($position);
    }

    private function fetchOrder(string $orderId, Context $context)
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        return $this->orderRepository
            ->search($criteria, $context)
            ->get($orderId);
    }

    /**
     * @throws OrderRecalculationException
     * @throws InvalidOrderException
     */
    private function validateOrder(?OrderEntity $order, string $orderId): void
    {
        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $this->checkVersion($order);
    }

    /**
     * @throws ProductNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    protected function validateProduct(string $productId, Context $context): void
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }
    }

    /**
     * @throws OrderRecalculationException
     */
    private function checkVersion(Entity $entity): void
    {
        if ($entity->getVersionId() === Defaults::LIVE_VERSION) {
            throw new OrderRecalculationException(
                $entity->getUniqueIdentifier(),
                'Live versions can\'t be recalculated. Please create a new version.'
            );
        }
    }

    private function recalculateCart(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = new CartBehavior($context->getPermissions());

        // all prices are now prepared for calculation -  starts the cart calculation
        $cart = $this->processor->process($cart, $context, $behavior);

        // validate cart against the context rules
        $validated = $this->cartRuleLoader->loadByCart($context, $cart, $behavior);

        $cart = $validated->getCart();

        return $cart;
    }
}
