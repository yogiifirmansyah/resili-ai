<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'ResiliAI API',
    description: 'API dokumentasi untuk sistem feedback resilient dengan penyimpanan MySQL utama dan failover Redis.',
)]
#[OA\Server(
    url: '/api',
    description: 'Base path API ResiliAI',
)]
class OpenApiSpec
{
}
