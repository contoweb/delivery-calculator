<?php

namespace Contoweb\DeliveryCalculator\Test;

use Carbon\Carbon;
use Contoweb\DeliveryCalculator\DeliveryCalculator;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class DeliveryCalculatorTest extends TestCase
{
    protected DeliveryCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // Business hours: 08:00 - 17:00
        $this->calculator = new DeliveryCalculator(8, 0, 17, 0);

        // Bootstrap a minimal Laravel container for facades
        $app = new Container();
        $app['config'] = new \Illuminate\Config\Repository([
            'cache' => [
                'default' => 'array',
                'stores' => [
                    'array' => ['driver' => 'array'],
                ],
            ],
        ]);
        $app->singleton('cache', function ($app) {
            return new CacheManager($app);
        });
        $app->singleton('cache.store', function ($app) {
            return $app['cache']->store();
        });

        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        // Set up SQLite in-memory database
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Create holidays table
        Capsule::schema()->create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // Clear cache to force reload of holidays
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('holidays');
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    private function seedHoliday(string $startDate, string $endDate): void
    {
        Capsule::table('holidays')->insert([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        Cache::flush();
    }

    // --- isBusinessTime ---

    public function testIsBusinessTimeDuringBusinessHours(): void
    {
        // Wednesday 2025-01-08 at 10:00 => business time
        $date = Carbon::create(2025, 1, 8, 10, 0, 0);
        $this->assertTrue($this->calculator->isBusinessTime($date));
    }

    public function testIsBusinessTimeAtStartOfBusinessHours(): void
    {
        // Exactly at 08:00
        $date = Carbon::create(2025, 1, 8, 8, 0, 0);
        $this->assertTrue($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeAtEndOfBusinessHours(): void
    {
        // Exactly at 17:00 => end time is exclusive
        $date = Carbon::create(2025, 1, 8, 17, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeBeforeBusinessHours(): void
    {
        // 06:00 before business hours
        $date = Carbon::create(2025, 1, 8, 6, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeAfterBusinessHours(): void
    {
        // 20:00 after business hours
        $date = Carbon::create(2025, 1, 8, 20, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeOnWeekend(): void
    {
        // Saturday 2025-01-11 at 10:00
        $date = Carbon::create(2025, 1, 11, 10, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeOnSunday(): void
    {
        // Sunday 2025-01-12 at 10:00
        $date = Carbon::create(2025, 1, 12, 10, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    public function testIsNotBusinessTimeOnHoliday(): void
    {
        // Wednesday 2025-01-08 is a holiday
        $this->seedHoliday('2025-01-08', '2025-01-08');

        $date = Carbon::create(2025, 1, 8, 10, 0, 0);
        $this->assertFalse($this->calculator->isBusinessTime($date));
    }

    // --- getDeliveryTime ---

    public function testDeliveryTimeSameDayDuringBusinessHours(): void
    {
        // Wednesday 2025-01-08 at 10:00, duration 2 hours => 12:00 same day
        $order = Carbon::create(2025, 1, 8, 10, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 2);

        $this->assertEquals('2025-01-08 12:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeSpansToNextDay(): void
    {
        // Wednesday 2025-01-08 at 15:00, duration 4 hours
        // 2 hours left on Wed (15:00 - 17:00), 2 hours on Thu => Thu 10:00
        $order = Carbon::create(2025, 1, 8, 15, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 4);

        $this->assertEquals('2025-01-09 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderBeforeBusinessHours(): void
    {
        // Wednesday 2025-01-08 at 06:00 (before 08:00), duration 2 hours
        // Starts at 08:00 => delivery at 10:00
        $order = Carbon::create(2025, 1, 8, 6, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 2);

        $this->assertEquals('2025-01-08 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderAfterBusinessHours(): void
    {
        // Wednesday 2025-01-08 at 20:00 (after 17:00), duration 2 hours
        // Starts next day Thu 08:00 => delivery at 10:00
        $order = Carbon::create(2025, 1, 8, 20, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 2);

        $this->assertEquals('2025-01-09 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderOnFridaySpansWeekend(): void
    {
        // Friday 2025-01-10 at 15:00, duration 4 hours
        // 2 hours left on Fri (15:00 - 17:00), skip Sat+Sun, 2 hours on Mon => Mon 10:00
        $order = Carbon::create(2025, 1, 10, 15, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 4);

        $this->assertEquals('2025-01-13 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderOnSaturday(): void
    {
        // Saturday 2025-01-11 at 10:00, duration 2 hours
        // Skips to Monday 08:00 => delivery Mon 10:00
        $order = Carbon::create(2025, 1, 11, 10, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 2);

        $this->assertEquals('2025-01-13 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderOnSunday(): void
    {
        // Sunday 2025-01-12 at 14:00, duration 3 hours
        // Skips to Monday 08:00 => delivery Mon 11:00
        $order = Carbon::create(2025, 1, 12, 14, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 3);

        $this->assertEquals('2025-01-13 11:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeSkipsHoliday(): void
    {
        // Thursday 2025-01-09 is a holiday
        $this->seedHoliday('2025-01-09', '2025-01-09');

        // Wednesday 2025-01-08 at 15:00, duration 4 hours
        // 2 hours left on Wed, skip Thu (holiday), 2 hours on Fri => Fri 10:00
        $order = Carbon::create(2025, 1, 8, 15, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 4);

        $this->assertEquals('2025-01-10 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeSkipsMultiDayHoliday(): void
    {
        // Thu-Fri 2025-01-09 to 2025-01-10 are holidays
        $this->seedHoliday('2025-01-09', '2025-01-10');

        // Wednesday 2025-01-08 at 15:00, duration 4 hours
        // 2 hours left on Wed, skip Thu+Fri (holidays) + Sat+Sun (weekend), 2 hours on Mon => Mon 10:00
        $order = Carbon::create(2025, 1, 8, 15, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 4);

        $this->assertEquals('2025-01-13 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeOrderOnHoliday(): void
    {
        // Wednesday 2025-01-08 is a holiday
        $this->seedHoliday('2025-01-08', '2025-01-08');

        // Order on holiday Wed at 10:00, duration 2 hours
        // Skips to Thu 08:00 => delivery Thu 10:00
        $order = Carbon::create(2025, 1, 8, 10, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 2);

        $this->assertEquals('2025-01-09 10:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeFullWorkday(): void
    {
        // Wednesday 2025-01-08 at 08:00, duration 9 hours (full workday)
        // Full day Wed => delivery Thu 08:00
        $order = Carbon::create(2025, 1, 8, 8, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 9);

        $this->assertEquals('2025-01-09 08:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    public function testDeliveryTimeMultipleWorkdays(): void
    {
        // Wednesday 2025-01-08 at 08:00, duration 18 hours (2 full workdays)
        // Wed + Thu => delivery Fri 08:00
        $order = Carbon::create(2025, 1, 8, 8, 0, 0);
        $delivery = $this->calculator->getDeliveryTime($order, 18);

        $this->assertEquals('2025-01-10 08:00:00', $delivery->format('Y-m-d H:i:s'));
    }

    // --- getDurationInWorkingHours ---

    public function testDurationInWorkingHoursSameDay(): void
    {
        $start = Carbon::create(2025, 1, 8, 10, 0, 0);
        $end = Carbon::create(2025, 1, 8, 14, 0, 0);

        $this->assertEquals(4.0, $this->calculator->getDurationInWorkingHours($start, $end));
    }

    public function testDurationInWorkingHoursAcrossTwoDays(): void
    {
        // Wed 15:00 to Thu 10:00 => 2h (Wed 15-17) + 2h (Thu 08-10) = 4h
        $start = Carbon::create(2025, 1, 8, 15, 0, 0);
        $end = Carbon::create(2025, 1, 9, 10, 0, 0);

        $this->assertEquals(4.0, $this->calculator->getDurationInWorkingHours($start, $end));
    }

    public function testDurationInWorkingHoursAcrossWeekend(): void
    {
        // Fri 15:00 to Mon 10:00 => 2h (Fri 15-17) + 2h (Mon 08-10) = 4h
        $start = Carbon::create(2025, 1, 10, 15, 0, 0);
        $end = Carbon::create(2025, 1, 13, 10, 0, 0);

        $this->assertEquals(4.0, $this->calculator->getDurationInWorkingHours($start, $end));
    }

    public function testDurationInWorkingHoursSkipsHoliday(): void
    {
        // Thursday 2025-01-09 is a holiday
        $this->seedHoliday('2025-01-09', '2025-01-09');

        // Wed 15:00 to Fri 10:00 => 2h (Wed 15-17) + skip Thu (holiday) + 2h (Fri 08-10) = 4h
        $start = Carbon::create(2025, 1, 8, 15, 0, 0);
        $end = Carbon::create(2025, 1, 10, 10, 0, 0);

        $this->assertEquals(4.0, $this->calculator->getDurationInWorkingHours($start, $end));
    }

    public function testDurationInWorkingHoursStartBeforeBusinessHours(): void
    {
        // Start at 06:00 (before business), end at 12:00 => clamped to 08:00-12:00 = 4h
        $start = Carbon::create(2025, 1, 8, 6, 0, 0);
        $end = Carbon::create(2025, 1, 8, 12, 0, 0);

        $this->assertEquals(4.0, $this->calculator->getDurationInWorkingHours($start, $end));
    }

    // --- getDurationInWorkingDays ---

    public function testDurationInWorkingDaysFullDay(): void
    {
        // Wed 08:00 to Thu 08:00 => 1 full working day
        $start = Carbon::create(2025, 1, 8, 8, 0, 0);
        $end = Carbon::create(2025, 1, 9, 8, 0, 0);

        $this->assertEquals(1.0, $this->calculator->getDurationInWorkingDays($start, $end));
    }

    public function testDurationInWorkingDaysHalfDay(): void
    {
        // Wed 08:00 to Wed 12:30 => 4.5h / 9h = 0.5 days
        $start = Carbon::create(2025, 1, 8, 8, 0, 0);
        $end = Carbon::create(2025, 1, 8, 12, 30, 0);

        $this->assertEquals(0.5, $this->calculator->getDurationInWorkingDays($start, $end));
    }
}
