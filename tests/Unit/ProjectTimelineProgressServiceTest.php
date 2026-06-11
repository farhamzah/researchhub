<?php

namespace Tests\Unit;

use App\Models\ProjectMilestone;
use App\Models\ProjectTimelineTask;
use App\Modules\Projects\Services\ProjectTimelineProgressService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ProjectTimelineProgressServiceTest extends TestCase
{
    public function test_weighted_progress_excludes_cancelled_tasks_and_clamps_values(): void
    {
        $service = new ProjectTimelineProgressService;

        $tasks = new Collection([
            new ProjectTimelineTask([
                'status' => ProjectMilestone::STATUS_COMPLETED,
                'progress_percentage' => 10,
                'weight' => 2,
            ]),
            new ProjectTimelineTask([
                'status' => ProjectMilestone::STATUS_IN_PROGRESS,
                'progress_percentage' => 50,
                'weight' => 1,
            ]),
            new ProjectTimelineTask([
                'status' => ProjectMilestone::STATUS_CANCELLED,
                'progress_percentage' => 100,
                'weight' => 10,
            ]),
        ]);

        $this->assertSame(83, $service->weightedProgress($tasks));
        $this->assertSame(100, $service->taskProgress($tasks[0]));
        $this->assertSame(0, $service->taskProgress($tasks[2]));
        $this->assertSame(100, $service->clamp(150));
        $this->assertSame(0, $service->clamp(-25));
    }
}
