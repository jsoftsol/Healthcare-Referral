<?php
use App\Actions\Referral\TriageReferralAction;
use App\Enums\ReferralStatus;
use App\Events\ReferralTriaged;
use App\Exceptions\AiTriageException;
use App\Models\Referral;
use App\Services\AiTriageService;
use Illuminate\Support\Facades\Event;

it('triages a referral using AI service and saves the result', function () {
    Event::fake();

    $referral = Referral::factory()->pending()->create();

    $mockAi = Mockery::mock(AiTriageService::class);
    $mockAi->shouldReceive('assess')
        ->once()
        ->andReturn([
            'department'       => 'cardiology',
            'confidence_score' => 0.92,
            'reasoning'        => 'ICD-10 I21 indicates acute myocardial infarction.',
        ]);

    $action = new TriageReferralAction($mockAi);
    $result = $action->execute($referral);

    expect($result->status)->toBe(ReferralStatus::Triaged)
        ->and($result->ai_suggested_department)->toBe('cardiology')
        ->and($result->ai_confidence_score)->toBe(0.92)
        ->and($result->ai_processed_at)->not->toBeNull()
        ->and($result->ai_input_payload)->not->toBeNull()
        ->and($result->ai_output_payload)->not->toBeNull();

    Event::assertDispatched(ReferralTriaged::class);
});

it('propagates AiTriageException when AI service fails', function () {
    $referral = Referral::factory()->pending()->create();

    $mockAi = Mockery::mock(AiTriageService::class);
    $mockAi->shouldReceive('assess')
        ->once()
        ->andThrow(new AiTriageException('AI API is unavailable'));

    $action = new TriageReferralAction($mockAi);

    expect(fn() => $action->execute($referral))
        ->toThrow(AiTriageException::class, 'AI API is unavailable');

    // Referral should remain in pending status
    expect($referral->fresh()->status)->toBe(ReferralStatus::Pending);
});
