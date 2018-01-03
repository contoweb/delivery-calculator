<?php

namespace Contoweb\DeliveryCalculator;

use Contoweb\DeliveryCalculator\Holiday;
use Carbon\Carbon;

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
        $durationWorkday = ($this->endHour * 60 + $this->endMinute) - ($this->startHour * 60 + $this->startMinute);
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
        // Load holidays from db in array
        $holidays = [];
        $holidayPeriods = Holiday::all();

        foreach ($holidayPeriods as $holidayPeriod) {
            $holidays[] = $this->dateRange($holidayPeriod->start_date, $holidayPeriod->end_date);
        }       

        return array_reduce($holidays, 'array_merge', array()); // Turn into one-dimensional array
    }
}
