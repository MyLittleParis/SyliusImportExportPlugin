<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Controller;

use FriendsOfSylius\SyliusImportExportPlugin\Exporter\ExporterRegistry;
use FriendsOfSylius\SyliusImportExportPlugin\Exporter\ResourceExporterInterface;
use FriendsOfSylius\SyliusImportExportPlugin\Form\ExportType;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


final class ExportDataController extends Controller
{
    /**
     * @var ServiceRegistryInterface
     */
    private $registry;

    /**
     * @param ServiceRegistryInterface $registry
     */
    public function __construct(
        ServiceRegistryInterface $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * @param Request $request
     * @param string $resource
     * @param string $format
     * @return Response
     */
    public function exportAction(Request $request, string $resource, string $format): Response
    {
        $filename = sprintf('%s-%s.%s', $resource, date('Y-m-d'), $format); // @todo Create a service for this

        return $this->exportData($resource, $format, $filename);
    }

    /**
     * @param string $exporter
     * @param string $format
     * @param string $filename
     * @return Response
     */
    private function exportData(string $exporter, string $format, string $filename): Response
    {
        $name = ExporterRegistry::buildServiceName($exporter, $format);
        if (!$this->registry->has($name)) {
            $message = sprintf("No exporter found of type '%s' for format '%s'", $exporter, $format);
            $this->session->getFlashBag()->add('error', $message);
        }

        /** @var ResourceExporterInterface $service */
        $service = $this->registry->get($name);

        /** @var \Sylius\Component\Resource\Repository\RepositoryInterface $repository */
        $repository = $this->get('sylius.repository.' . $exporter);

        $service->setExportFile($filename);
        $allItems = $repository->findAll();
        $idsToExport = [];
        foreach ($allItems as $item) {
            /** @var ResourceInterface $item */
            $idsToExport[] = $item->getId();
        }
        $service->export($idsToExport);

        $exportedData = $service->getExportedData($filename);

        $response = new Response($exportedData);

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
