<?php

namespace Tests\Feature;

use App\Models\ListCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin category add / edit / delete, including a stale or repeated request for a category that is already gone. */
class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'email'  => 'tauseefchoohan0401@gmail.com',
            'role'   => 'admin',
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'admin');
    }

    public function test_add_edit_and_delete_work(): void
    {
        $this->post(route('admin.categories.store'), ['category' => 'Rigging', 'status' => 'active'])
            ->assertRedirect(route('admin.categories'));
        $category = ListCategory::where('category', 'Rigging')->firstOrFail();

        $this->get(route('admin.categories.edit', $category->id))->assertOk()->assertSee('Rigging');

        $this->post(route('admin.categories.update', $category->id), ['category' => 'Lighting', 'status' => 'inactive'])
            ->assertRedirect(route('admin.categories'));
        $this->assertSame('Lighting', $category->fresh()->category);

        $this->delete(route('admin.categories.delete', $category->id))
            ->assertRedirect(route('admin.categories'))
            ->assertSessionHas('success');
        $this->assertNull($category->fresh());
    }

    public function test_deleting_twice_redirects_back_with_a_message_instead_of_404(): void
    {
        $category = ListCategory::create(['category' => 'Audio', 'status' => 1]);

        $this->delete(route('admin.categories.delete', $category->id))->assertRedirect(route('admin.categories'));

        // second click / old tab / browser resubmit
        $this->delete(route('admin.categories.delete', $category->id))
            ->assertRedirect(route('admin.categories'))
            ->assertSessionHas('error', trans('messages.category_not_found'));
    }

    public function test_editing_or_updating_a_deleted_category_redirects_with_a_message(): void
    {
        $this->get(route('admin.categories.edit', 999))
            ->assertRedirect(route('admin.categories'))
            ->assertSessionHas('error', trans('messages.category_not_found'));

        $this->post(route('admin.categories.update', 999), ['category' => 'X', 'status' => 'active'])
            ->assertRedirect(route('admin.categories'))
            ->assertSessionHas('error', trans('messages.category_not_found'));

        $this->assertSame(0, ListCategory::count());
    }
}
