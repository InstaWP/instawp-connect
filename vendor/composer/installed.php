<?php return array(
    'root' => array(
        'name' => 'instawp/connect',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '58322ced1e8926380e56753edcb3119ec6070df9',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'instawp/connect' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '58322ced1e8926380e56753edcb3119ec6070df9',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'instawp/connect-helpers' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'bc01df95c8aa76a31c619839b87d1fdc8db356cf',
            'type' => 'library',
            'install_path' => __DIR__ . '/../instawp/connect-helpers',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'wp-cli/wp-config-transformer' => array(
            'pretty_version' => 'v1.3.5',
            'version' => '1.3.5.0',
            'reference' => '202aa80528939159d52bc4026cee5453aec382db',
            'type' => 'library',
            'install_path' => __DIR__ . '/../wp-cli/wp-config-transformer',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
