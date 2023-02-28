<?php
namespace PaazlCheckoutWidget\Controller;

use PaazlCheckoutWidget\RestApi\PaazlClient;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ApiController extends AbstractController
{
    private PaazlClient $paazlClient;

    public function __construct(
        paazlClient $paazlClient
    ) {
        $this->paazlClient = $paazlClient;
    }

    /**
     * @Route("/api/paazl-checkout/validate-sandbox-api",
     *     name="paazl-checkout.api.validate-sandbox-api",
     *     methods={"POST"},
     *     defaults={"auth_required"=true, "_routeScope"={"api"}}
     * )
     */
    public function validateSandboxApi(Context $context): JsonResponse
    {
        $token = $this->paazlClient->getApiTokenValidate('', $context, 'back');
        return new JsonResponse($token);
    }
}
