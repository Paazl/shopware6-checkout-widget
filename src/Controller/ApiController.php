<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Controller;

use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\PaazlConfiguration;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['auth_required' => true, '_routeScope' => ['api']])]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly PaazlClient $paazlClient,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    #[Route(
        path: '/api/paazl-checkout/validate-sandbox-api',
        name: 'paazl-checkout.api.validate-sandbox-api',
        options: ['seo' => false],
        methods: ['POST']
    )]
    public function validateSandboxApi(RequestDataBag $requestDataBag): JsonResponse
    {
        $oldKey = $this->systemConfigService->get(PaazlConfiguration::PREFIX . 'apiKey');
        $oldSecret = $this->systemConfigService->get(PaazlConfiguration::PREFIX . 'SecretKey');

        $this->systemConfigService->set(PaazlConfiguration::PREFIX . 'apiKey', $requestDataBag->get('apiKey'));
        $this->systemConfigService->set(PaazlConfiguration::PREFIX . 'SecretKey', $requestDataBag->get('secretKey'));

        $token = null;
        try {
            $token = $this->paazlClient->getApiToken(Uuid::randomHex(), null);
        } catch (ApiException $e) {
            $token = $e;
        }

        $this->systemConfigService->set(PaazlConfiguration::PREFIX . 'apiKey', $oldKey);
        $this->systemConfigService->set(PaazlConfiguration::PREFIX . 'SecretKey', $oldSecret);

        if ($token instanceof \Throwable) {
            throw $token;
        }

        return new JsonResponse($token);
    }
}
