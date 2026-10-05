<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_renders_without_a_database(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('Frota · PF')
            ->assertSee('Acesse o sistema')
            ->assertDontSee('Projeto em construção');
    }
}
