<?php


// ExampleTest.php — ExampleTest module.
//
// exports: ExampleTest | ExampleTest::test_the_application_redirects_guests_to_login(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
