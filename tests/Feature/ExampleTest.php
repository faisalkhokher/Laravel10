<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\RecurringOrder;
use Tests\TestCase;
use App\Models\RecurringRequest;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // dd(RecurringRequest::limit(1)->get());
        RecurringOrder::create([
            'recurring_request_id' => 2,
            'entity_id'           => 1,
            'attempts'            => 1,
            'progress'            => 'new',
            'execution_date'      => now()->format('Y-m-d'),
        ]);
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
