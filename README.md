# Delivery Calculator for Laravel

Calculate a DateTime by providing a start date-time and a duration in hours considering business hours, weekends and defined holidays.

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

Load the class
```
use Contoweb\DeliveryCalculator\DeliveryCalculator;
```

Initialize business hours with start (eg. 05:45) and end time (eg. 23:00)
```
$deliveryCalculator = new DeliveryCalculator(5, 45, 23, 0);
```

Function 1: Calculate a delivery DateTime by given start DateTime (Carbon date) and the delivery duration (integer in hours)
```
$deliveryDateTime = $deliveryCalculator->getDeliveryTime(Carbon::now(), $duration); 
```

Function 2: Given date (Carbon) is in business time?
```
$isBusinessTime = $deliveryCalculator->isBusinessTime(Carbon::now());
```

#### Define holidays

Enter a start_date (eg. `2017-12-24`) and end_date (eg. `2017-12-26`) into the `holidays` table to define holidays.
For a single holiday just enter the same date for both fields. 

## Built With

* [Laravel](https://laravel.com/) - The web framework used

## Version

1.0

## Authors

* [contoweb AG](https://contoweb.ch) - *Initial work*
