<?php

it('has pest page', function () {
    $response = $this->get('/pest');

    $response->assertStatus(200);
});
