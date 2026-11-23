<?php

use App\Models\Category;
use App\Models\KbArticle;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function articlePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Réinitialiser un mot de passe Windows',
        'content' => "1. Ouvrir la console Active Directory.\n2. Chercher le compte.\n3. Choisir « Réinitialiser le mot de passe ».",
        'category_id' => '',
        'is_published' => '1',
    ], $overrides);
}

test('only an administrator manages the knowledge base', function (string $state) {
    $user = $state === 'user' ? User::factory()->create() : User::factory()->{$state}()->create();
    $article = KbArticle::factory()->create();

    $this->actingAs($user)->get('/admin/kb-articles')->assertForbidden();
    $this->actingAs($user)->post('/admin/kb-articles', articlePayload())->assertForbidden();
    $this->actingAs($user)->put("/admin/kb-articles/{$article->id}", articlePayload())->assertForbidden();
    $this->actingAs($user)->delete("/admin/kb-articles/{$article->id}")->assertForbidden();

    expect(KbArticle::count())->toBe(1);
})->with(['user', 'technician']);

test('an administrator lists the articles, drafts included', function () {
    KbArticle::factory()->create(['title' => 'Configurer le VPN']);
    KbArticle::factory()->draft()->create(['title' => 'Article en cours de rédaction']);

    $this->actingAs($this->admin)
        ->get('/admin/kb-articles')
        ->assertOk()
        ->assertSee('Configurer le VPN')
        ->assertSee('Article en cours de rédaction')
        ->assertSee('Brouillon');
});

test('an administrator creates an article', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->get('/admin/kb-articles/create')->assertOk();

    $this->actingAs($this->admin)
        ->post('/admin/kb-articles', articlePayload(['category_id' => $category->id]))
        ->assertRedirect('/admin/kb-articles');

    $article = KbArticle::query()->sole();

    expect($article->title)->toBe('Réinitialiser un mot de passe Windows')
        ->and($article->category_id)->toBe($category->id)
        ->and($article->is_published)->toBeTrue();
});

test('an article is validated', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->post('/admin/kb-articles', articlePayload($overrides))
        ->assertSessionHasErrors($field);

    expect(KbArticle::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'content too short' => [['content' => 'Trop court.'], 'content'],
    'unknown category' => [['category_id' => 9999], 'category_id'],
    'missing state' => [['is_published' => ''], 'is_published'],
]);

test('an administrator updates and unpublishes an article', function () {
    $article = KbArticle::factory()->create();

    $this->actingAs($this->admin)->get("/admin/kb-articles/{$article->id}/edit")->assertOk();

    $this->actingAs($this->admin)
        ->put("/admin/kb-articles/{$article->id}", articlePayload(['title' => 'Nouveau titre de l\'article', 'is_published' => '0']))
        ->assertRedirect('/admin/kb-articles');

    $article->refresh();

    expect($article->title)->toBe('Nouveau titre de l\'article')
        ->and($article->is_published)->toBeFalse();
});

test('an administrator deletes an article', function () {
    $article = KbArticle::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/kb-articles/{$article->id}")
        ->assertRedirect('/admin/kb-articles');

    $this->assertModelMissing($article);
});

test('deleting a category keeps its articles', function () {
    $category = Category::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id]);

    $category->delete();

    expect($article->refresh()->category_id)->toBeNull();
});

test('the Markdown of an article is rendered without letting HTML through', function () {
    $article = KbArticle::factory()->make([
        'content' => "# Étapes\n\n- Redémarrer **le poste**\n\n<script>alert('xss')</script>\n\n[lien piégé](javascript:alert(1))",
    ]);

    $html = $article->contentAsHtml();

    expect($html)->toContain('<h1>Étapes</h1>')
        ->toContain('<strong>le poste</strong>')
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});
