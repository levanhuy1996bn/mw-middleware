<?php

namespace App\Controller;

use Endertech\EcommerceMiddleware\Contracts\Connector\WithMetafieldsInterface;
use Endertech\EcommerceMiddleware\Contracts\Store\DoctrineAwareInterface;
use Endertech\EcommerceMiddleware\Contracts\Variant\DataMiddlewareInterface as VariantDataMiddlewareInterface;
use Endertech\EcommerceMiddleware\Core\Traits\Connector\GraphQLFormatTrait;
use Endertech\EcommerceMiddleware\Core\Traits\DataMiddlewareAwareTrait;
use Endertech\EcommerceMiddlewareReport\Contracts\Model\ReportMetafieldInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Repository\ReportMetafieldRepositoryInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldOwnerInterface;
use Endertech\EcommerceMiddlewareReport\Contracts\Store\ReportMetafieldStoreInterface;
use Endertech\EcommerceMiddlewareReport\Metafield\Data\Variant\ReportMetafieldDataMiddlewareDecorator as VariantReportMetafieldDataMiddlewareDecorator;
use Endertech\EcommerceMiddlewareReport\Metafield\Traits\Store\ReportMetafieldStoreAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontendMetafieldApiController extends AbstractController
{
    use DataMiddlewareAwareTrait;
    use ReportMetafieldStoreAwareTrait;
    use GraphQLFormatTrait;

    const FRONTEND_METAFIELDS_AUTH_TOKEN = 'E8F65A32ADA5E4F8E4FE0F56D4CD6296AA85DDBCD703B84A3D32111E320996D80DF31328E65B3CD84A3915A16E769239C56F4523FBFA16249DA9FC0797E8117E';

    #[Route('/api/metafields', name: 'frontend_metafield_get_list')]
    public function frontendMetafieldGetList(Request $request, VariantDataMiddlewareInterface $dataMiddleware, ReportMetafieldStoreInterface $reportMetafieldStore)
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->json([], Response::HTTP_METHOD_NOT_ALLOWED);
        }
        if (!$request->headers->get('Authorization') || 'Bearer '.self::FRONTEND_METAFIELDS_AUTH_TOKEN !== $request->headers->get('Authorization')) {
            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $this->setDataMiddleware($dataMiddleware);
        $this->setReportMetafieldStore($reportMetafieldStore);
        $reportMetafieldDataMiddleware = $this->getReportMetafieldDataMiddleware();

        $status = Response::HTTP_OK;
        $headers = [];

        $response = [];

        try {
            $payload = $request->getPayload()->all();
            $filter = $this->getFilterFromPayload($payload);
            $metafields = [];

            if ($this->isUseLocalCache($payload)) {
                $reportMetafields = $this->getReportMetafieldsFromPayload($filter, $this->getReportMetafieldStore());
                $metafields = $this->getFormattedReportMetafields($reportMetafields);
            }

            if (!$metafields || $this->isForceLatest($payload)) {
                $latestMetafields = $this->getLatestMetafieldsFromPayload($filter, $reportMetafieldDataMiddleware, $this->getReportMetafieldStore());
                $metafields = $this->getFormattedLatestMetafields($latestMetafields);
            }

            $response['metafields'] = $metafields;
        } catch (\Exception $e) {
            $status = Response::HTTP_BAD_REQUEST;
            $response = [];
            $response['error'] = $e->getMessage();
        }

        return $this->json($response, $status, $headers);
    }

    private function getReportMetafieldDataMiddleware()
    {
        $dataMiddleware = $this->getDataMiddleware();

        if ($this->getReportMetafieldStore() instanceof DoctrineAwareInterface
            && $dataMiddleware instanceof DoctrineAwareInterface
            && $dataMiddleware->getObjectManager()
        ) {
            $this->getReportMetafieldStore()->setObjectManager($dataMiddleware->getObjectManager());
            $this->getReportMetafieldStore()->setDoctrine($dataMiddleware->getDoctrine());
        }

        if ($dataMiddleware instanceof VariantDataMiddlewareInterface) {
            $dataMiddleware = new VariantReportMetafieldDataMiddlewareDecorator($dataMiddleware);
            $dataMiddleware->setReportMetafieldStore($this->getReportMetafieldStore());
        }

        return $dataMiddleware;
    }

    private function getFilterFromPayload($payload)
    {
        $filter = [];
        if (isset($payload['metafield']) && isset($payload['metafield']['namespace'])) {
            $filter['namespace'] = $payload['metafield']['namespace'];
        }
        if (isset($payload['metafield']) && isset($payload['metafield']['key'])) {
            $filter['key'] = $payload['metafield']['key'];
        }
        if (isset($payload['metafield']) && isset($payload['metafield']['owner'])) {
            $filter['owner'] = $payload['metafield']['owner'];
        }
        if (isset($payload['metafield']) && isset($payload['metafield']['ownerId'])) {
            $filter['owner']['id'] = $payload['metafield']['ownerId'];
        }

        return $filter;
    }

    private function isUseLocalCache($payload)
    {
        if (array_key_exists('useLocalCache', $payload)
            && !in_array($payload['useLocalCache'], [true, 'true', 'True', 'TRUE', 1, '1', 'yes', 'Yes', 'YES', 'y', 'Y'], true)
        ) {
            return false;
        }

        return true;
    }

    private function isForceLatest($payload)
    {
        if (array_key_exists('forceLatest', $payload)
            && in_array($payload['forceLatest'], [true, 'true', 'True', 'TRUE', 1, '1', 'yes', 'Yes', 'YES', 'y', 'Y'], true)
        ) {
            return true;
        }

        return false;
    }

    private function getReportMetafieldsFromPayload($payload, ReportMetafieldStoreInterface $reportMetafieldStore)
    {
        $refl = new \ReflectionMethod($reportMetafieldStore, 'getReportMetafieldRepository');
        $refl->setAccessible(true);
        $reportMetafieldRepository = $refl->invokeArgs($reportMetafieldStore, [$payload, ['fallbackMetafieldData' => $payload]]);

        if (!$reportMetafieldRepository instanceof ReportMetafieldRepositoryInterface) {
            return [];
        }

        $criteria = $reportMetafieldStore->generateReportMetafieldCriteria($payload['namespace'] ?? null, $payload['key'] ?? null, null, ['fallbackMetafieldData' => $payload]);

        if (!$criteria) {
            return [];
        }

        return $reportMetafieldRepository->findBy($criteria);
    }

    private function getFormattedReportMetafields($reportMetafields = [])
    {
        $metafields = [];

        foreach ($reportMetafields as $reportMetafield) {
            if ($reportMetafield instanceof ReportMetafieldInterface) {
                $index = $reportMetafield->getDataSourceMetafieldNamespace().'.'.$reportMetafield->getDataSourceMetafieldKey();
                $metafields[$index] = $this->denormalizeMiddlewareIdInNodes($reportMetafield->getDataSourceMetafieldJson());
            }
        }

        return array_values($metafields);
    }

    private function getLatestMetafieldsFromPayload($payload, VariantDataMiddlewareInterface $dataMiddleware, ReportMetafieldStoreInterface $reportMetafieldStore)
    {
        if (!$payload || !$dataMiddleware->getDataSource()->getDataSourceApiConnector() instanceof WithMetafieldsInterface) {
            return [];
        }

        $ownerData = null;
        if ($reportMetafieldStore instanceof ReportMetafieldOwnerInterface) {
            $ownerData = $reportMetafieldStore->getReportMetafieldOwnerOptions($payload);
        }

        $metafields = $dataMiddleware->getDataSource()->getDataSourceApiConnector()->getMetafields($payload, $ownerData);

        if (is_array($metafields) || $metafields instanceof \Traversable) {
            foreach ($metafields as $metafield) {
                try {
                    $reportMetafieldStore->storeReportMetafield($metafield);
                } catch (\Exception $e) {
                    // do nothing
                }
            }
        }

        return $metafields;
    }

    private function getFormattedLatestMetafields($latestMetafields = [])
    {
        $metafields = [];

        foreach ($latestMetafields as $metafield) {
            $metafields[] = $this->denormalizeMiddlewareIdInNodes($metafield);
        }

        return $metafields;
    }
}
