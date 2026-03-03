<?php namespace App\Controller;

use App\Domain\LogBatchRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Application\LogIngestionService;

class LogIngestionController extends AbstractController
{
    public function __construct(
        private LogIngestionService $service,
    ) {
    }

    #[Route('/api/logs/ingest', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'status' => 'error',
                'errors' => ['Invalid JSON: ' . json_last_error_msg()],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $logRequest = new LogBatchRequest(
            logs: $payload['logs'] ?? []
        );

        $errors = $this->service->validate($logRequest);

        if ($errors !== []) {
            return $this->json([
                'status' => 'error',
                'errors' => $errors,
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $batchId = $this->service->dispatch($logRequest);

        return $this->json([
            'status' => 'accepted',
            'batch_id' => $batchId,
            'logs_count' => count($logRequest->logs),
        ], JsonResponse::HTTP_ACCEPTED);
    }
}