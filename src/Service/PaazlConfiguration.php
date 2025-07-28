<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

readonly class PaazlConfiguration
{
    public const PREFIX = 'PaazlCheckoutWidget.config.';

    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function getMode(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'environment',
            $salesChannelId
        );
    }

    public function debug(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'debugMode',
            $salesChannelId
        );
    }

    public function getApiKey(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'apiKey',
            $salesChannelId
        );
    }

    public function tokenReferenceLifetime(?string $salesChannelId): int
    {
        $lifetime = (int)($this->systemConfigService->get(
            self::PREFIX . 'tokenReferenceLifetime',
            $salesChannelId
        ) ?? 10);

        if ($lifetime < 0) {
            $lifetime = 0;
        }
        if ($lifetime > 30) {
            $lifetime = 30;
        }

        return $lifetime;
    }

    public function getInsuranceValue(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'insuranceValue',
            $salesChannelId
        );
    }

    public function getSecretKey(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'SecretKey',
            $salesChannelId
        );
    }

    public function getAvailableTabs(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'availableTabs',
            $salesChannelId
        );
    }

    public function getWidgetSectionToggle(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'headerTabType',
            $salesChannelId
        );
    }

    public function getDefaultTab(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'defaultTab',
            $salesChannelId
        );
    }

    public function getWidgetTheme(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'style',
            $salesChannelId
        );
    }

    public function getNominatedDateEnabled(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'nominatedDateEnabled',
            $salesChannelId
        );
    }

    public function getDefaultCountry(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'country',
            $salesChannelId
        );
    }

    public function getPrice(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'Price',
            $salesChannelId
        );
    }

    public function getDefaultPostcode(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'postalCode',
            $salesChannelId
        );
    }

    public function getShippingOptionsLimit(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'shippingOptionsLimit',
            $salesChannelId
        );
    }

    public function getPickupLocationsPageLimit(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'pickupLocationsPageLimit',
            $salesChannelId
        );
    }

    public function getPickupLocationsLimit(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'pickupLocationsLimit',
            $salesChannelId
        );
    }

    public function getInitialPickupLocations(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'initialPickupLocations',
            $salesChannelId
        );
    }

    public function getFreeShipping(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'freeShipping',
            $salesChannelId
        );
    }

    public function getStartMatrix(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'startMatrix',
            $salesChannelId
        );
    }

    public function getHouseNumberDefaultOption(?string $salesChannelId): int
    {
        return $this->systemConfigService->getInt(
            self::PREFIX . 'housenumberDefaultValue',
            $salesChannelId
        );
    }

    public function getNumberOfDays(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'numberOfDays',
            $salesChannelId
        );
    }

    public function getIsPricingEnabled(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'isPricingEnabled',
            $salesChannelId
        );
    }

    public function getIsShowAsExtraCost(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'isShowAsExtraCost',
            $salesChannelId
        );
    }

    public function getDeliveryRangeFormat(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'deliveryRangeFormat',
            $salesChannelId
        );
    }

    public function getOrderBy(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'orderBy',
            $salesChannelId
        );
    }

    public function getSortOrder(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'sortOrder',
            $salesChannelId
        );
    }


    public function getDeliveryOptionDateFormat(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'deliveryOptionDateFormat',
            $salesChannelId
        );
    }

    public function getDeliveryEstimateDateFormat(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'deliveryEstimateDateFormat',
            $salesChannelId
        );
    }

    public function getPickupOptionDateFormat(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'pickupOptionDateFormat',
            $salesChannelId
        );
    }

    public function getPickupEstimateDateFormat(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'pickupEstimateDateFormat',
            $salesChannelId
        );
    }

    public function getReferencePrefix(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'referencePrefix',
            $salesChannelId
        );
    }

    public function getOrderExport(?string $salesChannelId)
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'orderExport',
            $salesChannelId
        );
    }

    public function getHouseNumberFirstStreet(?string $salesChannelId): ?string
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'houseNumberFirstStreet',
            $salesChannelId
        );
    }

    public function getHouseNumberSecondStreet(?string $salesChannelId): ?string
    {
        return $this->systemConfigService->get(
            self::PREFIX . 'houseNumberSecondStreet',
            $salesChannelId
        );
    }
}
