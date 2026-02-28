<?php
use App\Enums\ReferralStatus;

it('allows valid status transitions', function (ReferralStatus $from, ReferralStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'pending → triaged'          => [ReferralStatus::Pending, ReferralStatus::Triaged],
    'pending → cancelled'        => [ReferralStatus::Pending, ReferralStatus::Cancelled],
    'triaged → assigned'         => [ReferralStatus::Triaged, ReferralStatus::Assigned],
    'assigned → acknowledged'    => [ReferralStatus::Assigned, ReferralStatus::Acknowledged],
    'assigned → escalated'       => [ReferralStatus::Assigned, ReferralStatus::Escalated],
    'acknowledged → in_progress' => [ReferralStatus::Acknowledged, ReferralStatus::InProgress],
    'in_progress → completed'    => [ReferralStatus::InProgress, ReferralStatus::Completed],
]);

it('blocks invalid status transitions', function (ReferralStatus $from, ReferralStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'completed → cancelled' => [ReferralStatus::Completed, ReferralStatus::Cancelled],
    'cancelled → triaged'   => [ReferralStatus::Cancelled, ReferralStatus::Triaged],
    'pending → completed'   => [ReferralStatus::Pending, ReferralStatus::Completed],
    'completed → any'       => [ReferralStatus::Completed, ReferralStatus::Assigned],
]);

it('marks completed and cancelled as final statuses', function () {
    expect(ReferralStatus::Completed->isFinal())->toBeTrue();
    expect(ReferralStatus::Cancelled->isFinal())->toBeTrue();
    expect(ReferralStatus::Pending->isFinal())->toBeFalse();
    expect(ReferralStatus::Assigned->isFinal())->toBeFalse();
});
