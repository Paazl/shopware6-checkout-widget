<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PaazlCheckoutWidget\Service\Utility\ShippingMethodHandler;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class PaazlCheckoutWidget extends Plugin
{
    public const SHIPPING_METHOD_NAME = 'Paazl';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->getShippingMethodHandler()->createShippingMethod($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->getShippingMethodHandler()->createShippingMethod($updateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        $this->getShippingMethodHandler()->updateShippingMethod(true, $activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);

        $this->getShippingMethodHandler()->updateShippingMethod(false, $deactivateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        try {
            $connection->executeStatement('DROP TABLE IF EXISTS `paazl_token`');

            $this->getShippingMethodHandler()->deleteShippingMethod($uninstallContext->getContext());
        } catch (Exception $e) {
        }
    }

    public function getShippingMethodHandler(): ShippingMethodHandler
    {
        return new ShippingMethodHandler(
            $this->container->get('shipping_method.repository'),
            $this->container->get('delivery_time.repository'),
            $this->container->get('rule.repository'),
        );
    }
}
