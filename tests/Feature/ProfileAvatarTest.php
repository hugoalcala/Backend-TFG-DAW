<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_avatar_with_profile_avatar_endpoint(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/to-delete.jpg',
        ]);

        Storage::disk('public')->put($user->avatar_path, 'fake-image-content');
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/profile/avatar');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado correctamente')
            ->assertJsonPath('user.avatar_path', null)
            ->assertJsonPath('user.avatar_url', null);

        $user->refresh();

        $this->assertNull($user->avatar_path);
        $this->assertFalse(Storage::disk('public')->exists('avatars/to-delete.jpg'));
    }

    public function test_it_uploads_and_persists_user_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->post('/api/me', [
            'avatar' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado correctamente');

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($user->avatar_path));

        $response->assertJsonPath('user.avatar_url', asset('storage/'.$user->avatar_path));
    }

    public function test_avatar_url_is_visible_in_me_response(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/test-avatar.jpg',
        ]);

        Storage::disk('public')->put($user->avatar_path, 'fake-image-content');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('avatar_url', asset('storage/'.$user->avatar_path));
    }
}
