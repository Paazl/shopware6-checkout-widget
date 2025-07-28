<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Service\Utility;

use PaazlCheckoutWidget\PaazlCheckoutWidget;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

readonly class ShippingMethodHandler
{
    public function __construct(
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $deliveryTimeRepository,
        private EntityRepository $ruleRepository
    ) {
    }

    public function createShippingMethod(Context $context): void
    {
        $this->shippingMethodRepository->upsert([
            $this->createDataPayload($context),
        ], $context);
    }

    public function updateShippingMethod($active, $context): void
    {
        $id = $this->getExistingShippingMethodId($context);

        if (!$id) {
            return;
        }

        $this->shippingMethodRepository->update([
            [
                'id' => $id,
                'active' => $active,
            ],
        ], $context);
    }

    public function deleteShippingMethod($context): void
    {
        $id = $this->getExistingShippingMethodId($context);
        if (!$id) {
            return;
        }

        $this->shippingMethodRepository->delete([
            [
                'id' => $id,
            ],
        ], $context);
    }

    private function createDataPayload(Context $context): array
    {
        $id = $this->getExistingShippingMethodId($context);

        $data = [
            'id' => $id,
            'translations' => [
                'en-GB' => [
                    'customFields' => [
                        'paazlName' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME,
                    ],
                ],
                'nl-NL' => [
                    'customFields' => [
                        'paazlName' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME,
                    ],
                ],
                'de-DE' => [
                    'customFields' => [
                        'paazlName' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME,
                    ],
                ],
            ],
            'technicalName' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME,
        ];

        if (!$id) {
            $deliveryTimeEntityId = $this->deliveryTimeRepository->searchIds(
                (new Criteria())->setLimit(1),
                $context
            )->firstId();

            if ($deliveryTimeEntityId === null) {
                $deliveryTimeEntityId = $this->addDeliveryTime($context);
            }

            $availabilityId = $this->ruleRepository->searchIds(
                (new Criteria())->setLimit(1),
                $context
            )->firstId();

            if ($availabilityId === null) {
                $availabilityId = $this->addAvailability($context);
            }

            $data['id'] = Uuid::randomHex();
            $data['active'] = false;
            $data['name'] = PaazlCheckoutWidget::SHIPPING_METHOD_NAME;
            $data['deliveryTimeId'] = $deliveryTimeEntityId;
            $data['availabilityRuleId'] = $availabilityId;
            $data['prices'] = [
                [
                    'price' => 0,
                    'currencyId' => Defaults::CURRENCY,
                    'calculation' => 1,
                    'quantityStart' => 0,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 0,
                            'gross' => 0,
                            'linked' => false,
                        ],
                    ],
                ],
            ];
        }

        return $data;
    }

    private function addDeliveryTime(Context $context): string
    {
        $deliveryTimeId = Uuid::randomHex();

        $this->deliveryTimeRepository->upsert([
            [
                'id' => $deliveryTimeId,
                'name' => '2 to 5 days',
                'min' => 2,
                'max' => 5,
                'unit' => 'day',
            ],
        ], $context);

        return $deliveryTimeId;
    }

    private function addAvailability(Context $context)
    {
        $availabilityId = Uuid::randomHex();

        $this->ruleRepository->upsert([
            [
                'id' => $availabilityId,
                'name' => 'All customers',
                'priority' => 1,
                'invalid' => 0,
                'conditions' => [
                    [
                        'type' => 'customerCustomerGroup',
                        'value' => json_encode([
                            'operator' => '=',
                            'customerGroupIds' => ['cfbd5018d38d41d8adca10d94fc8bdd6'],
                        ]),
                    ],
                ],
            ],
        ], $context);

        return $availabilityId;
    }

    private function getExistingShippingMethodId($context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customFields.paazlName', PaazlCheckoutWidget::SHIPPING_METHOD_NAME));

        $id = $this->shippingMethodRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('name', PaazlCheckoutWidget::SHIPPING_METHOD_NAME));

            $id = $this->shippingMethodRepository->searchIds($criteria, $context)->firstId();
        }

        return $id;
    }
}
