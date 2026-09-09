<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Memorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The registry's contract, including the parts that keep a stranger from
 * rewriting someone's memorial.
 */
class RegistryTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'slug' => $id,
            'fullName' => 'Zeynep Şahinoğlu',
            'profession' => 'Öğretmen',
            'birthPlace' => 'İzmir',
            'privacy' => 'public',
            'candleCount' => 0,
            'memories' => [],
        ], $overrides);
    }

    private function seeded(string $id = 'albert-einstein'): Memorial
    {
        $memorial = new Memorial(['id' => $id]);
        $memorial->id = $id;
        $memorial->syncFromDocument($this->document($id, ['fullName' => 'Albert Einstein']));
        $memorial->is_seed = true;
        $memorial->save();

        return $memorial;
    }

    public function test_it_lists_only_public_dossiers(): void
    {
        $this->seeded('public-one');

        $hidden = new Memorial(['id' => 'hidden-one']);
        $hidden->id = 'hidden-one';
        $hidden->syncFromDocument($this->document('hidden-one', ['privacy' => 'family_only']));
        $hidden->save();

        $response = $this->getJson('/api/memorials');

        $response->assertOk();
        $ids = array_column($response->json('memorials'), 'id');

        $this->assertContains('public-one', $ids);
        $this->assertNotContains('hidden-one', $ids);
    }

    public function test_an_unlisted_dossier_is_still_reachable_by_id(): void
    {
        $memorial = new Memorial(['id' => 'unlisted']);
        $memorial->id = 'unlisted';
        $memorial->syncFromDocument($this->document('unlisted', ['privacy' => 'private_link']));
        $memorial->save();

        $this->getJson('/api/memorials/unlisted')
            ->assertOk()
            ->assertJsonPath('memorial.id', 'unlisted');
    }

    public function test_creating_a_dossier_returns_an_edit_token_once(): void
    {
        $response = $this->postJson('/api/memorials', [
            'document' => $this->document('new-dossier'),
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('editToken'));

        // The token itself is never stored, and never returned again.
        $this->assertNull($this->getJson('/api/memorials/new-dossier')->json('memorial.editToken'));
        $this->assertNotSame(
            $response->json('editToken'),
            Memorial::find('new-dossier')->edit_token_hash
        );
    }

    public function test_it_refuses_a_duplicate_id(): void
    {
        $this->postJson('/api/memorials', ['document' => $this->document('taken')])->assertCreated();

        $this->postJson('/api/memorials', ['document' => $this->document('taken')])
            ->assertStatus(409);
    }

    public function test_utf8_survives_the_round_trip(): void
    {
        $this->postJson('/api/memorials', [
            'document' => $this->document('turkish', ['fullName' => 'Âşık Veysel Şatıroğlu']),
        ])->assertCreated();

        $this->getJson('/api/memorials/turkish')
            ->assertOk()
            ->assertJsonPath('memorial.fullName', 'Âşık Veysel Şatıroğlu');
    }

    public function test_a_dossier_cannot_be_rewritten_without_its_token(): void
    {
        $this->postJson('/api/memorials', ['document' => $this->document('mine')])->assertCreated();

        $this->putJson('/api/memorials/mine', [
            'document' => $this->document('mine', ['fullName' => 'Vandalised']),
        ])->assertStatus(403);

        $this->assertSame('Zeynep Şahinoğlu', Memorial::find('mine')->full_name);
    }

    public function test_the_token_holder_may_rewrite_it(): void
    {
        $token = $this->postJson('/api/memorials', ['document' => $this->document('mine')])
            ->json('editToken');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/memorials/mine', [
                'document' => $this->document('mine', ['fullName' => 'Updated By Owner']),
            ])
            ->assertOk();

        $this->assertSame('Updated By Owner', Memorial::find('mine')->full_name);
    }

    public function test_a_wrong_token_is_refused(): void
    {
        $this->postJson('/api/memorials', ['document' => $this->document('mine')])->assertCreated();

        $this->withHeader('Authorization', 'Bearer not-the-token')
            ->putJson('/api/memorials/mine', [
                'document' => $this->document('mine', ['fullName' => 'Nope']),
            ])
            ->assertStatus(403);
    }

    public function test_seeded_dossiers_refuse_rewrites_outright(): void
    {
        $this->seeded();

        $this->putJson('/api/memorials/albert-einstein', [
            'document' => $this->document('albert-einstein', ['fullName' => 'Vandalised']),
        ])->assertStatus(403);

        $this->assertSame('Albert Einstein', Memorial::find('albert-einstein')->full_name);
    }

    public function test_anyone_may_light_a_candle_and_it_counts(): void
    {
        $this->seeded();

        $this->postJson('/api/memorials/albert-einstein/contributions', ['kind' => 'candle'])
            ->assertCreated()
            ->assertJsonPath('contribution.isApproved', true);

        $this->assertSame(1, Memorial::find('albert-einstein')->candle_count);
    }

    public function test_a_memory_letter_is_held_for_approval(): void
    {
        $this->seeded();

        $this->postJson('/api/memorials/albert-einstein/contributions', [
            'kind' => 'memory',
            'authorName' => 'Bir Ziyaretçi',
            'body' => 'Onu hiç unutmadım.',
        ])
            ->assertCreated()
            ->assertJsonPath('contribution.isApproved', false);

        // Held back until a guardian publishes it.
        $this->getJson('/api/memorials/albert-einstein/contributions')
            ->assertOk()
            ->assertJsonCount(0, 'contributions');
    }

    public function test_a_memory_letter_needs_words(): void
    {
        $this->seeded();

        $this->postJson('/api/memorials/albert-einstein/contributions', ['kind' => 'memory'])
            ->assertStatus(422);
    }

    public function test_an_unknown_contribution_kind_is_refused(): void
    {
        $this->seeded();

        $this->postJson('/api/memorials/albert-einstein/contributions', ['kind' => 'mischief'])
            ->assertStatus(422);

        $this->assertSame(0, Contribution::count());
    }

    public function test_a_missing_dossier_is_a_404(): void
    {
        $this->getJson('/api/memorials/nobody')->assertStatus(404);
    }
}
