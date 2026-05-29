<?php

namespace Tests\Feature;

use App\Models\ItemTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_can_list_item_templates(): void
    {
        ItemTemplate::create(['name' => 'Hosting Fee']);
        ItemTemplate::create(['name' => 'Web Design Monthly']);

        $response = $this->getJson('/api/v1/item-templates');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Hosting Fee');
    }

    public function test_can_create_item_template(): void
    {
        $response = $this->postJson('/api/v1/item-templates', [
            'name' => 'Consulting Service',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Consulting Service');

        $this->assertDatabaseHas('item_templates', ['name' => 'Consulting Service']);
    }

    public function test_create_item_template_requires_name(): void
    {
        $response = $this->postJson('/api/v1/item-templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_item_template_name_max_200_chars(): void
    {
        $response = $this->postJson('/api/v1/item-templates', [
            'name' => str_repeat('a', 201),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_item_template(): void
    {
        $template = ItemTemplate::create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/item-templates/{$template->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('item_templates', ['id' => $template->id, 'name' => 'New Name']);
    }

    public function test_can_delete_item_template(): void
    {
        $template = ItemTemplate::create(['name' => 'To Delete']);

        $response = $this->deleteJson("/api/v1/item-templates/{$template->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('item_templates', ['id' => $template->id]);
    }

    public function test_list_requires_authentication(): void
    {
        $this->app['auth']->guard('sanctum')->forgetUser();
        $response = $this->withoutCookies()->getJson('/api/v1/item-templates');
        $response->assertStatus(401);
    }

    public function test_can_create_item_template_with_description(): void
    {
        $response = $this->postJson('/api/v1/item-templates', [
            'name' => 'Consulting',
            'description' => 'Monthly retainer for consulting services',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Consulting')
            ->assertJsonPath('data.description', 'Monthly retainer for consulting services');

        $this->assertDatabaseHas('item_templates', [
            'name' => 'Consulting',
            'description' => 'Monthly retainer for consulting services',
        ]);
    }

    public function test_description_is_optional_on_create(): void
    {
        $response = $this->postJson('/api/v1/item-templates', [
            'name' => 'Hosting Fee',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', null);
    }

    public function test_description_max_1000_chars(): void
    {
        $response = $this->postJson('/api/v1/item-templates', [
            'name' => 'Test',
            'description' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_can_update_item_template_description(): void
    {
        $template = ItemTemplate::create(['name' => 'Old Name', 'description' => 'Old desc']);

        $response = $this->putJson("/api/v1/item-templates/{$template->id}", [
            'name' => 'Old Name',
            'description' => 'New description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.description', 'New description');

        $this->assertDatabaseHas('item_templates', [
            'id' => $template->id,
            'description' => 'New description',
        ]);
    }

    public function test_list_includes_description(): void
    {
        ItemTemplate::create(['name' => 'Hosting Fee', 'description' => 'Monthly hosting']);

        $response = $this->getJson('/api/v1/item-templates');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.description', 'Monthly hosting');
    }
}
