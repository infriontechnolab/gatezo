<?php

it('states the real pricing: first event free and the cheapest paid plan', function () {
    $this->capPlan('starter', ['price_monthly' => 199]);

    $this->get('/')->assertOk()
        ->assertSee('Your first event is free, any size')
        ->assertSee('from ₹199 a month')
        ->assertDontSee('early access')
        ->assertDontSee('never a subscription')
        ->assertDontSee('the gate opens');
});

it('only links to the demo when the demo is switched on', function () {
    config(['gatezo.demo.enabled' => false]);
    $this->get('/')->assertOk()->assertDontSee('href="/demo"', false);

    config(['gatezo.demo.enabled' => true]);
    $this->get('/')->assertOk()->assertSee('href="/demo"', false);
});
