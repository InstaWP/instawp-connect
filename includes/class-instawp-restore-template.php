<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
return array(
    'task_id'     => '',
    'data'        => array(
        INSTAWP_BACKUP_TYPE_CORE    => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),

        INSTAWP_BACKUP_TYPE_DB      => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),

        INSTAWP_BACKUP_TYPE_CONTENT => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),

        INSTAWP_BACKUP_TYPE_PLUGIN  => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),

        INSTAWP_BACKUP_TYPE_UPLOADS => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),

        INSTAWP_BACKUP_TYPE_THEMES  => array(
            'state'  => INSTAWP_RESTORE_WAIT,
            'time'   => array(
                'start' => 0,
                'end'   => 0,
            ),
            'return' => array(
                'result' => '',
                'error'  => '',
            ),
            'table'  => array(
                'succeed'    => 0,
                'failed'     => 0,
                'unfinished' => 0,
            ),
        ),
    ),
    'state'       => INSTAWP_RESTORE_INIT,
    'error'       => '',
    'error_task'  => '',
    'backup_data' => '',
);