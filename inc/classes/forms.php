<?php

namespace FormVibes\Classes;

class Forms{

    private static $_instance = null;

    static $forms = [];

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct(){

        self::$forms = $this->get_all_forms();
    }

    function get_all_forms(){

        // get forms saved in options
        $forms = get_option('fv_forms');

        $forms = apply_filters('formvibes/forms', $forms);

        return $forms;
    }

}