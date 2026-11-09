<?php

namespace Tests\Feature\Web;

use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceiptWebRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitKerjaSeeder::class);

        $unit = UnitKerja::first();
        $this->user = User::factory()->create([
            'unit_kerja_id' => $unit->id,
            'role' => 'OPERATOR',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_receipts_index_and_is_redirected_to_login(): void
    {
        $response = $this->get('/receipts');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_receipts_index_with_inertia_props(): void
    {
        $response = $this->actingAs($this->user)->get('/receipts');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Receipts/Index')
            ->has('receipts')
            ->has('tabCounts')
            ->has('filters')
            ->has('unitKerjaList')
        );
    }
}

