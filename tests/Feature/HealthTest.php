<?php

test('the health endpoint responds', function () {
    $this->get('/up')->assertOk();
});
