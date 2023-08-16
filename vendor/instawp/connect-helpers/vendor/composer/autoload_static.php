<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit129f2c3248d416d992d406eb81702bfc
{
    public static $files = array (
        'ac949ce40a981819ba132473518a9a31' => __DIR__ . '/..' . '/wp-cli/wp-config-transformer/src/WPConfigTransformer.php',
    );

    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'InstaWP\\Connect\\Helpers\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'InstaWP\\Connect\\Helpers\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'InstaWP\\Connect\\Helpers\\Cache' => __DIR__ . '/../..' . '/src/Cache.php',
        'InstaWP\\Connect\\Helpers\\DebugLog' => __DIR__ . '/../..' . '/src/DebugLog.php',
        'InstaWP\\Connect\\Helpers\\Installer' => __DIR__ . '/../..' . '/src/Installer.php',
        'InstaWP\\Connect\\Helpers\\Inventory' => __DIR__ . '/../..' . '/src/Inventory.php',
        'InstaWP\\Connect\\Helpers\\WPConfig' => __DIR__ . '/../..' . '/src/WPConfig.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit129f2c3248d416d992d406eb81702bfc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit129f2c3248d416d992d406eb81702bfc::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit129f2c3248d416d992d406eb81702bfc::$classMap;

        }, null, ClassLoader::class);
    }
}
