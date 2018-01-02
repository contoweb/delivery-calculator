# Delivery Calculator for Laravel

Calculate a date-time by providing a start date-time and a duration in hours considering weekends and defined holidays.

## Getting Started

The package is also available on Packagist:
* <https://packagist.org/packages/contoweb/delivery-calculator>

### Installing

With composer
```
composer require contoweb/delivery-calculator
```

Migrate Holiday table
```
php artisan migrate
```

### How to use

Function 1: Calculate the end date-time with start-date and duration
```
// Send Carbon\Carbon date-time object and $duration as integer
$deliveryDateTime = $newDeliveryTime->getDeliveryTime(Carbon::now(), $duration); 
```

Function 2: Given date is in business time?
```
// Send Carbon\Carbon date-time object
$isBusinessTime = $newDeliveryTime->isBusinessTime(Carbon::now());
```

Input a start_date (eg. `2017-12-24`) and end_date (eg. `2017-12-26`) into Holiday table to manage your holidays.
For a single holiday just input the same date for both fields. 

## Built With

* [Laravel](https://laravel.com/) - The web framework used

## Version

1.0

## Authors

* [contoweb](https://contoweb.ch) - *Initial work*