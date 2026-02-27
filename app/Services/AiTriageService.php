<?php
namespace App\Services;

use App\Exceptions\AiTriageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiTriageService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('referral.ai_triage_api_url');
        $this->apiKey  = config('referral.ai_triage_api_key');
        $this->timeout = config('referral.ai_triage_timeout', 30);
    }

    /**
     * Send diagnosis data to the AI triage API and return structured result.
     *
     * @throws AiTriageException
     */
    public function assess(array $payload): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/triage", $payload);

            if ($response->failed()) {
                throw new AiTriageException(
                    "AI triage API returned {$response->status()}: {$response->body()}"
                );
            }

            return $this->parseResponse($response->json());

        } catch (ConnectionException $e) {
            throw new AiTriageException("AI triage API connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    private function parseResponse(array $response): array
    {
        // Validate the response structure we expect from the AI API
        if (! isset($response['department'], $response['confidence_score'])) {
            throw new AiTriageException('AI triage API returned an unexpected response structure.');
        }

        return [
            'department'       => $response['department'],
            'confidence_score' => (float) $response['confidence_score'],
            'reasoning'        => $response['reasoning'] ?? null,
            'raw'              => $response,
        ];
    }
}
