<?php

namespace Tests;

use App\Services\QuestionReviewLedger;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\ReviewLedgerFixture;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Application tests exercise approved content using synthetic editorial
        // records. Real content approval is checked outside tests by
        // `content:review-audit`; it must never use this fixture.
        $this->app->bind(QuestionReviewLedger::class,
            fn () => new QuestionReviewLedger(ReviewLedgerFixture::records()));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
