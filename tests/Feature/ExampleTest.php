<?php

test('the root route redirects to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
