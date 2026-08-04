<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Repositories\EventRepository;
use HPucca\Platform\Repositories\IntegrationSourceRepository;
use HPucca\Platform\Services\ApiKeyService;
use HPucca\Platform\Services\EventIngestionService;
use HPucca\Platform\Services\PublicCodeGenerator;
use Throwable;

final readonly class EventWebhookController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function store(Request $request): Response
    {
        if ($request->contentType() !== 'application/json') {
            return Response::json(['status' => 'unsupported_media_type', 'message' => 'Use Content-Type application/json.'], 415);
        }

        try {
            $connection = $this->database->connection();
            $result = (new EventIngestionService(
                new IntegrationSourceRepository($connection),
                new EventRepository($connection),
                new PublicCodeGenerator($connection),
                new ApiKeyService(),
            ))->ingest($request->header('X-API-Key'), $request->rawBody());

            return Response::json($result['body'], $result['httpStatus']);
        } catch (Throwable) {
            $debug = Config::get('app.debug') === 'true';

            return Response::json([
                'status' => 'error',
                'message' => $debug ? 'Erro interno ao receber evento.' : 'Erro interno.',
            ], 500);
        }
    }
}
