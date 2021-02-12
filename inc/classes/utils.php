<?php

namespace FormVibes\Classes;

use Carbon\Carbon;
use Stripe\Util\Util;

class Utils
{

    static public function dashesToCamelCase($string, $capitalizeFirstCharacter = true)
    {
        $str = str_replace('-', '', ucwords($string, '-'));
        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }
        return $str;
    }

    static function get_plugin_key_by_name($name)
    {
        if ($name == 'Contact Form 7') {
            return 'cf7';
        } else if ($name == 'Elementor Forms') {
            return 'elementor';
        } else if ($name == 'Beaver Builder') {
            return 'beaverBuilder';
        } else if ($name == 'WP Forms') {
            return 'wpforms';
        } else if ($name == 'Caldera') {
            return 'caldera';
        } else if ($name == 'Ninja Forms') {
            return 'ninja';
        }
        return $name;
    }

    static function getQueryDates($queryType, $param)
    {
        $gmt_offset =  get_option('gmt_offset');
        $hours   = (int) $gmt_offset;
        $minutes = ($gmt_offset - floor($gmt_offset)) * 60;

        if ($hours >= 0) {
            $time_zone = '+' . $hours . ':' . $minutes;
        } else {
            $time_zone = $hours . ':' . $minutes;
        }

        if ($queryType !== 'Custom') {
            $dates = self::get_date_interval($queryType, $time_zone);

            $fromDate = $dates['fromDate'];
            $toDate = $dates['endDate'];
        } else {
            $tz = new \DateTimeZone($time_zone);
            $fromDate = new \DateTime($param['fromDate']);
            $fromDate->setTimezone($tz);
            $toDate = new \DateTime($param['toDate']);
            $toDate->setTimezone($tz);
        }

        return [$fromDate, $toDate];
    }

    static function get_date_interval($queryType, $time_zone)
    {
        //$now = Carbon::now($time_zone);
        switch ($queryType) {
            case 'Today':
                $dates['fromDate'] = Carbon::now($time_zone);
                $dates['endDate'] = Carbon::now($time_zone);

                return $dates;
                break;

            case 'Yesterday':
                $dates['fromDate'] = Carbon::now($time_zone)->subDay();
                $dates['endDate'] = Carbon::now($time_zone)->subDay();

                return $dates;
                break;

            case 'Last_7_Days':
                $dates['fromDate'] = Carbon::now($time_zone)->subDays(6);
                $dates['endDate'] = Carbon::now($time_zone);

                return $dates;
                break;

            case 'This_Week':
                $start_week = get_option('start_of_week');
                if ($start_week != 0) {
                    $staticstart = Carbon::now($time_zone)->startOfWeek(Carbon::MONDAY);
                    $staticfinish = Carbon::now($time_zone)->endOfWeek(Carbon::SUNDAY);
                } else {
                    $staticstart = Carbon::now($time_zone)->startOfWeek(Carbon::SUNDAY);
                    $staticfinish = Carbon::now($time_zone)->endOfWeek(Carbon::SATURDAY);
                }
                $dates['fromDate'] = $staticstart;
                $dates['endDate'] = $staticfinish;
                return $dates;
                break;

            case 'Last_Week':
                $start_week = get_option('start_of_week');
                if ($start_week != 0) {
                    $staticstart        = Carbon::now($time_zone)->startOfWeek(Carbon::MONDAY)->subDays(7);
                    $staticfinish       = Carbon::now($time_zone)->endOfWeek(Carbon::SUNDAY)->subDays(7);
                } else {
                    $staticstart        = Carbon::now($time_zone)->startOfWeek(Carbon::SUNDAY)->subDays(7);
                    $staticfinish       = Carbon::now($time_zone)->endOfWeek(Carbon::SATURDAY)->subDays(7);
                }

                $dates['fromDate'] = $staticstart;
                $dates['endDate'] = $staticfinish;

                return $dates;
                break;

            case 'Last_30_Days':
                $dates['fromDate'] = Carbon::now($time_zone)->subDays(29);
                $dates['endDate'] = Carbon::now($time_zone);

                return $dates;
                break;

            case 'This_Month':
                $dates['fromDate'] = Carbon::now($time_zone)->startOfMonth();
                $dates['endDate'] = Carbon::now($time_zone)->endOfMonth();

                return $dates;
                break;

            case 'Last_Month':
                $dates['fromDate'] = Carbon::now($time_zone)->subMonth()->startOfMonth();
                $dates['endDate'] = Carbon::now($time_zone)->subMonth()->endOfMonth();

                return $dates;
                break;

            case 'This_Quarter':
                $dates['fromDate'] = Carbon::now($time_zone)->startOfQuarter();
                $dates['endDate'] = Carbon::now($time_zone)->endOfQuarter();

                return $dates;
                break;

            case 'Last_Quarter':
                $dates['fromDate'] = Carbon::now($time_zone)->subMonths(3)->startOfQuarter();
                $dates['endDate'] = Carbon::now($time_zone)->subMonths(3)->endOfQuarter();

                return $dates;
                break;

            case 'This_Year':
                $dates['fromDate'] = Carbon::now($time_zone)->startOfYear();
                $dates['endDate'] = Carbon::now($time_zone)->endOfYear();

                return $dates;
                break;

            case 'Last_Year':
                $dates['fromDate'] = Carbon::now($time_zone)->subMonths(12)->startOfYear();
                $dates['endDate'] = Carbon::now($time_zone)->subMonths(12)->endOfYear();

                return $dates;
                break;
        }
    }
    static function get_dates($queryType)
    {
        switch ($queryType) {
            case 'Today':
                $dates['fromDate'] = date("Y-m-d H:i:s");
                $dates['endDate'] = date("Y-m-d H:i:s");

                return $dates;
                break;

            case 'Yesterday':
                $dates['fromDate'] = date('Y-m-d H:i:s', strtotime("-1 days"));
                $dates['endDate'] = date('Y-m-d H:i:s', strtotime("-1 days"));

                return $dates;
                break;

            case 'Last_7_Days':
                $dates['fromDate'] = date('Y-m-d H:i:s', strtotime("-6 days"));
                $dates['endDate'] = date('Y-m-d H:i:s');

                return $dates;
                break;

            case 'This_Week':
                $start_week = get_option('start_of_week');
                if ($start_week != 0) {
                    if (date('D') != 'Mon') {
                        $staticstart = date('Y-m-d', strtotime('last Monday'));
                    } else {
                        $staticstart = date('Y-m-d');
                    }

                    if (date('D') != 'Sat') {
                        $staticfinish = date('Y-m-d', strtotime('next Sunday'));
                    } else {

                        $staticfinish = date('Y-m-d');
                    }
                } else {
                    if (date('D') != 'Sun') {
                        $staticstart = date('Y-m-d', strtotime('last Sunday'));
                    } else {
                        $staticstart = date('Y-m-d');
                    }

                    if (date('D') != 'Sat') {
                        $staticfinish = date('Y-m-d', strtotime('next Saturday'));
                    } else {

                        $staticfinish = date('Y-m-d');
                    }
                }
                $dates['fromDate'] = $staticstart;
                $dates['endDate'] = $staticfinish;
                return $dates;
                break;

            case 'Last_Week':
                $start_week = get_option('start_of_week');
                if ($start_week != 0) {
                    $previous_week = strtotime("-1 week +1 day");
                    $start_week    = strtotime("last monday midnight", $previous_week);
                    $end_week      = strtotime("next sunday", $start_week);
                } else {
                    $previous_week = strtotime("-1 week +1 day");
                    $start_week    = strtotime("last sunday midnight", $previous_week);
                    $end_week      = strtotime("next saturday", $start_week);
                }
                $start_week = date("Y-m-d", $start_week);
                $end_week = date("Y-m-d", $end_week);

                $dates['fromDate'] = $start_week;
                $dates['endDate'] = $end_week;

                return $dates;
                break;

            case 'Last_30_Days':
                $dates['fromDate'] = date('Y-m-d h:m:s', strtotime("-29 days"));
                $dates['endDate'] = date('Y-m-d h:m:s');

                return $dates;
                break;

            case 'This_Month':
                $dates['fromDate'] = date('Y-m-01');
                $dates['endDate'] = date('Y-m-t');

                return $dates;
                break;

            case 'Last_Month':
                //$dates['fromDate'] = date('Y-m-01',strtotime("-1 month"));
                //$dates['endDate'] = date('Y-m-t',strtotime("-1 month"));
                $dates['fromDate'] = date('Y-m-01', strtotime("first day of last month"));
                $dates['endDate'] = date('Y-m-t', strtotime("last day of last month"));

                return $dates;
                break;

            case 'This_Quarter':
                $current_month = date('m');
                $current_year = date('Y');
                if ($current_month >= 1 && $current_month <= 3) {
                    $start_date = strtotime('1-January-' . $current_year);  // timestamp or 1-Januray 12:00:00 AM
                    $end_date = strtotime('31-March-' . $current_year);  // timestamp or 1-April 12:00:00 AM means end of 31 March
                } else  if ($current_month >= 4 && $current_month <= 6) {
                    $start_date = strtotime('1-April-' . $current_year);  // timestamp or 1-April 12:00:00 AM
                    $end_date = strtotime('30-June-' . $current_year);  // timestamp or 1-July 12:00:00 AM means end of 30 June
                } else  if ($current_month >= 7 && $current_month <= 9) {
                    $start_date = strtotime('1-July-' . $current_year);  // timestamp or 1-July 12:00:00 AM
                    $end_date = strtotime('30-September-' . $current_year);  // timestamp or 1-October 12:00:00 AM means end of 30 September
                } else  if ($current_month >= 10 && $current_month <= 12) {
                    $start_date = strtotime('1-October-' . $current_year);  // timestamp or 1-October 12:00:00 AM
                    $end_date = strtotime('31-December-' . ($current_year));  // timestamp or 1-January Next year 12:00:00 AM means end of 31 December this year
                }

                $dates['fromDate'] = date('Y-m-d', $start_date);
                $dates['endDate'] = date('Y-m-d', $end_date);
                return $dates;
                break;

            case 'Last_Quarter':
                $current_month = date('m');
                $current_year = date('Y');

                if ($current_month >= 1 && $current_month <= 3) {
                    $start_date = strtotime('1-October-' . ($current_year - 1));  // timestamp or 1-October Last Year 12:00:00 AM
                    $end_date = strtotime('31-December-' . ($current_year - 1));  // // timestamp or 1-January  12:00:00 AM means end of 31 December Last year
                } else if ($current_month >= 4 && $current_month <= 6) {
                    $start_date = strtotime('1-January-' . $current_year);  // timestamp or 1-Januray 12:00:00 AM
                    $end_date = strtotime('31-March-' . $current_year);  // timestamp or 1-April 12:00:00 AM means end of 31 March
                } else  if ($current_month >= 7 && $current_month <= 9) {
                    $start_date = strtotime('1-April-' . $current_year);  // timestamp or 1-April 12:00:00 AM
                    $end_date = strtotime('30-June-' . $current_year);  // timestamp or 1-July 12:00:00 AM means end of 30 June
                } else  if ($current_month >= 10 && $current_month <= 12) {
                    $start_date = strtotime('1-July-' . $current_year);  // timestamp or 1-July 12:00:00 AM
                    $end_date = strtotime('30-September-' . $current_year);  // timestamp or 1-October 12:00:00 AM means end of 30 September
                }
                $dates['fromDate'] = date('Y-m-d', $start_date);
                $dates['endDate'] = date('Y-m-d', $end_date);
                return $dates;
                break;

            case 'This_Year':
                $dates['fromDate'] = date('Y-01-01');
                $dates['endDate'] = date('Y-12-t');

                return $dates;
                break;

            case 'Last_Year':
                $dates['fromDate'] = date('Y-01-01', strtotime("-1 year"));
                $dates['endDate'] = date('Y-12-t', strtotime("-1 year"));

                return $dates;
                break;
        }
    }

    static function get_first_plugin_form()
    {
        $forms = [];
        $plugins = apply_filters('fv_forms', $forms);

        $class = '\FormVibes\Integrations\\' . ucfirst(array_keys($plugins)[0]);

        $plugin_forms = $class::get_forms(array_keys($plugins)[0]);
        $plugin = array_keys($plugins)[0];

        //$form = $plugin_forms[0];

        $data = [
            'formName' => $plugin_forms,
            'selectedPlugin' => $plugin,
            'selectedForm' => array_keys($plugin_forms)[0],
        ];

        return $data;
    }

    static function get_settings()
    {
        $defaults = [
            'ip' => true,
            'userAgent' => false,
            'debugMode' => false,
            'exportReason' => false
        ];
        $settings = get_option('fvSettings');

        $settings = wp_parse_args($settings, $defaults);

        return $settings;
    }

    static function get_entry_table_fields()
    {
        $entry_table_fields = [
            'url',
            'user_agent',
            'fv_status',
            'captured',
        ];

        $entry_table_fields = apply_filters('formvibes/entry_table_fields', $entry_table_fields);

        return $entry_table_fields;
    }

    static function prepare_table_columns($columns, $plugin_name, $form_id, $type = 'submission')
    {
        $saved_columns = get_option('fv-keys');

        $col_label = 'Header';
        $col_key = 'accessor';

        if ($type === 'columns') {
            $col_label = 'alias';
            $col_key = 'colKey';
        }

        if ($saved_columns) {
            if (!array_key_exists($plugin_name . '_' . $form_id, $saved_columns)) {
                $cols = [];
                foreach ($columns as $column) {
                    $cols[] = (object) [
                        $col_label => $column === 'captured' || $column === 'datestamp' ? 'Submission Date' :   $column,
                        $col_key => $column,
                        'visible' => true,
                    ];
                }
                return $cols;
            }
        }

        $current_form_saved_columns = $saved_columns[$plugin_name . '_' . $form_id];

        if (empty($current_form_saved_columns)) {
            $cols = [];
            foreach ($columns as $column) {
                $cols[] = (object) [
                    $col_label => $column === 'captured' || $column === 'datestamp' ? 'Submission Date' :   $column,
                    $col_key => $column,
                    'visible' => true,
                ];
            }
            return $cols;
        }

        $cols = [];
        foreach ($current_form_saved_columns as $column) {
            if (in_array($column['colKey'], $columns))
                $cols[] = (object) [
                    $col_label => $column['alias'],
                    $col_key => $column['colKey'],
                    'visible' => $column['visible'],
                ];
        }

        foreach ($columns as $column) {
            $key = array_search($column, array_column($cols, $col_key), true);
            if ($key === false) {
                $cols[] = (object) [
                    $col_label => $column,
                    $col_key => $column,
                    'visible' => true,
                ];
            }
        }

        return $cols;
    }
}
