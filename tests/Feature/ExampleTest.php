<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The portal has no public home page: the fallback route sends visitors to the admin login.
     */
    public function test_the_root_url_redirects_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_the_admin_login_page_loads(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
    }
}
