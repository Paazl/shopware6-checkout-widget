<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Core\Checkout\Cart\Delivery;

use PaazlCheckoutWidget\Model\DeliveryType;
use PaazlCheckoutWidget\Model\PaazlToken;
use PaazlCheckoutWidget\PaazlCheckoutWidget;
use PaazlCheckoutWidget\RestApi\PaazlApiException;
use PaazlCheckoutWidget\RestApi\PaazlApiTokenProvider;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\CartPaazlTokenService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

readonly class DeliveryProcessor implements CartDataCollectorInterface, CartProcessorInterface
{
    public function __construct(
        private DeliveryBuilder $builder,
        private DeliveryCalculator $deliveryCalculator,
        private PaazlClient $paazlClient,
        private CartPaazlTokenService $cartPaazlTokenService,
        private PaazlApiTokenProvider $apiTokenProvider
    ) {
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        if ($original->getDeliveries()->first()?->getShippingMethod()->getCustomFieldsValue('paazlName') !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }

        if ($original->getLineItems()->count() === 0) {
            return;
        }

        try {
            $data->set('paazlToken', $this->apiTokenProvider->getApiToken(
                $original,
                $context
            ));

            $data->set('paazlCheckout', $this->paazlClient->getPaazlCheckoutDetails(
                $this->cartPaazlTokenService->getReference($original->getToken(), $context->getSalesChannelId()),
                $context->getSalesChannelId()
            ));
        } catch (PaazlApiException $e) {
            $data->remove('paazlToken');
            $data->remove('paazlCheckout');
        }
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $toCalculate->addState('no-paazl');

        if (!$data->has('paazlCheckout')
            || !$data->has('paazlToken')
            || $original->getDeliveries()->first()?->getShippingMethod()->getCustomFieldsValue('paazlName') !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME
        ) {
            return;
        }

        $deliveries = $this->builder->build($toCalculate, $data, $context, $behavior);
        $delivery = $deliveries->first();
        if (!$delivery) {
            return;
        }

        /** @var array $paazlCheckout */
        $paazlCheckout = $data->get('paazlCheckout');

        if (!array_key_exists('shippingOption', $paazlCheckout)) {
            return;
        }

        $toCalculate->removeState('no-paazl');

        $paazlTitle = $paazlCheckout['shippingOption']['name'];
        if (DeliveryType::isPickup($paazlCheckout['deliveryType'])) {
            $paazlTitle = $paazlCheckout['pickupLocation']['name'];
        }

        $paazlRate = 0;
        $paazlDeliveryDateEarliest = null;
        $paazlDeliveryDateLatest = null;

        if ($shippingOption = ($paazlCheckout['shippingOption'] ?? null)) {
            $paazlRate = $shippingOption['rate'];

            if (!empty($shippingOption['deliveryDates'] ?? null)) {
                $paazlDeliveryDateEarliest = reset($shippingOption['deliveryDates'])['deliveryDate'];
                $paazlDeliveryDateLatest = end($shippingOption['deliveryDates'])['deliveryDate'];
            }
        }

        $reference = $this->cartPaazlTokenService->getReference($original->getToken(), $context->getSalesChannelId());

        // Set paazl widget price
        $delivery->setShippingCosts(
            new CalculatedPrice(
                $paazlRate,
                $paazlRate,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        // Set paazl api data in customFields
        $customFields = $delivery->getShippingMethod()->getCustomFields();
        $customFields['paazlTitle'] = $paazlTitle;
        $customFields['paazlData'] = $paazlCheckout;
        $customFields['paazlOrderReference'] = $reference;
        $customFields['paazlToken'] = $data->get('paazlToken');
        $customFields['paazlProducts'] = $this->getPaazlPayload($toCalculate->getLineItems(), $context);
        $customFields['paazlDeliveryDateEarliest'] = $paazlDeliveryDateEarliest;
        $customFields['paazlDeliveryDateLatest'] = $paazlDeliveryDateLatest;

        $delivery->getShippingMethod()->setCustomFields($customFields);

        if (is_string($customFields['paazlToken'])) {
            $toCalculate->addExtension('paazlToken', new PaazlToken($customFields['paazlToken']));
        }

        // Set all things to real Cart
        $this->deliveryCalculator->calculate($data, $toCalculate, $deliveries, $context);
        $toCalculate->setDeliveries($deliveries);
    }

    private function getPaazlPayload(LineItemCollection $lineItems, SalesChannelContext $context): array
    {
        $product['lineItem'] = [];
        $product['totalWeight'] = 0.00;

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
                    'currency' => $context->getCurrency()->getIsoCode(),
                ],
                'description' => $lineItem->getLabel(),
            ];

            if ($lineItem->getDeliveryInformation()) {
                if ($lineItem->getDeliveryInformation()->getWeight() > 0) {
                    $itemData['weight'] = $lineItem->getDeliveryInformation()->getWeight();
                    $product['totalWeight'] += (float)$lineItem->getDeliveryInformation()->getWeight();
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
