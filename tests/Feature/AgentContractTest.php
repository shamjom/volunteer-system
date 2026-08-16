<?php

namespace Tests\Feature;

use App\Models\AgentConfirmation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Faq;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Support\ConfirmationFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentContractTest extends TestCase
{
    use RefreshDatabase;

    private User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->volunteer = User::create([
            'name'     => 'سلمى العتيبي',
            'email'    => 'salma@example.test',
            'password' => bcrypt('secret-pass'),
            'role'     => 'volunteer',
            'status'   => 'active',
        ]);

        VolunteerProfile::create([
            'user_id' => $this->volunteer->id,
            'phone'   => '0500000000',
        ]);

        Sanctum::actingAs($this->volunteer);
    }

    // ── مساعدات ───────────────────────────────────────────────────────

    private function event(array $overrides = []): Event
    {
        $admin = User::create([
            'name'     => 'إدارة',
            'email'    => 'admin' . uniqid() . '@example.test',
            'password' => bcrypt('secret-pass'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        return Event::create($overrides + [
            'title'          => 'حملة التبرعات الكبرى',
            'description'    => 'حملة سنوية',
            'location'       => 'الرياض',
            'start_date'     => now()->addDays(10),
            'end_date'       => now()->addDays(10)->addHours(4),
            'max_volunteers' => 2,
            'slots_taken'    => 0,
            'status'         => 'open',
            'created_by'     => $admin->id,
        ]);
    }

    /**
     * يستدعي أداة مُغيِّرة عبر المسار الحقيقي: يطلب تأكيداً ثم يمرّره في الترويسة.
     */
    private function callTool(string $tool, array $arguments = [])
    {
        $id = $this->postJson('/api/agent/confirmations', [
            'tool'      => $tool,
            'arguments' => $arguments,
        ])->assertCreated()->json('data.confirmation_id');

        return $this->withHeader('X-Confirmation-Id', $id)
            ->postJson("/api/agent/{$tool}", $arguments);
    }

    // ── الحالة الأولى: وضع التسجيل المباشر ────────────────────────────

    public function test_register_for_event_in_direct_mode_confirms_immediately(): void
    {
        config(['agent.registration_mode' => 'direct']);

        $event = $this->event();

        $response = $this->callTool('register_for_event', ['event_id' => $event->id]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('error', null)
            ->assertJsonPath('data.registration_status', 'approved')
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.event.slots_taken', 1)
            ->assertJsonPath('data.event.slots_available', 1);

        $this->assertSame(1, $event->fresh()->slots_taken);
    }

    // ── الحالة الثانية: وضع الطلب المعلّق ─────────────────────────────

    public function test_register_for_event_in_pending_mode_creates_a_pending_request(): void
    {
        config(['agent.registration_mode' => 'pending']);

        $event = $this->event();

        $response = $this->callTool('register_for_event', ['event_id' => $event->id]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.registration_status', 'pending')
            ->assertJsonPath('data.confirmed', false);

        // المقعد محجوز في الوضعين، فـ EVENT_FULL يبقى صادقاً
        $this->assertSame(1, $event->fresh()->slots_taken);
    }

    // ── القيود ────────────────────────────────────────────────────────

    public function test_double_registration_is_rejected(): void
    {
        $event = $this->event();

        $this->callTool('register_for_event', ['event_id' => $event->id])->assertCreated();

        $this->callTool('register_for_event', ['event_id' => $event->id])
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'ALREADY_REGISTERED');

        $this->assertSame(1, $event->fresh()->slots_taken);
    }

    public function test_registration_beyond_capacity_returns_event_full(): void
    {
        $event = $this->event(['max_volunteers' => 1]);

        $this->callTool('register_for_event', ['event_id' => $event->id])->assertCreated();

        // متطوع آخر على آخر مقعد
        $other = User::create([
            'name'     => 'نورة',
            'email'    => 'noura@example.test',
            'password' => bcrypt('secret-pass'),
            'role'     => 'volunteer',
            'status'   => 'active',
        ]);

        $this->volunteer = $other;
        Sanctum::actingAs($other);

        $this->callTool('register_for_event', ['event_id' => $event->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EVENT_FULL');

        $this->assertSame(1, $event->fresh()->slots_taken);
    }

    public function test_withdrawal_releases_the_seat(): void
    {
        $event = $this->event();

        $this->callTool('register_for_event', ['event_id' => $event->id])->assertCreated();
        $this->assertSame(1, $event->fresh()->slots_taken);

        $this->callTool('withdraw_from_event', [
            'event_id' => $event->id,
            'reason'   => 'ظرف طارئ',
        ])->assertOk()->assertJsonPath('data.registration_status', 'cancelled');

        $this->assertSame(0, $event->fresh()->slots_taken);
    }

    public function test_withdrawal_after_the_deadline_is_rejected(): void
    {
        $event = $this->event(['start_date' => now()->addHours(3)]);

        EventRegistration::create([
            'event_id'            => $event->id,
            'volunteer_id'        => $this->volunteer->id,
            'registration_status' => 'approved',
            'attendance_status'   => 'pending',
            'volunteer_hours'     => 0,
        ]);

        $this->callTool('withdraw_from_event', ['event_id' => $event->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEADLINE_PASSED');
    }

    // ── بوابة التأكيد ─────────────────────────────────────────────────

    public function test_mutating_tool_without_a_confirmation_id_is_refused(): void
    {
        $event = $this->event();

        $this->postJson('/api/agent/register_for_event', ['event_id' => $event->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $this->assertSame(0, $event->fresh()->slots_taken);
    }

    public function test_a_confirmation_issued_for_another_event_cannot_be_reused(): void
    {
        $wanted    = $this->event();
        $different = $this->event(['title' => 'فعالية أخرى']);

        $confirmation = AgentConfirmation::create([
            'user_id'     => $this->volunteer->id,
            'tool'        => 'register_for_event',
            'fingerprint' => ConfirmationFingerprint::make('register_for_event', ['event_id' => $wanted->id]),
            'expires_at'  => now()->addMinutes(10),
        ]);

        $this->withHeader('X-Confirmation-Id', $confirmation->id)
            ->postJson('/api/agent/register_for_event', ['event_id' => $different->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_a_confirmation_is_consumed_only_once(): void
    {
        $event     = $this->event();
        $arguments = ['event_id' => $event->id];

        $confirmation = AgentConfirmation::create([
            'user_id'     => $this->volunteer->id,
            'tool'        => 'register_for_event',
            'fingerprint' => ConfirmationFingerprint::make('register_for_event', $arguments),
            'expires_at'  => now()->addMinutes(10),
        ]);

        $this->withHeader('X-Confirmation-Id', $confirmation->id)
            ->postJson('/api/agent/register_for_event', $arguments)
            ->assertCreated();

        $this->withHeader('X-Confirmation-Id', $confirmation->id)
            ->postJson('/api/agent/register_for_event', $arguments)
            ->assertStatus(403);
    }

    // ── انتحال السلطة ─────────────────────────────────────────────────

    public function test_role_claims_in_the_request_body_are_ignored(): void
    {
        $this->postJson('/api/agent/get_my_profile', [
            'role'        => 'hr_admin',
            'user_id'     => 9999,
            'total_hours' => 500,
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'سلمى العتيبي')
            ->assertJsonPath('data.total_hours', 0);
    }

    public function test_update_my_profile_refuses_fields_outside_the_contract(): void
    {
        $this->callTool('update_my_profile', ['city' => 'جدة'])
            ->assertOk()
            ->assertJsonPath('data.profile.city', 'جدة');

        // الدور والحالة والساعات يحذفها الوسيط، فالطلب يبقى بلا حقول صالحة
        $this->callTool('update_my_profile', ['cv_status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame('not_received', $this->volunteer->fresh()->volunteerProfile->cv_status);
    }

    // ── المهام ────────────────────────────────────────────────────────

    public function test_update_task_status_enforces_ownership_and_translates_done(): void
    {
        $event = $this->event();

        $mine = Task::create([
            'event_id' => $event->id,
            'title'    => 'تجهيز الصناديق',
            'status'   => 'pending',
        ]);
        $mine->volunteers()->attach($this->volunteer->id);

        $notMine = Task::create([
            'event_id' => $event->id,
            'title'    => 'مهمة غيري',
            'status'   => 'pending',
        ]);

        $this->callTool('update_task_status', [
            'task_id'    => $mine->id,
            'new_status' => 'done',
        ])->assertOk()->assertJsonPath('data.task.status', 'done');

        // العقد يقول done، وقاعدة البيانات تبقى على completed
        $this->assertSame('completed', $mine->fresh()->status);

        $this->callTool('update_task_status', [
            'task_id'    => $notMine->id,
            'new_status' => 'done',
        ])->assertStatus(403)->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $this->assertSame('pending', $notMine->fresh()->status);
    }

    public function test_get_my_tasks_filters_by_contract_status(): void
    {
        $event = $this->event();

        $done = Task::create([
            'event_id' => $event->id,
            'title'    => 'مهمة منجزة',
            'status'   => 'completed',
        ]);
        $done->volunteers()->attach($this->volunteer->id);

        $this->postJson('/api/agent/get_my_tasks', ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.tasks.0.status', 'done');

        $this->postJson('/api/agent/get_my_tasks', ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    // ── المطابقة الضبابية العربية ─────────────────────────────────────

    public function test_find_event_matches_across_hamza_and_ta_marbuta(): void
    {
        $this->event();

        $response = $this->postJson('/api/agent/find_event', ['query' => 'حمله التبرعات'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertGreaterThanOrEqual(0.8, $response->json('data.candidates.0.match_score'));
        $this->assertSame('حملة التبرعات الكبرى', $response->json('data.candidates.0.title_ar'));
    }

    public function test_find_event_returns_not_found_for_nonsense(): void
    {
        $this->event();

        $this->postJson('/api/agent/find_event', ['query' => 'زيارة المصنع الفضائي'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── الفرق والطلبات والأسئلة ───────────────────────────────────────

    public function test_join_team_creates_a_pending_request_not_a_membership(): void
    {
        $team = Team::create(['name' => 'فريق الإغاثة', 'is_active' => true]);

        $this->callTool('request_join_team', [
            'team_id'    => $team->id,
            'motivation' => 'أرغب بالمشاركة',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertSame(0, $team->members()->count());

        $this->callTool('request_join_team', ['team_id' => $team->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_REGISTERED');
    }

    public function test_search_faq_finds_a_normalized_match(): void
    {
        Faq::create([
            'question'     => 'كيف أنضم إلى الجمعية كمتطوع؟',
            'answer'       => 'سجّل بياناتك في المنصة ثم تراجع الإدارة طلبك.',
            'keywords'     => 'انضمام تسجيل عضوية',
            'is_published' => true,
        ]);

        $this->postJson('/api/agent/search_faq', ['query' => 'كيف انضم للجمعيه'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.results.0.question_ar', 'كيف أنضم إلى الجمعية كمتطوع؟');
    }

    // ── الغلاف ────────────────────────────────────────────────────────

    public function test_every_response_carries_exactly_three_keys(): void
    {
        $ok = $this->postJson('/api/agent/list_upcoming_events')->assertOk();
        $this->assertSame(['ok', 'data', 'error'], array_keys($ok->json()));

        $failed = $this->postJson('/api/agent/find_event', ['query' => '']);
        $this->assertSame(['ok', 'data', 'error'], array_keys($failed->json()));
        $this->assertSame(['code', 'message_ar'], array_keys($failed->json('error')));
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $failed->json('error.message_ar'));
    }
}
