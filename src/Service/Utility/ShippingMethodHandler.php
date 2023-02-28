<?php

namespace PaazlCheckoutWidget\Service\Utility;

use PaazlCheckoutWidget\PaazlCheckoutWidget;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class ShippingMethodHandler
{
    private EntityRepositoryInterface $shippingMethodRepository;

    private EntityRepositoryInterface $rulesRepository;

    private EntityRepositoryInterface $deliveryTimeRepository;

    public function __construct(
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $rulesRepository,
        EntityRepositoryInterface $deliveryTimeRepository
    ) {
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->rulesRepository = $rulesRepository;
        $this->deliveryTimeRepository = $deliveryTimeRepository;
    }

    public function addShippingMethod(Context $context): void
    {
        $this->shippingMethodRepository->upsert([
            $this->createDataPayload($context)
        ], $context);
    }

    private function addRule(Context $context): string
    {
        $ruleIdInsert = Uuid::randomHex();
        $data = [
            'id' => $ruleIdInsert,
            'name' => 'All customers',
            'priority' => 1,
            'invalid' => false
        ];
        $this->rulesRepository->upsert([$data], $context);
        return $ruleIdInsert;
    }

    private function addDeliveryTime(Context $context): string
    {
        $deliveryIdInsert = Uuid::randomHex();

        $this->deliveryTimeRepository->upsert([
            [
                'id' => $deliveryIdInsert,
                'name' => 'All customers',
                'min' => 2,
                'max' => 5,
                'unit' => 'day'
            ]
        ], $context);

        return $deliveryIdInsert;
    }

    public function createDataPayload($context): array
    {
        $ruleId = $this->rulesRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('name', 'All customers')),
            $context
        )->firstId();

        if (!$ruleId) {
            $ruleId = $this->rulesRepository->searchIds(
                (new Criteria())->setLimit(1),
                $context
            )->firstId();
        }

        if ($ruleId === null) {
            $ruleId = $this->addRule($context);
        }

        $deliveryTimeEntityId = $this->deliveryTimeRepository->searchIds(
            (new Criteria())->setLimit(1),
            $context
        )->firstId();

        if ($deliveryTimeEntityId === null) {
            $deliveryTimeEntityId = $this->addDeliveryTime($context);
        }

        $id = $this->getExistingShippingMethodId($context);

        return [
            'id' => $id ?? Uuid::randomHex(),
            'translations' => [
                'en-GB' => [
                    'name' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME
                ],
                'nl-NL' => [
                    'name' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME
                ],
                'de-DE' => [
                    'name' => PaazlCheckoutWidget::SHIPPING_METHOD_NAME
                ]
            ],
            'active' => false,
            'availabilityRuleId' => $ruleId,
            'deliveryTimeId' => $deliveryTimeEntityId,
            'prices' => [
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
            ],
        ];
    }

    private function getExistingShippingMethodId($context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', PaazlCheckoutWidget::SHIPPING_METHOD_NAME));

        return $this->shippingMethodRepository->searchIds($criteria, $context)->firstId();
    }

    public function updateShippingMethod($active, $context): void
    {
        $id = $this->getExistingShippingMethodId($context);

        $this->shippingMethodRepository->upsert([
            [
                'id' => $id,
                'active' => $active,
            ]
        ], $context);
    }

    public function deleteShippingMethod($context): void
    {
        $this->shippingMethodRepository->delete([
            [
                'id' => $this->getExistingShippingMethodId($context)
            ]
        ], $context);
    }
}
