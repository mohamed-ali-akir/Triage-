<?php

use App\Models\Category;
use App\Models\User;

test('only an administrator can manage categories', function (string $state) {
    $user = $state === 'user' ? User::factory()->create() : User::factory()->{$state}()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->get('/admin/categories')->assertForbidden();
    $this->actingAs($user)->post('/admin/categories', ['name' => 'Téléphonie'])->assertForbidden();
    $this->actingAs($user)->put("/admin/categories/{$category->id}", ['name' => 'Autre nom'])->assertForbidden();
    $this->actingAs($user)->delete("/admin/categories/{$category->id}")->assertForbidden();

    expect(Category::count())->toBe(1);
})->with(['user', 'technician']);

test('an administrator can list categories', function () {
    $category = Category::factory()->create(['name' => 'Téléphonie']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/categories')
        ->assertOk()
        ->assertSee('Téléphonie');
});

test('an administrator can create a category and its slug is generated', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/categories/create')->assertOk();

    $this->actingAs($admin)
        ->post('/admin/categories', ['name' => 'Téléphonie mobile'])
        ->assertRedirect('/admin/categories');

    $this->assertDatabaseHas('categories', [
        'name' => 'Téléphonie mobile',
        'slug' => 'telephonie-mobile',
        'is_active' => true,
    ]);
});

test('a category name must be unique', function () {
    Category::factory()->create(['name' => 'Réseau', 'slug' => 'reseau']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/categories', ['name' => 'Réseau'])
        ->assertSessionHasErrors('name');
});

test('two names that produce the same slug are refused', function () {
    Category::factory()->create(['name' => 'Accès', 'slug' => 'acces']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/admin/categories', ['name' => 'Acces'])
        ->assertSessionHasErrors('name');

    expect(Category::count())->toBe(1);
});

test('renaming or deactivating a category keeps its slug', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Logiciel', 'slug' => 'logiciel']);

    $this->actingAs($admin)->get("/admin/categories/{$category->id}/edit")->assertOk();

    $this->actingAs($admin)
        ->put("/admin/categories/{$category->id}", ['name' => 'Applications', 'is_active' => '0'])
        ->assertRedirect('/admin/categories');

    $category->refresh();

    expect($category->name)->toBe('Applications')
        ->and($category->slug)->toBe('logiciel')
        ->and($category->is_active)->toBeFalse();
});

test('an administrator can delete an unused category', function () {
    $category = Category::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete("/admin/categories/{$category->id}")
        ->assertRedirect('/admin/categories');

    $this->assertModelMissing($category);
});
