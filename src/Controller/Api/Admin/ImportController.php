<?php

namespace App\Controller\Api\Admin;

use App\Import\ImportException;
use App\Import\StrainImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Spreadsheet upload - the way to load the original WEED.xlsx on hosting
 * with no console access. Send dryRun=1 first to preview the report.
 */
class ImportController extends AbstractController
{
    #[Route('/api/admin/import', name: 'api_admin_import', methods: ['POST'])]
    public function import(Request $request, StrainImporter $importer): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(['error' => 'Choose an .xlsx or .csv file to upload.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $report = $importer->import($file->getPathname(), $file->getClientOriginalName(), $request->request->getBoolean('dryRun'));
        } catch (ImportException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($report);
    }
}
