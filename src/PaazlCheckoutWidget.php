<?php declare(strict_types=1);

namespace PaazlCheckoutWidget;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PaazlCheckoutWidget\Service\Utility\ShippingMethodHandler;

class PaazlCheckoutWidget extends Plugin
{
    public const SHIPPING_METHOD_NAME = 'Paazl';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->getShippingMethodHandler()->addShippingMethod($installContext->getContext());
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

        /**
         * @var Connection $connection
         */
        $connection = $this->container->get(Connection::class);
        try {
            $connection->executeStatement(
                'DELETE FROM system_config WHERE configuration_key LIKE :domain',
                [
                    'domain' => '%PaazlCheckoutWidget.config%',
                ]
            );
        } catch (Exception $e) {
        }

        $this->getShippingMethodHandler()->deleteShippingMethod($uninstallContext->getContext());
    }

    public function getShippingMethodHandler(): ShippingMethodHandler
    {
        /** @var EntityRepositoryInterface $shippingMethodRepository */
        $shippingMethodRepository = $this->container->get('shipping_method.repository');
        /** @var EntityRepositoryInterface $rulesRepository */
        $rulesRepository = $this->container->get('rule.repository');
        /** @var EntityRepositoryInterface $deliveryTimeRepository */
        $deliveryTimeRepository = $this->container->get('delivery_time.repository');

        return new ShippingMethodHandler(
            $shippingMethodRepository,
            $rulesRepository,
            $deliveryTimeRepository
        );
    }
}
