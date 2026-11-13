<?php

namespace Tests\Feature;

use App\Models\ListCategory;
use App\Models\ListStory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** POST /api/srearch-product */
class MarketplaceSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ListCategory $electronics;
    protected ListCategory $furniture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->electronics = ListCategory::create(['category' => 'Electronics', 'status' => true]);
        $this->furniture   = ListCategory::create(['category' => 'Furniture', 'status' => true]);
    }

    protected function product(array $attributes, int $minutesAgo = 0): ListStory
    {
        $product = ListStory::create(array_merge([
            'user_id'     => $this->user->id,
            'category_id' => $this->electronics->id,
            'title'       => 'Item',
            'old_price'   => '100',
            'new_price'   => '80',
            'location'    => 'Dallas',
            'description' => 'Good item',
            'condition'   => 'new',
            'status'      => 1,
        ], $attributes));

        $product->created_at = Carbon::now()->subMinutes($minutesAgo);
        $product->save();

        return $product;
    }

    protected function search(array $body)
    {
        return $this->postJson('/api/srearch-product', $body);
    }

    protected function titles($response): array
    {
        return array_column($response->json('data.data'), 'title');
    }

    public function test_empty_search_keeps_the_old_message(): void
    {
        $this->search(['search' => '   '])
            ->assertOk()
            ->assertJson(['status' => false, 'message' => 'Please enter a search value.', 'data' => []]);
    }

    public function test_matches_each_field(): void
    {
        $this->product(['title' => 'Samsung Phone']);
        $this->product(['title' => 'Lamp', 'location' => 'Houston']);
        $this->product(['title' => 'Chair', 'description' => 'Wooden oak chair', 'category_id' => $this->furniture->id]);
        $this->product(['title' => 'Old radio', 'condition' => 'used']);
        $this->product(['title' => 'Cheap mouse', 'new_price' => '15']);
        $this->product(['title' => 'Desk', 'old_price' => '999', 'category_id' => $this->furniture->id]);

        $cases = [
            'samsung'   => ['Samsung Phone'],     // title, any letter case
            'houston'   => ['Lamp'],              // location
            'oak'       => ['Chair'],             // description
            'used'      => ['Old radio'],         // condition
            '15'        => ['Cheap mouse'],       // new price
            '999'       => ['Desk'],              // old price
            'furniture' => ['Chair', 'Desk'],     // category name
            'sams'      => ['Samsung Phone'],     // part of a word
        ];

        foreach ($cases as $term => $expected) {
            $titles = $this->titles($this->search(['search' => $term])->assertOk()->assertJson(['status' => true]));
            sort($titles);
            $this->assertSame($expected, $titles, "search: {$term}");
        }
    }

    public function test_any_word_matches_and_best_match_comes_first(): void
    {
        $this->product(['title' => 'Phone case', 'location' => 'Dallas'], 1);
        $this->product(['title' => 'Table', 'location' => 'Houston'], 2);
        $this->product(['title' => 'iPhone 13 blue', 'location' => 'Houston'], 3);
        $this->product(['title' => 'Sofa', 'location' => 'Austin'], 0);

        $titles = $this->titles($this->search(['search' => 'iphone houston']));

        // iPhone matches both words (first), Table matches "houston" only;
        // Phone case ("iphone" is not in it) and Sofa match nothing
        $this->assertSame(['iPhone 13 blue', 'Table'], $titles);

        // a one-letter word next to longer words is ignored ("a" would match everything)
        $this->assertSame(['iPhone 13 blue', 'Table'], $this->titles($this->search(['search' => 'a iphone houston'])));
    }

    public function test_search_by_category_name_with_other_words(): void
    {
        $this->product(['title' => 'Bulb', 'location' => 'Vihari', 'condition' => 'used'], 0);
        $this->product(['title' => 'Sofa', 'category_id' => $this->furniture->id], 1);

        $this->assertSame(['Bulb'], $this->titles($this->search(['search' => 'electronics'])));
        $this->assertSame(['Bulb'], $this->titles($this->search(['search' => 'vihari used'])));
    }

    public function test_title_match_ranks_above_description_match(): void
    {
        $this->product(['title' => 'Bike stand', 'description' => 'Fits any bike'], 0);
        $this->product(['title' => 'Helmet', 'description' => 'Great for bike rides'], 5);

        $this->assertSame(['Bike stand', 'Helmet'], $this->titles($this->search(['search' => 'bike'])));

        // newer product with only a description match still ranks below the title match
        $this->product(['title' => 'Gloves', 'description' => 'bike gloves'], 0);
        $this->assertSame('Bike stand', $this->titles($this->search(['search' => 'bike']))[0]);
    }

    public function test_category_and_condition_filters(): void
    {
        $this->product(['title' => 'Blue lamp', 'category_id' => $this->electronics->id, 'condition' => 'new']);
        $this->product(['title' => 'Blue chair', 'category_id' => $this->furniture->id, 'condition' => 'used']);
        $this->product(['title' => 'Blue table', 'category_id' => $this->furniture->id, 'condition' => 'new']);

        $titles = $this->titles($this->search(['search' => 'blue', 'category_id' => $this->furniture->id]));
        sort($titles);
        $this->assertSame(['Blue chair', 'Blue table'], $titles);

        $this->assertSame(['Blue chair'], $this->titles($this->search([
            'search' => 'blue', 'category_id' => $this->furniture->id, 'condition' => 'used',
        ])));

        // a category on its own (no text) lists that category
        $this->assertCount(2, $this->search(['category_id' => $this->furniture->id])->json('data.data'));

        $this->search(['search' => 'blue', 'condition' => 'broken'])->assertStatus(422);
    }

    public function test_no_results_and_wildcards_are_literal(): void
    {
        $this->product(['title' => 'Lamp']);

        $this->search(['search' => 'zzzz'])
            ->assertOk()
            ->assertJson(['status' => false, 'message' => 'No products found.', 'data' => []]);

        // % and _ must not match everything
        $this->search(['search' => '%'])->assertJson(['status' => false, 'message' => 'Please enter a search value.']);
        $this->search(['search' => '_ _'])->assertJson(['status' => false]);
    }

    public function test_response_shape_is_unchanged(): void
    {
        $this->product(['title' => 'Camera']);

        $this->search(['search' => 'camera'])
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data' => [
                'current_page', 'last_page', 'per_page', 'total',
                'data' => [['id', 'title', 'old_price', 'new_price', 'location', 'description', 'condition', 'category', 'images', 'user']],
            ]])
            ->assertJsonPath('data.per_page', 10);
    }

    public function test_requires_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeader('Accept', 'application/json')
            ->post('/api/srearch-product', ['search' => 'x'])
            ->assertUnauthorized();
    }
}
