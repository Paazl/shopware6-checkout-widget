<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Model\Transformer;

use Monolog\Logger;
use PaazlCheckoutWidget\Model\AddressParser;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\Logger\PaazlLogger;
use PaazlCheckoutWidget\Service\PaazlConfiguration;
use Shopware\Core\Framework\Context;

class CurrentOrderPayloadTransformer
{
    private PaazlConfiguration $paazlConfiguration;

    private PaazlLogger $paazlLogger;

    private AddressParser $addressParser;

    public function __construct(
        PaazlConfiguration $paazlConfiguration,
        PaazlLogger $paazlLogger,
        AddressParser $addressParser
    ) {
        $this->paazlConfiguration = $paazlConfiguration;
        $this->paazlLogger = $paazlLogger;
        $this->addressParser = $addressParser;
    }

    public function transform(array $order, Context $context): array
    {
        $extInformation = null;
        $preferredDeliveryDate = null;
        $identifier = null;
        $pickupCode = null;
        $pickupAccountNumber = null;

        $delivery = ($order['deliveries'] ? $order['deliveries'][0] : null);
        if (!$delivery) {
            return [];
        }

        if (!$shippingAddress = $delivery['shippingOrderAddress']) {
            return [];
        }

        if (!$orderCustomer = $order['orderCustomer']) {
            return [];
        }

        $orderCustomFields = $order['customFields'];

        if (array_key_exists('paazlData', $orderCustomFields)) {
            $extInformation = $orderCustomFields['paazlData'];

            if (array_key_exists('shippingOption', $extInformation)) {
                $shippingOption = $extInformation['shippingOption'];
                $preferredDeliveryDate
                    = $shippingOption['preferred_delivery_date'] ?? null;
                $identifier = $shippingOption['identifier'] ?? null;
            }

            $pickupCode = $extInformation['pickupLocation']['code'] ?? null;
            $pickupAccountNumber = $extInformation['pickup_account_number'] ??
                null;
        }

        $address = $this->addressParser->parseAddress(
            $shippingAddress['street'],
            $order['salesChannelId']
        );

        $houseNr = $this->paazlConfiguration->getHouseNumberDefaultOption(
            $order['salesChannelId']
        );
        $houseNr = $houseNr ? 0 : '';

        $result = [
            'additionalInstruction' => '',
            'consignee' => [
                'email' => $orderCustomer['email'],
                'name' => $shippingAddress['firstName'],
                'address' => [
                    'city' => $shippingAddress['city'],
                    'country' => $shippingAddress['country']['iso'],
                    'postalCode' => $shippingAddress['zipcode'],
                    'street' => $address['street'] ?? '',
                    'houseNumber' => $address['houseNumber'] ?? $houseNr,
                    'houseNumberExtension' => $address['houseNumberExtension']
                            ?? '',
                ],
            ],
            'customsValue' => [
                'currency' => $order['currency']['isoCode'],
                'value' => $order['amountTotal'],
            ],
            'codValue' => [
                'currency' => $order['currency']['isoCode'],
                'value' => $order['amountTotal'],
            ],
            'insuredValue' => [
                'currency' => $order['currency']['isoCode'],
                'value' => $this->paazlConfiguration->getInsuranceValue(
                    $order['salesChannelId']
                ),
            ],
            'requestedDeliveryDate' => $preferredDeliveryDate,
            'products' => [],
            'reference' => $order['orderNumber'],
            'invoiceNumber' => $order['orderNumber'],
            'shipping' => [
                'option' => $identifier,
            ],
        ];

        if (array_key_exists('company', $shippingAddress)) {
            $result['consignee']['companyName'] = $shippingAddress['company'];
        }

        if (array_key_exists('phoneNumber', $shippingAddress)) {
            $result['consignee']['phone'] = $shippingAddress['phoneNumber'];
        }

        if (array_key_exists('countryState', $shippingAddress)) {
            $result['consignee']['address']['province']
                = $shippingAddress['countryState'];
        }

        if (array_key_exists('deliveryType', $extInformation)
            && $extInformation['deliveryType'] === 'PICKUP_LOCATION'
        ) {
            $result['shipping']['pickupLocation'] = ['code' => $pickupCode];

            if ($pickupAccountNumber) {
                $result['shipping']['pickupLocation']['accountNumber']
                    = $pickupAccountNumber;
            }
        }

        if (empty($result['consignee']['address']['street'])) {
            $result['consignee']['address']['streetLines']
                = [$shippingAddress['street']];
            unset(
                $result['consignee']['address']['street'],
                $result['consignee']['address']['houseNumber'],
                $result['consignee']['address']['houseNumberExtension']
            );
        }

        if (array_key_exists('paazlProducts', $orderCustomFields)) {
            $result['weight'] = array_sum(
                $orderCustomFields['paazlProducts']['totalWeight']
            );
            $result['products']
                = $orderCustomFields['paazlProducts']['lineItem'];
        }

        if ($this->paazlConfiguration->debug($order['salesChannelId'])) {
            $this->paazlLogger->addEntry(
                'Paazl OrderApi Payload',
                $context,
                null,
                ['result' => $result],
                Logger::INFO
            );
        }

        return $result;
    }
}
