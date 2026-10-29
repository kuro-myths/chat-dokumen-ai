<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Review\ThesisReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunThesisReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public Document $document,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    public function handle(ThesisReviewService $service): void
    {
        $service->review($this->document, $this->provider, $this->model);
    }

    public function failed(Throwable $e): void
    {
        $this->document->update([
            'status' => 'review_failed',
            'review_summary' => ['error' => $e->getMessage()],
        ]);
    }
}
