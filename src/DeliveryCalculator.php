<?php

namespace Contoweb\DeliveryCalculator;

use Contoweb\DeliveryCalculator\Holiday;
use Carbon\Carbon;

class DeliveryCalculator
{
    // Set default time interval (eg. 05:45) / end (eg. 23:00)
	public $startHour;
	public $startMinute;
	public $endHour;
	public $endMinute;

	/**
	 * Define start and end time for a new class instance
	 *
	 * @param startHour
	 * @param startMinute
	 * @param endHour
	 * @param endMinute
	 */
	public function __construct ($startHour, $startMinute, $endHour, $endMinute) { 
        $this->startHour = $startHour; 
        $this->startMinute = $startMinute; 
        $this->endHour = $endHour; 
        $this->endMinute = $endMinute; 
    }

    /**
	 * Check if order time is business-time
	 *
	 * @param Carbon\Carbon date time object
	 */
	public function isBusinessTime ($orderDateTime) { 
		// Stamp start and end time
		$actualTime = new Carbon($orderDateTime);
		$startTime = new Carbon($actualTime->setTime($this->startHour, $this->startMinute, 0));
		$endTime = new Carbon($actualTime->setTime($this->endHour, $this->endMinute, 0));

		$holidays = $this->getHolidays();

		if ($orderDateTime->isWeekend() || in_array($orderDateTime->toDateString(), $holidays))
			return false;

		if ($startTime > $orderDateTime || $endTime <= $orderDateTime)
			return false;

		return true;
    }

    /**
	 * Calculating a delivery date with start date and duration in hours (considering weekends and holidays)
	 *
	 * @param Carbon\Carbon date time object
	 * @param integer duration
	 * @return Carbon\Carbon date time object
	 */
    public function getDeliveryTime ($orderDateTime, $duration) {

		$holidays = $this->getHolidays();
		
		// New Carbon date for calculation
		$deliveryDateTime = new Carbon($orderDateTime);

		// Calculate in Minutes
		$fullDay = 1440;
		$workday = ($this->endHour * 60 + $this->endMinute) - ($this->startHour * 60 + $this->startMinute);
		$duration = $duration * 60;

		$durationWorkdays = $duration / $workday;

		// The time off business
		$offTime = $fullDay - $workday;

		// Stamp start and end time
		$actualTime = new Carbon($deliveryDateTime);
		$startTime = new Carbon($actualTime->setTime($this->startHour, $this->startMinute, 0));
		$endTime = new Carbon($actualTime->setTime($this->endHour, $this->endMinute, 0));

		if ($startTime > $deliveryDateTime)
			$deliveryDateTime->setTime($this->startHour, $this->startMinute);

		if ($endTime < $deliveryDateTime) 
			$deliveryDateTime->addDays(1)->setTime($this->startHour, $this->startMinute);

		// Skip weekend and holidays and set to startTime if there are holidays
		$deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays, true);

		// Workdays are one or more
		if (floor($durationWorkdays) >= 1) {

			// Left time after days have passed
			$remainingTime = $duration - floor($durationWorkdays) * $workday;

			for ($days = 1; $days <= $durationWorkdays; $days++) { 
				$deliveryDateTime->addDays(1);

				$deliveryDateTime = $this->skipHolidays($deliveryDateTime, $holidays);
			}

			// Stamp end time
			$actualTime = new Carbon($deliveryDateTime);
			$endTime = new Carbon($actualTime->setTime($this->endHour, $this->endMinute, 0));

			$deliveryDateTime->addMinutes($remainingTime);

		// Workdays are less than one
		} else {

			// Stamp end time
			$actualTime = new Carbon($deliveryDateTime);
			$endTime = new Carbon($actualTime->setTime($this->endHour, $this->endMinute, 0));

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
	 * @return array
	 */
    private function dateRange($first, $last, $step = '+1 day', $output_format = 'Y-m-d' ) {

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
	 * Checks date for holidays, adds days and returns the new date
	 *
	 * @param Carbon\Carbon date time object
	 * @param array holidays
	 * @param boolean setStartTime
	 * @param integer startHour
	 * @param integer startMinute
	 * @return Carbon\Carbon date time object
	 */
	private function skipHolidays($dateTime, $holidays, $setStartTime = false) {

		while ($dateTime->isWeekend() || in_array($dateTime->toDateString(), $holidays)) {
			$dateTime->addDays(1);

			if ($setStartTime)
				$dateTime->setTime($this->startHour, $this->startMinute, 0);
		}

		return $dateTime;
	}

	/**
	 * Get Holidays from holidays table
	 *
	 * @return array holidays
	 */
	private function getHolidays() {
		// Load holidays from db in array
		$holidays = [];
    	$holidayPeriods = Holiday::all();

    	foreach ($holidayPeriods as $holidayPeriod) {
	    	$holidays[] = $this->dateRange($holidayPeriod->start_date, $holidayPeriod->end_date);
		}    	

		return array_reduce($holidays, 'array_merge', array()); // Turn into one-dimensional array
	}
}
