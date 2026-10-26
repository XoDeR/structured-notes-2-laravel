<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_notes_and_guests_are_sent_to_login(): void
    {
        $this->get(route('home'))->assertRedirect('/notes');
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
