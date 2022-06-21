<?php

namespace Contoweb\DeliveryCalculator;

use Carbon\CarbonPeriod;
use Contoweb\DeliveryCalculator\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DeliveryCalculator
{
    /**
     * Business start hour
     * Example: 6 => 06:XX
     *
     * @var int
     */
    protected $startHour;

    /**
     * Business start minute
     * Example 45 => XX:45
     *
     * @var int
     */
    protected $startMinute;

    /**
     * Business end hour
     * Example: 18 => 18:XX
     *
     * @var int
     */
    protected $endHour;

    /**
     * Business end minute
     * Example: 30 => XX:30
     *
     * @var int
     */
    protected $endMinute;

    /**
     * Define start and end time for a new class instance
     *
     * @param int $startHour
     * @param int $startMinute
     * @param int $endHour
     * @param int $endMinute
     */
    public function __construct($startHour, $startMinute, $endHour, $endMinute)
    {
        $this->startHour = $startHour;
        $this->startMinute = $startMinute;
        $this->endHour = $endHour;
        $this->endMinute = $endMinute;
    }

    /**
     * Check if a given DateTime is in business hours
     *
     * @param  \Carbon\Carbon $orderDateTime
     * @return boolean
     */
    public function isBusinessTime($orderDateTime)
    {
        // Save current date with business start and end time
        $currentTime = new Carbon($orderDateTime);
        $startTime = new Carbon($currentTime->setTime($this->startHour, $this->startMinute, 0));
        $endTime = new Carbon($currentTime->setTime($this->endHour, $this->endMinute, 0));

        $holidays = $this->getHolidays();

        if ($orderDateTime->isWeekend() || in_array($orderDateTime->toDateString(), $holidays)) {
            return false;
        }

        if ($startTime > $orderDateTime || $endTime <= $orderDateTime) {
            return false;
        }

        return true;
    }

    /**
     * Calculates a delivery DateTime form a given DateTime and duration in hours (considering business hours, weekends and holidays)
     *
     * @param  \Carbon\Carbon $orderDateTime
     * @param  integer $duration
     *
     * @return \Carbon\Carbon $deliveryDateTime
     */
    public function getDeliveryTime($orderDateTime, $duration)
    {
        $holidays = $this->getHolidays();

        $deliveryDateTime = new Carbon($orderDateTime);

        // Calculate durations in minutes
        $duration = $duration * 60;
        $durationFullDay = 1440;
        $durationWorkday = $this->getWorkdayDurationInMinutes();
        $durationInWorkdays = $duration / $durationWorkday;
        $offTime = $durationFullDay - $durationWorkday;

        // Save current date with business start and end time
        $currentTime = new Carbon($deliveryDateTime);
        $startTime = new Carbon($currentTime->setTime($this->startHour, $this->startMinute, 0));
        $endTime = new Carbon($currentTime->setTime($this->endHour, $this->endMinute, 0));

        if ($startTime > $deliveryDateTime) {
            $deliveryDateTime->setTime($this->startHour, $this->startMinute);
        }

        if ($endTime < $deliveryDateTime) {
            $deliveryDateTime->addDays(1)->setTime($this->startHour, $this->startMinute);
        }

        // Skip weekend and holidays and set to startTime if there are holidays
        $deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays, true);

        if (floor($durationInWorkdays) >= 1) {
            // Substract durationWorkdays from duration to get the remaining time
            $remainingTime = $duration - floor($durationInWorkdays) * $durationWorkday;

            for ($days = 1; $days <= $durationInWorkdays; $days++) {
                $deliveryDateTime->addDays(1);

                $deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays);
            }

            // Save current date with business end time
            $currentTime = new Carbon($deliveryDateTime);
            $endTime = new Carbon($currentTime->setTime($this->endHour, $this->endMinute, 0));

            $deliveryDateTime->addMinutes($remainingTime);
        } else {
            // Save current date with business end time
            $currentTime = new Carbon($deliveryDateTime);
            $endTime = new Carbon($currentTime->setTime($this->endHour, $this->endMinute, 0));

            $deliveryDateTime->addMinutes($duration);

            // Skip weekend and holidays
            $deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays);
        }

        if ($endTime < $deliveryDateTime)
            $deliveryDateTime->addMinutes($offTime);

        // Skip weekend and holidays
        $deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays);

        return $deliveryDateTime;
    }

    /**
     * Calculates duration in working hours between two given dates (considering business hours, weekends and holidays).
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    public function getDurationInWorkingHours($startDate, $endDate)
    {
        $startDate = new Carbon($startDate);
        $endDate = new Carbon($endDate);

        $startDateCheck = new Carbon($startDate);
        $startDateStartCheck = new Carbon($startDateCheck->setTime($this->startHour, $this->startMinute, 0));
        $startDateEndCheck = new Carbon($startDateCheck->setTime($this->endHour, $this->endMinute, 0));

        if ($startDateStartCheck > $startDate) {
            $startDate->setTime($this->startHour, $this->startMinute);
        }

        if ($startDateEndCheck <= $startDate) {
            $startDate->addDays(1)->setTime($this->startHour, $this->startMinute);
        }

        $endDateCheck = new Carbon($endDate);
        $endDateStartCheck = new Carbon($endDateCheck->setTime($this->startHour, $this->startMinute, 0));
        $endDateEndCheck = new Carbon($endDateCheck->setTime($this->endHour, $this->endMinute, 0));

        if ($endDateStartCheck > $endDate) {
            $endDate->setTime($this->startHour, $this->startMinute);
        }

        if ($endDateEndCheck <= $endDate) {
            $endDate->addDays(1)->setTime($this->startHour, $this->startMinute);
        }

        // Check if start date is in business time, otherwise set to next business day start time until it is a workday.
        if (!$this->isBusinessTime($startDate)) {
            do {
                $startDate->addDay();
            } while (!$this->isBusinessTime($startDate));
        }

        // Check if end date is in business time, otherwise set to next business day start time.
        if (!$this->isBusinessTime($endDate)) {
            do {
                $endDate->addDay();
            } while (!$this->isBusinessTime($endDate));
        }

        $datesBetween = CarbonPeriod::create(
            (new Carbon($startDate))->setTime($this->startHour, $this->startMinute),
            (new Carbon($endDate))->setTime($this->startHour, $this->startMinute)
        )->toArray();

        $workingMinutes = 0;

        // If there is only one date it means that start and end date are on the same day => Calculate the duration between the two times.
        if (count($datesBetween) === 1) {
            $workingMinutes = $startDate->diffInMinutes($endDate);

            return $workingMinutes / 60;
        }

        // Loop through all dates between start and end date (including start and end date).
        foreach ($datesBetween as $key => $dateBetween) {
            if ($this->isBusinessTime($dateBetween)) {
                // First day could have a time between the day, therefore we need to calculate how many hours are left till the end of workday.
                if ($key === 0) {
                    $workingMinutes += $dateBetween->setTime($this->endHour, $this->endMinute)->diffInMinutes($startDate);

                    continue;
                }

                // Last day could have a time between the day, therefore we need to calculate how many hours are left from start of workday.
                if ($key === array_key_last($datesBetween)) {
                    $workingMinutes += Carbon::make($endDate)->diffInMinutes($dateBetween->setTime($this->startHour, $this->startMinute));

                    continue;
                }

                // Add full workday
                $workingMinutes += $this->getWorkdayDurationInMinutes();
            }
        }

        return $workingMinutes / 60;
    }

    /**
     * Calculates duration in working days between two given dates (considering business hours, weekends and holidays).
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    public function getDurationInWorkingDays($startDate, $endDate)
    {
        $workingHours = $this->getDurationInWorkingHours($startDate, $endDate);

        return $workingHours / ($this->getWorkdayDurationInMinutes() / 60);
    }

    /**
     * Creating date collection between two dates
     *
     * @param string start date, time or datetime format
     * @param string end date, time or datetime format
     * @param string step between the dates
     * @param string date of output format
     *
     * @return array
     */
    private function dateRange($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    /**
     * Checks and skips a day if the date is a holiday
     *
     * @param Carbon\Carbon date time object
     * @param array holidays
     * @param boolean setStartTime
     * @param integer startHour
     * @param integer startMinute
     *
     * @return Carbon\Carbon date time object
     */
    private function skipHolidays($dateTime, $holidays, $setStartTime = false)
    {
        while ($dateTime->isWeekend() || in_array($dateTime->toDateString(), $holidays)) {
            $dateTime->addDays(1);

            if ($setStartTime) {
                $dateTime->setTime($this->startHour, $this->startMinute, 0);
            }
        }

        return $dateTime;
    }

    /**
     * Fetch holidays from database
     *
     * @return array holidays
     */
    private function getHolidays()
    {
        // Load holidays from cache (or DB) in array
        $holidays = [];

        if (Cache::has('delivery_calculator_holidays')) {
            $holidayPeriods = Cache::get('delivery_calculator_holidays');
        } else {
            $holidayPeriods = Holiday::all();
            Cache::put('delivery_calculator_holidays', $holidayPeriods);
        }

        foreach ($holidayPeriods as $holidayPeriod) {
            $holidays[] = $this->dateRange($holidayPeriod->start_date, $holidayPeriod->end_date);
        }

        return array_reduce($holidays, 'array_merge', array()); // Turn into one-dimensional array
    }

    /**
     * Get workday duration in minutes.
     *
     * @return float|int
     */
    private function getWorkdayDurationInMinutes()
    {
        return ($this->endHour * 60 + $this->endMinute) - ($this->startHour * 60 + $this->startMinute);
    }
}
