# Delivery Calculator for Laravel

Calculate a date-time by providing a start date-time and a duration in hours considering weekends and defined holidays.

## Getting Started

The package is available on Packagist and GitHub:
* <https://packagist.org/packages/contoweb/delivery-calculator>
* <https://github.com/contoweb/delivery-calculator>

### Installing

With composer
```
composer require contoweb/delivery-calculator
```

Migrate holidays table
```
php artisan migrate
```

### How to use

Use class
```
use Contoweb\DeliveryCalculator\DeliveryCalculator;
```

Initialize with start (eg. 05:45) and end time (eg. 23:00)
```
$newDeliveryCalculation = new DeliveryCalculator(5, 45, 23, 0);
```

Function 1: Calculate the end date-time with start-date (Carbon date) and duration (integer in hours)
```
$deliveryDateTime = $newDeliveryCalculation->getDeliveryTime(Carbon::now(), $duration); 
```

Function 2: Given date (Carbon) is in business time?
```
$isBusinessTime = $newDeliveryCalculation->isBusinessTime(Carbon::now());
```

#### Insert holidays

Input a start_date (eg. `2017-12-24`) and end_date (eg. `2017-12-26`) into Holiday table to manage your holidays.
For a single holiday just input the same date for both fields. 

## Built With

* [Laravel](https://laravel.com/) - The web framework used

## Version

1.0

## Authors

* [contoweb AG](https://contoweb.ch) - *Initial work*