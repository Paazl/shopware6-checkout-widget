<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Controller;

use PaazlCheckoutWidget\Service\OrderExportService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(defaults: ['auth_required' => true, '_routeScope' => ['api']])]
class PaazlRetryController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderExportService $orderExportService,
        private readonly RouterInterface $router,
        private readonly SystemConfigService $systemConfigService,
        private readonly SalesChannelContextRestorer $salesChannelContextRestorer
    ) {
    }

    #[Route(path: "/api/paazl/updatePaazlData", name: "api.action.paazl.updatepaazldata", methods: ["POST"])]
    public function updatePaazlData(
        RequestDataBag $dataBag,
        Context $context
    ): JsonResponse {
        $orderId = $dataBag->get('id');

        $criteria = new Criteria([$orderId]);
        $criteria->setLimit(1);
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            throw new EntityNotFoundException('order', $orderId);
        }

        $salesChannelContext = $this->salesChannelContextRestorer->restoreByOrder($order->getId(), $context);

        $export = $this->orderExportService->export($order, $salesChannelContext);

        return new JsonResponse($export);
    }

    #[Route(path: "/api/paazl/generateManualUrlForOrder", name: "api.action.paazl.generateManualUrlForOrder", methods: ["POST"])]
    public function generateManualUrlForOrder(RequestDataBag $dataBag, Context $context): JsonResponse
    {
        $orderId = $dataBag->get('id');

        $criteria = new Criteria([$orderId]);
        $criteria->setLimit(1);
        $criteria->addAssociation('salesChannel.domains');
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order instanceof OrderEntity) {
            throw new EntityNotFoundException('order', $orderId);
        }

        $key = $this->systemConfigService->get('PaazlCheckoutWidget.config.manualOrderRetrySecretKey', $order->getSalesChannelId());

        $url = $this->router->generate('frontend.paazl.retry-order-export-admin', ['id' => $order->getId(), 'key' => $key]);

        $domains = $order->getSalesChannel()?->getDomains();
        if (!$domains) {
            throw new EntityNotFoundException('order', $orderId);
        }
        $languageDomain = $domains->first();
        foreach ($domains as $domain) {
            if ($domain->getLanguageId() === $order->getLanguageId()) {
                $languageDomain = $domain;
            }
        }

        $url = $languageDomain->getUrl() . '/' . $url;

        return new JsonResponse([
            'url' => $url,
        ]);
    }
}
