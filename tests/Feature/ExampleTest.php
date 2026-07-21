<?php

test('returns a successful response with starter kit branding', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('<title>Laravel Worktrees Starter Kit</title>', false);
});
