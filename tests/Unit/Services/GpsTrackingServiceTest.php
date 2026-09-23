<?php

namespace Tests\Unit\Services;

use App\Services\GpsTrackingService;
use Tests\TestCase;

class GpsTrackingServiceTest extends TestCase
{
    private GpsTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GpsTrackingService();
    }

    // ── Coordinate validation ──────────────────────────────────────────────

    public function test_valid_coordinates_pass_validation(): void
    {
        $this->assertTrue($this->service->validateCoordinates(48.137154, 11.576124));
        $this->assertTrue($this->service->validateCoordinates(0, 0));
        $this->assertTrue($this->service->validateCoordinates(-90, -180));
        $this->assertTrue($this->service->validateCoordinates(90, 180));
    }

    public function test_out_of_range_coordinates_fail_validation(): void
    {
        $this->assertFalse($this->service->validateCoordinates(91, 0));    // lat > 90
        $this->assertFalse($this->service->validateCoordinates(-91, 0));   // lat < -90
        $this->assertFalse($this->service->validateCoordinates(0, 181));   // lng > 180
        $this->assertFalse($this->service->validateCoordinates(0, -181));  // lng < -180
    }

    public function test_non_numeric_coordinates_fail_validation(): void
    {
        $this->assertFalse($this->service->validateCoordinates('abc', 0));
        $this->assertFalse($this->service->validateCoordinates(null, 0));
        $this->assertFalse($this->service->validateCoordinates(48, null));
    }

    // ── Haversine distance ─────────────────────────────────────────────────

    public function test_distance_between_same_point_is_zero(): void
    {
        $dist = $this->service->calculateDistance(48.137154, 11.576124, 48.137154, 11.576124);
        $this->assertEquals(0.0, $dist);
    }

    public function test_distance_munich_to_berlin_is_approximately_504km(): void
    {
        // Munich: 48.137154, 11.576124
        // Berlin: 52.520008, 13.404954
        $dist = $this->service->calculateDistance(48.137154, 11.576124, 52.520008, 13.404954);

        // Haversine gives ~504 km — tolerance ±5 km
        $this->assertGreaterThan(499, $dist);
        $this->assertLessThan(510, $dist);
    }

    public function test_distance_is_symmetric(): void
    {
        $d1 = $this->service->calculateDistance(48.0, 11.0, 52.0, 13.0);
        $d2 = $this->service->calculateDistance(52.0, 13.0, 48.0, 11.0);

        $this->assertEquals($d1, $d2);
    }

    // ── Geofencing ─────────────────────────────────────────────────────────

    public function test_point_within_radius_returns_true(): void
    {
        // Center: Munich city center
        // Point: 100m away (approximately)
        $result = $this->service->isWithinRadius(
            48.137154, 11.576124,  // point (same location for this test)
            48.137154, 11.576124,  // center
            200                    // 200m radius
        );
        $this->assertTrue($result);
    }

    public function test_point_outside_radius_returns_false(): void
    {
        // Munich vs Berlin — clearly outside any reasonable check-in radius
        $result = $this->service->isWithinRadius(
            52.520008, 13.404954, // Berlin (point)
            48.137154, 11.576124, // Munich (center)
            500                   // 500m radius
        );
        $this->assertFalse($result);
    }

    // ── Minutes conversion ─────────────────────────────────────────────────

    public function test_minutes_to_hours_conversion(): void
    {
        $travelCalc = app(\App\Services\TravelTimeCalculatorService::class);

        $this->assertEquals(1.0,  $travelCalc->minutesToHours(60));
        $this->assertEquals(1.5,  $travelCalc->minutesToHours(90));
        $this->assertEquals(0.5,  $travelCalc->minutesToHours(30));
        $this->assertEquals(0.25, $travelCalc->minutesToHours(15));
    }
}
