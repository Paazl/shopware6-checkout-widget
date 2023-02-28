<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Core\Checkout\Cart\Delivery;

use PaazlCheckoutWidget\Model\PaazlToken;
use PaazlCheckoutWidget\PaazlCheckoutWidget;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\CartPaazlTokenService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryProcessor implements CartProcessorInterface
{
    private const PICKUP = 'PICKUP_LOCATION';
    private const DELIVERY = 'HOME';

    protected DeliveryBuilder $builder;

    protected DeliveryCalculator $deliveryCalculator;

    private PaazlClient $paazlClient;

    private CartPaazlTokenService $cartPaazlTokenService;

    public function __construct(
        DeliveryBuilder $builder,
        DeliveryCalculator $deliveryCalculator,
        PaazlClient $paazlClient,
        CartPaazlTokenService $cartPaazlTokenService
    ) {
        $this->builder = $builder;
        $this->deliveryCalculator = $deliveryCalculator;
        $this->paazlClient = $paazlClient;
        $this->cartPaazlTokenService = $cartPaazlTokenService;
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        if ($context->getShippingMethod()->getName() !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }

        $deliveries = $this->builder->build($toCalculate, $data, $context, $behavior);
        $delivery = $deliveries->first();

        if (!$delivery) {
            return;
        }

        // Must be called BEFORE any other paazlClient calls
        $paazlToken = $this->paazlClient->getApiToken($original, $context->getSalesChannelId(), $context->getContext(), 'front', $context);

        $paazlCheckout = $this->paazlClient->getPaazlCheckoutDetails($original, $context);
        $paazlTitle = null;
        if (array_key_exists('deliveryType', $paazlCheckout)) {
            if ($paazlCheckout['deliveryType'] === self::DELIVERY) {
                $paazlTitle = $paazlCheckout['shippingOption']['name'];
            } elseif ($paazlCheckout['deliveryType'] === self::PICKUP) {
                $paazlTitle = $paazlCheckout['pickupLocation']['name'];
            }
        }

        $paazlRate = 0;
        if (array_key_exists('shippingOption', $paazlCheckout)) {
            $paazlRate = $paazlCheckout['shippingOption']['rate'];
        }
        //check Paazl rate exist
        $paazlDeliveryDateEarliest = $paazlDeliveryDateLatest = null;
        if (array_key_exists('shippingOption', $paazlCheckout)) {
            $paazlRate = $paazlCheckout['shippingOption']['rate'];
            $shippingOption = $paazlCheckout['shippingOption'];
            if (array_key_exists('deliveryDates', $shippingOption)
                && !empty($shippingOption['deliveryDates'])) {
                $paazlDeliveryDateEarliest = $shippingOption['deliveryDates'][0]['deliveryDate'];
                $paazlDeliveryDateLatest = end($shippingOption['deliveryDates'])['deliveryDate'];
            }
        }

        //set paazl widget price
        $delivery->setShippingCosts(
            new CalculatedPrice(
                $paazlRate,
                $paazlRate,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        //set paazl api data in customFields
        $customFields = $delivery->getShippingMethod()->getCustomFields();
        $customFields['paazlTitle'] = $paazlTitle;
        $customFields['paazlData'] = $paazlCheckout;
        $customFields['paazlOrderReference'] = $this->cartPaazlTokenService->getReference($original->getToken());
        $customFields['paazlToken'] = $paazlToken;
        $customFields['paazlProducts'] = $this->getPaazlPayload($toCalculate->getLineItems(), $context);
        $customFields['paazlDeliveryDateEarliest'] = $paazlDeliveryDateEarliest;
        $customFields['paazlDeliveryDateLatest'] = $paazlDeliveryDateLatest;

        $delivery->getShippingMethod()->setCustomFields($customFields);

        $toCalculate->addExtension('paazlToken', new PaazlToken($customFields['paazlToken']));

        //set all things to real Cart
        $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);
        $toCalculate->setDeliveries($deliveries);
    }

    private function getPaazlPayload(LineItemCollection $lineItems, SalesChannelContext $context): array
    {
        $product['lineItem'] = [];
        $product['totalWeight'] = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            if (!$lineItem->getPrice()) {
                continue;
            }

            $itemData = [
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => [
                    'value' => $lineItem->getPrice()->getUnitPrice(),
                    'currency' => $context->getCurrency()->getIsoCode()
                ],
                'description' => $lineItem->getLabel()
            ];

            if ($lineItem->getDeliveryInformation()) {
                if ($lineItem->getDeliveryInformation()->getWeight() > 0) {
                    $product['totalWeight'][] = $itemData['weight'] = $lineItem->getDeliveryInformation()->getWeight();
                }

                $itemData['width'] = $lineItem->getDeliveryInformation()->getWidth();
                $itemData['height'] = $lineItem->getDeliveryInformation()->getHeight();
                $itemData['length'] = $lineItem->getDeliveryInformation()->getLength();
            }

            $product['lineItem'][] = $itemData;
        }

        return $product;
    }
}
