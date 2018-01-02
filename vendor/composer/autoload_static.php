<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8377628da85a455be295e4b3436eeeda
{
    public static $prefixLengthsPsr4 = array (
        'C' => 
        array (
            'Contoweb\\DeliveryCalculator\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Contoweb\\DeliveryCalculator\\' => 
        array (
            0 => __DIR__ . '/../..' . '/packages/contoweb/delivery-calculator/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8377628da85a455be295e4b3436eeeda::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8377628da85a455be295e4b3436eeeda::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
