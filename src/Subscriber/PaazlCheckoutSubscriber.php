<?php declare(strict_types=1);

namespace PaazlCheckoutWidget\Subscriber;

use PaazlCheckoutWidget\PaazlCheckoutWidget;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\CartPaazlTokenService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaazlCheckoutSubscriber implements EventSubscriberInterface
{
    private PaazlClient $paazlClient;

    private EntityRepositoryInterface $countryRepository;

    private CartPaazlTokenService $cartPaazlTokenService;

    private SystemConfigService $systemConfigService;

    public function __construct(
        paazlClient $paazlClient,
        EntityRepositoryInterface $countryRepository,
        CartPaazlTokenService $cartPaazlTokenService,
        SystemConfigService $systemConfigService
    ) {
        $this->paazlClient = $paazlClient;
        $this->countryRepository = $countryRepository;
        $this->cartPaazlTokenService = $cartPaazlTokenService;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'paazlCheckoutFunction',
            CartConvertedEvent::class => 'addCustomFieldsToConvertedCart',
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
            MailBeforeValidateEvent::class => 'onBeforeValidate',
            CustomerLogoutEvent::class => 'onCustomerLogout'
        ];
    }

    public function onBeforeValidate(MailBeforeValidateEvent $event): void
    {
        $data = $event->getData();

        if (!array_key_exists('order', $event->getTemplateData())) {
            return;
        }

        $order = $event->getTemplateData()['order'];
        if (!$order instanceof OrderEntity) {
            return;
        }

        $activeShippingMethod = '';
        if ($order->getDeliveries() && $order->getDeliveries()->first()
            && $order->getDeliveries()->first()->getShippingMethod()) {
            $activeShippingMethod = $order->getDeliveries()->first()
                ->getShippingMethod()->getName();
        }

        if ($activeShippingMethod === 'Paazl') {
            $paazlData = "
{% if order.customFields.paazlTitle is defined %}
    {{ '[' }} {{ order.customFields.paazlTitle }} {{ ']' }}
    <p><b>Paazl ReferenceId : </b>{{ order.customFields.paazlOrderReference }}</p>
    <p><b>Paazl Status : </b>{{ order.customFields.PaazlPost }}</p>
    <table class='paazlBackendData' border='1'>
        <tr>
            <th colspan='2'>Paazl Order Data</th>
        </tr>
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.identifier is defined %}
        <tr>
            <td>Identifier</td>
            <td>{{ order.customFields.paazlData.shippingOption.identifier }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.carrier.name is defined %}
        <tr>
            <td>Carrier Name</td>
            <td>{{ order.customFields.paazlData.shippingOption.carrier.name }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.carrier.description is defined %}
        <tr>
            <td>Carrier Description</td>
            <td>{{ order.customFields.paazlData.shippingOption.carrier.description }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.estimatedDeliveryRange is defined &&
        order.customFields.paazlData.shippingOption.estimatedDeliveryRange.earliestDate is defined %}
        <tr>
            <td>Estimated Delivery Range Earliest Date</td>
            <td>{{ order.customFields.paazlData.shippingOption.estimatedDeliveryRange.earliestDate }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.pickupLocation is defined &&
        order.customFields.paazlData.pickupLocation.address is defined %}
        <tr>
            <td>Pickup Location</td>
            <td>{% if order.customFields.paazlData.pickupLocation.address.streetNumberSuffix is defined %}
            {{ order.customFields.paazlData.pickupLocation.address.streetNumberSuffix }}{% endif %},
                {% if order.customFields.paazlData.pickupLocation.address.streetNumber is defined %}
                {{ order.customFields.paazlData.pickupLocation.address.streetNumber }}{% endif %},
                {% if order.customFields.paazlData.pickupLocation.address.street is defined %}
                {{ order.customFields.paazlData.pickupLocation.address.street }}{% endif %},
                {% if order.customFields.paazlData.pickupLocation.address.postalCode is defined %}
                {{ order.customFields.paazlData.pickupLocation.address.postalCode }}{% endif %},
                {% if order.customFields.paazlData.pickupLocation.address.city is defined %}
                {{ order.customFields.paazlData.pickupLocation.address.city }}{% endif %},
                {% if order.customFields.paazlData.pickupLocation.address.country is defined %}
                {{ order.customFields.paazlData.pickupLocation.address.country }}{% endif %}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.pickupLocation is defined &&
        order.customFields.paazlData.pickupLocation.name is defined %}
        <tr>
            <td>Pickup Location Name</td>
            <td>{{ order.customFields.paazlData.pickupLocation.name }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.pickupLocation is defined &&
        order.customFields.paazlData.pickupLocation.code is defined %}
        <tr>
            <td>Pickup Location Code</td>
            <td>{{ order.customFields.paazlData.pickupLocation.code }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.deliveryDates[0] is defined &&
        order.customFields.paazlData.shippingOption.deliveryDates[0].deliveryDate is defined %}
        <tr>
            <td>Delivery Date</td>
            <td>{{ order.customFields.paazlData.shippingOption.deliveryDates[0].deliveryDate }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.deliveryDates[0] is defined &&
        order.customFields.paazlData.shippingOption.deliveryDates[0].pickupDate is defined %}
        <tr>
            <td>Pickup Date</td>
            <td>{{ order.customFields.paazlData.shippingOption.deliveryDates[0].pickupDate }}</td>
        </tr>
        {% endif %}
        {% if order.customFields.paazlData.shippingOption is defined &&
        order.customFields.paazlData.shippingOption.estimatedDeliveryRange is defined &&
        order.customFields.paazlData.shippingOption.estimatedDeliveryRange.min is defined &&
        order.customFields.paazlData.shippingOption.estimatedDeliveryRange.max is defined %}
        <tr>
            <td>Estimate Delivery range</td>
            <td>{{ order.customFields.paazlData.shippingOption.estimatedDeliveryRange.min }} To
            {{ order.customFields.paazlData.shippingOption.estimatedDeliveryRange.max }} Days
            </td>
        </tr>
        {% endif %}
    </table>
{% endif %}";

            $data['contentHtml'] = str_replace('%paazlData%', $paazlData, $data['contentHtml']);
        } else {
            $data['contentHtml'] = str_replace('%paazlData%', '', $data['contentHtml']);
        }
        $event->setData($data);
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        $this->cartPaazlTokenService->deleteToken($event->getSalesChannelContext()->getToken());
    }

    public function paazlCheckoutFunction(PageLoadedEvent $event): void
    {
        $activeShippingMethod = $event->getSalesChannelContext()->getShippingMethod()->getName();
        if ($activeShippingMethod !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }

        $event->getPage()->assign([
            'paazlConfig' => $this->paazlClient->generatePaazlScript($event->getSalesChannelContext()),
        ]);
    }

    public function addCustomFieldsToConvertedCart(CartConvertedEvent $event): void
    {
        $activeShippingMethod = $event->getSalesChannelContext()->getShippingMethod()->getName();
        if ($activeShippingMethod !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }

        $shippingData = $event->getCart()->getDeliveries()->first()->getShippingMethod()->getCustomFields();
        $conCart = $event->getConvertedCart();

        //set shipping address in order data
        if (array_key_exists('paazlData', $shippingData) && !empty($shippingData['paazlData'])) {
            $paazlData = $shippingData['paazlData'];

            if (array_key_exists('pickupLocation', $paazlData) && !empty($paazlData['pickupLocation'])) {
                $pickupLocation = $paazlData['pickupLocation'];

                if (array_key_exists('address', $pickupLocation) && !empty($pickupLocation['address'])) {
                    $pickupAddress = $pickupLocation['address'];

                    if (!empty($pickupAddress['streetNumber']) &&
                        !empty($pickupAddress['street']) &&
                        !empty($pickupAddress['streetNumberSuffix'])) {
                        $street = $pickupAddress['street'] . ' ' .
                            $pickupAddress['streetNumber'] . ' ' .
                            $pickupAddress['streetNumberSuffix'];
                        $conCart['deliveries'][0]['shippingOrderAddress']['street'] = $street;
                    } elseif (!empty($pickupAddress['streetNumber']) && !empty($pickupAddress['street'])) {
                        $street = $pickupAddress['street'] . ' ' . $pickupAddress['streetNumber'];
                        $conCart['deliveries'][0]['shippingOrderAddress']['street'] = $street;
                    } elseif (!empty($pickupAddress['street'])) {
                        $street = $pickupAddress['street'];
                        $conCart['deliveries'][0]['shippingOrderAddress']['street'] = $street;
                    }

                    $context = $event->getSalesChannelContext()->getContext();
                    $country = $this->getCountryId($pickupAddress['country'], $context);
                    $conCart['deliveries'][0]['shippingOrderAddress']['zipcode'] = $pickupAddress['postalCode'];
                    $conCart['deliveries'][0]['shippingOrderAddress']['city'] = $pickupAddress['city'];
                    $conCart['deliveries'][0]['shippingOrderAddress']['countryId'] = $country;
                }
            }
        }

        //set delivery date in zorder data
        if ($shippingData['paazlDeliveryDateEarliest'] !== null) {
            $conCart['deliveries'][0]['shippingDateEarliest'] = $shippingData['paazlDeliveryDateEarliest'];
        }
        if ($shippingData['paazlDeliveryDateLatest'] !== null) {
            $conCart['deliveries'][0]['shippingDateLatest'] = $shippingData['paazlDeliveryDateLatest'];
        }

        $conCart['customFields'] = array_merge($conCart['customFields'] ?? [], $shippingData);

        if (array_key_exists('paazlData', $conCart['customFields'])) {
            if (array_key_exists('deliveryType', $conCart['customFields']['paazlData'])) {
                if ($conCart['customFields']['paazlData']['deliveryType'] == 'PICKUP_LOCATION') {
                    $activeBillingAddress = $event->getSalesChannelContext()->getCustomer()->getActiveBillingAddress();
                    if ($activeBillingAddress === null) {
                        throw new AddressNotFoundException('');
                    }
                    $billingAddress = AddressTransformer::transform($activeBillingAddress);
                    $conCart['addresses'] = [$billingAddress];
                    $conCart['billingAddressId'] = $billingAddress['id'];
                }
            }
        }
        $event->setConvertedCart($conCart);
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if (!$event->getOrder()->getDeliveries()
            || !$event->getOrder()->getDeliveries()->first()
            || !$event->getOrder()->getDeliveries()->first()->getShippingMethod()) {
            return;
        }

        $activeShippingMethod = $event->getOrder()->getDeliveries()->first()->getShippingMethod()->getName();
        if ($activeShippingMethod !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }
        $paidOrderConfig = $this->systemConfigService->get('PaazlCheckoutWidget.config.paidOrder', $event->getSalesChannelId());

        if ($paidOrderConfig == 'no') {
            $this->paazlClient->createPaazlOrder($event);
        } else {
            if ($event->getOrder()->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_PAID) {
                $this->paazlClient->createPaazlOrder($event);
            }
        }
    }

    public function getCountryId(string $code, Context $context): ?string
    {
        return $this->countryRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('iso', $code)),
            $context
        )->firstId();
    }
}
