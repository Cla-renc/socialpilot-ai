<?php

namespace Tests\Feature;

use App\Jobs\RunContentGeneration;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentGenerationJob;
use App\Models\ContentRevision;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\ContentGenerationService;
use App\Services\ContentImprovementService;
use App\Services\ContentVariationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentGenerationDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. DATABASE CONSTRAINTS
    // =========================================================================

    public function test_two_drafts_with_different_generation_jobs_can_coexist(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $job1 = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'status' => 'completed',
        ]);

        $job2 = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'instagram',
            'status' => 'completed',
        ]);

        $draft1 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => $job1->id,
            'title' => 'Draft 1',
            'prompt' => 'Prompt 1',
            'body' => 'Body 1',
        ]);

        $draft2 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => $job2->id,
            'title' => 'Draft 2',
            'prompt' => 'Prompt 2',
            'body' => 'Body 2',
        ]);

        $this->assertDatabaseHas('brand_content_drafts', ['id' => $draft1->id, 'generation_job_id' => $job1->id]);
        $this->assertDatabaseHas('brand_content_drafts', ['id' => $draft2->id, 'generation_job_id' => $job2->id]);
    }

    public function test_multiple_manually_created_drafts_with_null_generation_job_id_are_permitted(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft1 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => null,
            'title' => 'Manual Draft 1',
            'prompt' => 'Manual prompt 1',
            'body' => 'Manual body 1',
        ]);

        $draft2 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => null,
            'title' => 'Manual Draft 2',
            'prompt' => 'Manual prompt 2',
            'body' => 'Manual body 2',
        ]);

        $draft3 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => null,
            'title' => 'Manual Draft 3',
            'prompt' => 'Manual prompt 3',
            'body' => 'Manual body 3',
        ]);

        $this->assertDatabaseCount('brand_content_drafts', 3);
        $this->assertNull($draft1->generation_job_id);
        $this->assertNull($draft2->generation_job_id);
        $this->assertNull($draft3->generation_job_id);
    }

    public function test_two_drafts_cannot_reference_same_non_null_generation_job_id(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'status' => 'completed',
        ]);

        BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => $job->id,
            'title' => 'First Draft',
            'prompt' => 'Initial',
            'body' => 'First body',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to insert a second draft with the same generation_job_id must violate unique constraint
        BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'generation_job_id' => $job->id,
            'title' => 'Duplicate Draft',
            'prompt' => 'Duplicate',
            'body' => 'Second body',
        ]);
    }

    public function test_draft_cannot_have_two_revisions_with_same_revision_number(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Test Draft',
            'prompt' => 'Prompt',
            'body' => 'Draft body',
        ]);

        ContentRevision::create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
            'title' => 'Rev 1',
            'body' => 'Rev 1 body',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to insert another revision with the same revision_number for the same draft must fail
        ContentRevision::create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'user_edit',
            'title' => 'Duplicate Rev 1',
            'body' => 'Duplicate body',
        ]);
    }

    public function test_different_drafts_can_both_have_revision_number_one(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft1 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Draft A',
            'prompt' => 'Prompt A',
            'body' => 'Body A',
        ]);

        $draft2 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Draft B',
            'prompt' => 'Prompt B',
            'body' => 'Body B',
        ]);

        $rev1 = ContentRevision::create([
            'draft_id' => $draft1->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
            'title' => 'Draft A Rev 1',
            'body' => 'Body A Rev 1',
        ]);

        $rev2 = ContentRevision::create([
            'draft_id' => $draft2->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
            'title' => 'Draft B Rev 1',
            'body' => 'Body B Rev 1',
        ]);

        $this->assertDatabaseHas('content_revisions', ['id' => $rev1->id, 'draft_id' => $draft1->id, 'revision_number' => 1]);
        $this->assertDatabaseHas('content_revisions', ['id' => $rev2->id, 'draft_id' => $draft2->id, 'revision_number' => 1]);
    }

    // =========================================================================
    // 2. TRANSACTIONAL PERSISTENCE
    // =========================================================================

    public function test_successful_generation_persists_draft_revision_variations_and_completed_job(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Apex Athletics',
            'industry' => 'Sports & Fitness',
            'niche' => 'Performance',
        ]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'content_format' => 'post',
            'objective' => 'thought_leadership',
            'category' => 'educational',
            'instructions' => 'Consistency builds champions',
            'provider' => 'internal',
            'status' => 'queued',
        ]);

        $service = app(ContentGenerationService::class);
        $draft = $service->generateForJob($job);

        // Verify draft was created and linked
        $this->assertNotNull($draft);
        $this->assertEquals($job->id, $draft->generation_job_id);
        $this->assertEquals($brand->id, $draft->brand_id);

        // Verify job state
        $freshJob = $job->fresh();
        $this->assertEquals('completed', $freshJob->status);
        $this->assertEquals(100, $freshJob->progress);
        $this->assertEquals($draft->id, $freshJob->result_draft_id);
        $this->assertNotNull($freshJob->completed_at);

        // Verify revision 1
        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draft->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
        ]);

        // Verify variations
        $this->assertGreaterThanOrEqual(1, $draft->variations()->count());
    }

    public function test_simulated_persistence_failure_rolls_back_all_related_database_changes(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Rollback Brand',
        ]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'content_format' => 'post',
            'provider' => 'internal',
            'status' => 'queued',
        ]);

        // Intercept ContentRevision::creating event to simulate a failure during persistence
        ContentRevision::creating(function () {
            throw new \RuntimeException('Simulated persistence failure during revision creation');
        });

        $service = app(ContentGenerationService::class);

        try {
            $service->generateForJob($job);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated persistence failure', $e->getMessage());
        }

        // Verify draft was completely rolled back
        $this->assertDatabaseMissing('brand_content_drafts', [
            'generation_job_id' => $job->id,
        ]);

        // Verify no revisions or variations were persisted
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('content_variations', 0);

        // Verify job was NOT marked as completed
        $freshJob = $job->fresh();
        $this->assertNotEquals('completed', $freshJob->status);
        $this->assertNull($freshJob->result_draft_id);
    }

    // =========================================================================
    // 3. IDEMPOTENT GENERATION & RETRY SAFETY
    // =========================================================================

    public function test_reprocessing_completed_job_does_not_create_another_draft(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'content_format' => 'post',
            'provider' => 'internal',
            'status' => 'queued',
        ]);

        $service = app(ContentGenerationService::class);

        // First execution
        $draftFirst = $service->generateForJob($job);
        $this->assertDatabaseCount('brand_content_drafts', 1);
        $initialRevisionCount = ContentRevision::count();
        $initialVariationCount = ContentVariation::count();

        // Second execution (simulating queue worker retry of completed job)
        $draftSecond = $service->generateForJob($job->fresh());

        // Must return the exact same draft without adding rows
        $this->assertEquals($draftFirst->id, $draftSecond->id);
        $this->assertDatabaseCount('brand_content_drafts', 1);
        $this->assertEquals($initialRevisionCount, ContentRevision::count());
        $this->assertEquals($initialVariationCount, ContentVariation::count());
    }

    public function test_queue_job_run_content_generation_is_retry_safe(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'content_format' => 'post',
            'provider' => 'internal',
            'status' => 'queued',
        ]);

        $service = app(ContentGenerationService::class);

        // First run via queue Job handler
        $queueJob = new RunContentGeneration($job->id);
        $queueJob->handle($service);

        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertDatabaseCount('brand_content_drafts', 1);

        // Second run via queue Job handler (simulating automated queue retry)
        $queueJobRetry = new RunContentGeneration($job->id);
        $queueJobRetry->handle($service);

        // Still exactly 1 draft, status remains completed
        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertDatabaseCount('brand_content_drafts', 1);
    }

    // =========================================================================
    // 4. REVISION INTEGRITY UNDER VARIATIONS & IMPROVEMENTS
    // =========================================================================

    public function test_revision_numbers_increment_safely_on_content_improvement(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Original Draft',
            'prompt' => 'Generate standard post',
            'body' => "Paragraph 1\n\nParagraph 2\n\nParagraph 3",
            'quality_score' => 80,
        ]);

        ContentRevision::create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
            'title' => $draft->title,
            'body' => $draft->body,
        ]);

        $improvementService = app(ContentImprovementService::class);

        // First improvement -> Revision 2
        $draftAfterRev2 = $improvementService->improve($draft, $user, 'shorten');
        $this->assertEquals(2, $draftAfterRev2->revisions()->max('revision_number'));

        // Second improvement -> Revision 3
        $draftAfterRev3 = $improvementService->improve($draftAfterRev2, $user, 'expand');
        $this->assertEquals(3, $draftAfterRev3->revisions()->max('revision_number'));

        // All 3 revisions exist and have unique revision_number per draft
        $revisionNumbers = $draft->revisions()->pluck('revision_number')->sort()->values()->all();
        $this->assertEquals([1, 2, 3], $revisionNumbers);
    }

    public function test_revision_numbers_increment_safely_on_variation_selection(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Original Draft',
            'prompt' => 'Generate standard post',
            'body' => 'Base body text',
            'quality_score' => 80,
        ]);

        ContentRevision::create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
            'title' => $draft->title,
            'body' => $draft->body,
        ]);

        $variation = ContentVariation::create([
            'draft_id' => $draft->id,
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'style' => 'bold',
            'title' => 'Bold Version',
            'body' => 'Bold body text with direct assertions.',
        ]);

        $variationService = app(ContentVariationService::class);
        $updatedDraft = $variationService->selectVariation($draft, $variation, $user);

        $this->assertEquals($variation->id, $updatedDraft->selected_variation_id);
        $this->assertEquals(2, $updatedDraft->revisions()->max('revision_number'));

        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draft->id,
            'revision_number' => 2,
            'action' => 'select_variation',
        ]);
    }
}
