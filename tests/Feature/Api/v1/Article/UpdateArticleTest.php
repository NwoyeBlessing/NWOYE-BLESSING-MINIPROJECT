<?php

namespace Tests\Feature\Api\v1\Article;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateArticleTest extends TestCase
{
    use WithFaker;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Article $article */
        $article = Article::factory()->create();
        $this->article = $article;
    }

    public function testUpdateArticle(): void
    {
        $author = $this->article->author;

        $this->assertNotEquals($title = 'Updated title', $this->article->title);
        $this->assertNotEquals($fakeSlug = 'overwrite-slug', $this->article->slug);
        $this->assertNotEquals($description = 'New description.', $this->article->description);
        $this->assertNotEquals($body = 'Updated article body.', $this->article->body);

        $response = $this->actingAs($author)
            ->putJson("/api/v1/articles/{$this->article->slug}", [
                'article' => [
                    'title' => $title,
                    'slug' => $fakeSlug, // must be overwritten with title slug
                    'description' => $description,
                    'body' => $body,
                ],
            ]);

        $response->assertOk()
            ->assertExactJson([
                'article' => [
                    'slug' => 'updated-title',
                    'title' => $title,
                    'description' => $description,
                    'body' => $body,
                    'tagList' => [],
                    'createdAt' => optional($this->article->created_at)->toISOString(),
                    'updatedAt' => optional($this->article->updated_at)->toISOString(),
                    'favorited' => false,
                    'favoritesCount' => 0,
                    'author' => [
                        'username' => $author->username,
                        'bio' => $author->bio,
                        'image' => $author->image,
                        'following' => false,
                    ],
                ],
            ]);
    }

    public function testUpdateForeignArticle(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/articles/{$this->article->slug}", [
                'article' => [
                    'title' => $this->faker->sentence(4),
                    'description' => $this->faker->paragraph(),
                    'body' => $this->faker->text(),
                ],
            ]);

        $response->assertForbidden();
    }

    /**
     * @dataProvider articleProvider
     * @param array<mixed> $data
     * @param array<string> $errors
     */
    public function testUpdateArticleValidation(array $data, array $errors): void
    {
        $response = $this->actingAs($this->article->author)
            ->putJson("/api/v1/articles/{$this->article->slug}", $data);

        $response->assertStatus(422)
            ->assertInvalid($errors);
    }

    public function testUpdateArticleValidationUnique(): void
    {
        /** @var Article $anotherArticle */
        $anotherArticle = Article::factory()->create();

        $response = $this->actingAs($this->article->author)
            ->putJson("/api/v1/articles/{$this->article->slug}", [
                'article' => [
                    'title' => $anotherArticle->title,
                    'description' => $this->faker->paragraph(),
                    'body' => $this->faker->text(),
                ],
            ]);

        $response->assertStatus(422)
            ->assertInvalid(['article.slug']);
    }

    public function testSelfUpdateArticleValidationUnique(): void
    {
        $response = $this->actingAs($this->article->author)
            ->putJson("/api/v1/articles/{$this->article->slug}", [
                'article' => [
                    'title' => $this->article->title,
                    'description' => $this->article->description,
                    'body' => $this->article->body,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('article.slug', $this->article->slug);
    }

    public function testUpdateNonExistentArticle(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/articles/non-existent', [
                'article' => [
                    'title' => $this->faker->sentence(4),
                    'description' => $this->faker->paragraph(),
                    'body' => $this->faker->text(),
                ],
            ]);

        $response->assertNotFound();
    }

    public function testUpdateArticleWithoutAuth(): void
    {
        $response = $this->putJson("/api/v1/articles/{$this->article->slug}", [
            'article' => [
                'title' => $this->faker->sentence(4),
                'description' => $this->faker->paragraph(),
                'body' => $this->faker->text(),
            ],
        ]);

        $response->assertUnauthorized();
    }

    /**
     * @return array<int|string, array<mixed>>
     */
    public function articleProvider(): array
    {
        $errors = ['article.title', 'article.description', 'article.body'];
        $allErrors = array_merge($errors, ['article.slug']);

        return [
            'required' => [[], $allErrors],
            'not strings' => [[
                'article' => [
                    'title' => 123,
                    'description' => [],
                    'body' => null,
                ],
            ], $errors],
        ];
    }
}
