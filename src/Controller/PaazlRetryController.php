<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Controller;

use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use PaazlCheckoutWidget\Model\Transformer\CurrentOrderPayloadTransformer;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\Logger\PaazlLogger;
use PaazlCheckoutWidget\Service\PaazlConfiguration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class PaazlRetryController extends AbstractController
{
    private PaazlClient $paazlClient;

    private PaazlLogger $paazlLogger;

    private EntityRepositoryInterface $orderRepository;

    private PaazlConfiguration $paazlConfiguration;

    private CurrentOrderPayloadTransformer $currentOrderPayloadTransformer;

    public function __construct(
        PaazlClient $paazlClient,
        PaazlLogger $paazlLogger,
        EntityRepositoryInterface $orderRepository,
        PaazlConfiguration $paazlConfiguration,
        CurrentOrderPayloadTransformer $currentOrderPayloadTransformer
    ) {
        $this->paazlClient = $paazlClient;
        $this->paazlLogger = $paazlLogger;
        $this->orderRepository = $orderRepository;
        $this->paazlConfiguration = $paazlConfiguration;
        $this->currentOrderPayloadTransformer = $currentOrderPayloadTransformer;
    }

    /**
     * @Route("/api/paazl/updatePaazlData", name="api.action.paazl.updatepaazldata", methods={"POST"}, defaults={"_routeScope"={"api"}})
     */
    public function updatePaazlData(
        Request $request,
        Context $context
    ): JsonResponse {
        try {
            $currentOrder = json_decode(
                $request->get('currentOrder'),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            return new JsonResponse([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $orderData = $this->currentOrderPayloadTransformer->transform(
                $currentOrder,
                $context
            );

            $response = $this->paazlClient->order(
                $orderData,
                $currentOrder['salesChannelId']
            );
        } catch (GuzzleException $e) {
            if ($this->paazlConfiguration->debug(
                $currentOrder['salesChannelId']
            )
            ) {
                $this->paazlLogger->addEntry(
                    'Paazl apiToken guzzleError',
                    $context,
                    $e,
                    ['message' => $e->getMessage()],
                    Logger::WARNING
                );
            }

            $this->orderRepository->upsert([
                [
                    'id' => $currentOrder['id'],
                    'customFields' => ['PaazlPost' => 'Unsuccessful'],
                ],
            ], $context);

            return new JsonResponse([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
        if ($response->getStatusCode() !== 200) {
            return new JsonResponse([
                'type' => 'error',
                'message' => 'API error',
            ]);
        }

        $date = date("Y-m-d h:i:sa");
        $this->orderRepository->upsert([
            [
                'id' => $currentOrder['id'],
                'customFields' => [
                    'PaazlPost' => 'Successfully Updated at ' . $date,
                ],
            ],
        ], $context);

        if (empty($response->getBody()->getContents())) {
            return new JsonResponse([
                'type' => 'success',
                'message' => 'API Data Post SuccessFully',
            ]);
        }

        return new JsonResponse([
            'type' => 'success',
            'message' => 'Success',
        ]);
    }
}
