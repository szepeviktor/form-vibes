<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\Utils;
use FormVibes\Classes\Settings;

abstract class Base
{

    protected $plugin_name;
    protected  $ip;
    private $params = [];

    /**
     * @param $args
     */
    public function make_entry($data)
    {

        $args = [
            'post_type'   =>  'fv_leads',
            'post_status' =>  'publish'
        ];

        // Insert Post
        $post_id = wp_insert_post($args);

        // Add Meta Data
        $this->add_meta_entries($post_id, $data);
    }

    public function get_submissions($params)
    {

        $forms = [];
        $data['forms_plugin'] = apply_filters('fv_forms', $forms);
        $export = false;
        $query_filters = [];
        $filter_relation = "OR";
        $export_profile = false;
        $offset = 0;
        $limit = 0;
        if (array_key_exists('export', $params)) {
            $export = $params['export'];
        }
        if (array_key_exists("query_filters", $params)) {
            $query_filters = $params['query_filters'];
        }
        if (array_key_exists("filter_relation", $params)) {
            $filter_relation = $params['filter_relation'];
        }
        if (array_key_exists("export_profile", $params)) {
            $export_profile = $params['export_profile'];
        }
        if (array_key_exists("offset", $params)) {
            $offset = $params['offset'];
        }
        if (array_key_exists("limit", $params)) {
            $limit = $params['limit'];
        }
        $temp_params = [
            'plugin' => $params['plugin'],
            'per_page' => $params['per_page'],
            'page_num' => $params['page_num'] == '' ? 1 : $params['page_num'],
            'form_id' =>  $params['formid'],
            'queryType' => $params['query_type'],
            'fromDate' => $params['fromDate'],
            'toDate' => $params['toDate'],
            'export' => $export,
            'query_filters' => $query_filters,
            'filter_relation' => $filter_relation,
            'export_profile' => $export_profile,
            'offset' => $offset,
            'limit' => $limit,
        ];
        $data = self::get_data($temp_params);
        return $data;
    }
    static function get_data($params)
    {
        global $wpdb;
        $settings = get_option('fvSettings');
        list($saveIp, $saveUserAgent) = self::is_save_ip_user_agent($settings);

        // Start Entry Query
        $query_cols = ["entry.id", "entry.url", "DATE_FORMAT(captured, '%Y/%m/%d %H:%i:%S') as captured,form_id,form_plugin"];
        if ($saveUserAgent) {
            $query_cols[] = 'entry.user_agent';
        }
        $entry_query = "SELECT distinct " . implode(",", $query_cols) . " FROM {$wpdb->prefix}fv_enteries as entry";
        // Add Joins
        $joins = ['INNER JOIN ' . $wpdb->prefix . 'fv_entry_meta as e1 ON (entry.id = e1.data_id )'];
        $joins = apply_filters('formvibes/submissions/query/join', $joins, $params);
        $entry_query = $entry_query . ' ' . implode(' ', $joins);
        // Where Clauses
        $where[] = "1 = 1";
        $where = apply_filters('formvibes/submissions/query/where', $where, $params);
        // Form Plugin and Form Id
        if ($params['plugin'] !== '' && $params['plugin'] !== null) {
            $where[] = "form_plugin='" . $params['plugin'] . "'";
            if ($params['form_id'] !== '' && $params['form_id'] !== null) {
                $where[] = "form_id='" . $params['form_id'] . "'";
            }
        }
        // Date Conditions 
        if ($params['fromDate'] !== '' && $params['fromDate'] !== null) {
            $where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
        }
        if ($params['toDate'] !== '' && $params['toDate'] !== null) {
            if ($params['fromDate'] !== '') {
                $where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
            } else {
                $where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
            }
        }
        // Order By
        $orderby = " order by captured desc";
        // Limit 
        $limit = '';
        if ($params['export'] == false) {
            if ($params['page_num'] > 1) {
                $limit = ' limit ' . $params['per_page'] * ($params['page_num'] - 1) . ',' . $params['per_page'];
            } else {
                $limit = ' limit ' . $params['per_page'];
            }
        } else {
            if ($params['export_profile'] == true) {
                $limit = ' limit ' . $params['limit'] . ' OFFSET ' . $params['offset'];
            }
        }
        if ($params['export'] == true) {
            if ($params['export_profile'] == true) {
                $entry_query .= "WHERE " . implode(' and ', $where) . $orderby . $limit;
            } else {
                // Quick Export Query.
                $export_limit = apply_filters("formvibes/quickexport/export_limit", 1000);
                $export_limit_str = 'LIMIT ' . $export_limit;
                if (!$export_limit) {
                    $export_limit_str = '';
                }
                $entry_query .= "WHERE " . implode(' and ', $where) . $orderby . ' ' . $export_limit_str;
            }
            // print_r($entry_query);
            // die();
        } else {
            $entry_query .= "WHERE " . implode(' and ', $where) . $orderby . $limit;
        }
        // print_r($entry_query);
        // die();

        $entry_result = $wpdb->get_results($entry_query, ARRAY_A);

        $data = [];
        foreach ($entry_result as $key => $value) {
            $data[$value['id']]['url'] = $value['url'];
            $data[$value['id']]['captured'] = $value['captured'];
            if ($saveUserAgent) {
                $data[$value['id']]['user_agent'] = $value['user_agent'];
            }
        }


        if (count(array_keys($data)) > 0) {
            $entry_meta_query = "SELECT meta_key,meta_value,data_id FROM {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode(",", array_keys($data)) . ") AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
            if ($saveIp == false) {
                $entry_meta_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
            }
            $entry_metas = $wpdb->get_results($entry_meta_query, ARRAY_A);

            foreach ($entry_metas as $key => $value) {
                $data[$value['data_id']][$value['meta_key']] = $value['meta_value'];
            }

            // $count_where = $where;
            // array_splice($count_where, 1, 1);
            //$entry_count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries as entry " . implode(' ', $joins) . " WHERE " . implode(' and ', $where) . $orderby;
            $entry_count_query = "SELECT COUNT(distinct(e1.data_id)) FROM {$wpdb->prefix}fv_enteries as entry " . implode(' ', $joins) . " WHERE " . implode(' and ', $where) . $orderby;
            // echo $entry_count_query;
            // die();
            $entry_count = $wpdb->get_var($entry_count_query);

            $distinct_cols_query = "select distinct BINARY(meta_key) from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='" . $params['form_id'] . "' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
            if ($saveIp == false) {
                $distinct_cols_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
            }
            $columns = $wpdb->get_col($distinct_cols_query);
            // print_r($distinct_cols_query);
            // die();
            if ($params['export'] != true) {
                if ($entry_count > 0) {
                    array_push($columns, 'captured');
                    array_push($columns, 'url');
                    if ($saveUserAgent) {
                        array_push($columns, 'user_agent');
                    }
                }
            }
            $original_columns = $columns;
            $columns = Utils::prepare_table_columns($columns, $params['plugin'], $params['form_id']);
            return [
                'submissions' => $data,
                'total_submission_count' => $entry_count,
                'columns' => $columns,
                'original_columns' => $original_columns,
            ];
        } else {
            $entry_count = 0;
            //TODO:: handle no data here.
            $distinct_cols_query = "select distinct BINARY(meta_key) from {$wpdb->prefix}fv_entry_meta em join {$wpdb->prefix}fv_enteries e on em.data_id=e.id where form_id='" . $params['form_id'] . "' AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
            if ($saveIp == false) {
                $distinct_cols_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
            }
            $columns = $wpdb->get_col($distinct_cols_query);

            if ($params['export'] != true) {
                if ($entry_count > 0) {
                    array_push($columns, 'captured');
                    array_push($columns, 'url');
                    if ($saveUserAgent) {
                        array_push($columns, 'user_agent');
                    }
                }
            }

            return [
                'submissions' => [],
                'total_submission_count' => 0,
                'columns' => $columns,
                'original_columns' => $columns,
            ];
        }
    }



    static function get_tbl_data($params)
    {
        global $wpdb;

        $settings = get_option('fvSettings');

        list($saveIp, $saveUserAgent) = self::is_save_ip_user_agent($settings);


        // Start Entry Query
        $query_cols = ["entry.id", "entry.url", "DATE_FORMAT(captured, '%Y/%m/%d %H:%i:%S') as captured,form_id,form_plugin"];

        if ($saveUserAgent) {
            $query_cols[] = 'entry.user_agent';
        }

        $entry_query = "SELECT distinct " . implode(",", $query_cols) . " FROM {$wpdb->prefix}fv_enteries as entry";

        // Add Joins
        $joins = ['INNER JOIN ' . $wpdb->prefix . 'fv_entry_meta as e1 ON (entry.id = e1.data_id )'];

        $joins = apply_filters('formvibes/submissions/query/join', $joins, $params);

        $entry_query = $entry_query . ' ' . implode(' ', $joins);

        // Where Clauses

        $where[] = "1 = 1";
        $where = apply_filters('formvibes/submissions/query/where', $where, $params);

        // Form Plugin and Form Id
        if ($params['plugin'] !== '' && $params['plugin'] !== null) {
            $where[] = "form_plugin='" . $params['plugin'] . "'";
            if ($params['form_id'] !== '' && $params['form_id'] !== null) {
                $where[] = "form_id='" . $params['form_id'] . "'";
            }
        }

        // Date Conditions 
        if ($params['fromDate'] !== '' && $params['fromDate'] !== null) {
            $where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
        }
        if ($params['toDate'] !== '' && $params['toDate'] !== null) {
            if ($params['fromDate'] !== '') {
                $where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
            } else {
                $where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
            }
        }

        // Order By
        $orderby = " order by captured desc";

        // Limit 
        $limit = '';
        if ($params['export'] == false) {
            if ($params['page_num'] > 1) {
                $limit = ' limit ' . $params['per_page'] * ($params['page_num'] - 1) . ',' . $params['per_page'];
            } else {
                $limit = ' limit ' . $params['per_page'];
            }
        }

        //print_r($where);
        if ($params['export'] == true) {
            $entry_query .= "WHERE " . implode(' and ', $where) . $orderby;
        } else {
            $entry_query .= "WHERE " . implode(' and ', $where) . $orderby . $limit;
        }
        $entry_result = $wpdb->get_results($entry_query, ARRAY_A);
        $data = [];
        foreach ($entry_result as $key => $value) {
            $data[$value['id']]['url'] = $value['url'];
            $data[$value['id']]['captured'] = $value['captured'];
        }
        $entry_meta_query = "SELECT meta_key,meta_value,data_id FROM {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode(",", array_keys($data)) . ") AND meta_key != 'fv_form_id' AND meta_key != 'fv_plugin'";
        if ($saveIp == false) {
            $entry_meta_query .= " AND meta_key != 'fv_ip' AND meta_key != 'IP'";
        }
        $entry_metas = $wpdb->get_results($entry_meta_query, ARRAY_A);

        foreach ($entry_metas as $key => $value) {
            $data[$value['data_id']][$value['meta_key']] = $value['meta_value'];
        }

        return $data;
        // echo "<pre>";
        // print_r($data);
        // die();
    }

    private function add_meta_entries($post_id, $data)
    {
        foreach ($data['posted_data'] as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    public function set_user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $temp_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);;
            $ip = $temp_ip[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    function insert_enteries($enteries)
    {

        //TODO :: Check exclude form


        $inserted_forms = get_option('fv_forms');

        if ($inserted_forms == false) {
            $inserted_forms = [];
        }
        $forms = [];

        if (array_key_exists($enteries['plugin_name'], $inserted_forms)) {
            $forms = $inserted_forms[$enteries['plugin_name']];

            $forms[$enteries['id']] = [
                'id' => $enteries['id'],
                'name' => $enteries['title']
            ];
        } else {
            $forms[$enteries['id']] = [
                'id' => $enteries['id'],
                'name' => $enteries['title']
            ];
        }
        $inserted_forms[$enteries['plugin_name']] = $forms;

        update_option('fv_forms', $inserted_forms);


        global $wpdb;
        $entry_data = array(
            'form_plugin' => $enteries['plugin_name'],
            'form_id' => $enteries['id'],
            'captured' => $enteries['captured'],
            'captured_gmt' => $enteries['captured_gmt'],
            'url' => $enteries['url'],
        );


        $settings = Settings::instance();
        $dbSettings      = $settings->get();
        $saveUA = $dbSettings['userAgent'];

        if ($saveUA == 'yes' || $saveUA) {
            $entry_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $enteries['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        } else {
            $entry_data['user_agent'] = '';
        }


        $wpdb->insert(
            $wpdb->prefix . 'fv_enteries',
            $entry_data
        );
        $insert_id = $wpdb->insert_id;
        if ($insert_id != 0) {
            $this->insert_fv_entry_meta($insert_id, $enteries['posted_data']);

            return $insert_id;
        }
    }

    function insert_fv_entry_meta($insert_id, $enteries)
    {
        global $wpdb;

        foreach ($enteries as $key => $value) {
            $wpdb->insert(
                $wpdb->prefix . 'fv_entry_meta',
                array(
                    'data_id' => $insert_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                )
            );
        }
        $insert_id_meta = $wpdb->insert_id;
        if ($insert_id_meta > 1) {
        } else {
            write_log("==============Entry Failed===============");
        }
    }

    static function get_forms($param)
    {
    }

    static function delete_entries($ids)
    {
        global $wpdb;
        $message = [];
        $deleteRowQuery1 = "Delete from {$wpdb->prefix}fv_enteries where id IN (" . implode(",", $ids) . ")";
        $deleteRowQuery2 = "Delete from {$wpdb->prefix}fv_entry_meta where data_id IN (" . implode(",", $ids) . ")";

        $dl1 = $wpdb->query($deleteRowQuery1);
        $dl2 = $wpdb->query($deleteRowQuery2);

        if ($dl1 == 0 || $dl2 == 0) {
            $message['status'] = "failed";
            $message['message'] = "Could not able to delete Entries";
        } else {
            $message['status'] = "passed";
            $message['message'] = "Entries Deleted";
        }

        wp_send_json($message);
    }

    function save_options($params)
    {
        $forms_data = $params['columns'];
        $formName = $params['form'];
        $pluginName = $params['plugin'];
        $key = $params['key'];
        $saved_data = get_option('fv-keys');
        $data = $saved_data;
        $data[$pluginName . '_' . $formName] = $forms_data;
        update_option($key, $data, false);
        wp_send_json($this->get_fv_keys());
    }

    private function get_fv_keys()
    {
        $temp = get_option('fv-keys');
        if ($temp === "" || $temp === false) return [];
        $fv_keys = [];
        foreach ($temp as $key => $value) {
            foreach ($value as $val_key => $val_val) {
                $fv_keys[$key][$val_val['colKey']] = $val_val;
            }
        }
        return $fv_keys;
    }
    public function get_analytics($params)
    {
        $filterType = $params['filter_type'];
        $pluginName = $params['plugin'];
        $fromDate = $params['fromDate'];
        $toDate = $params['toDate'];
        $filter = '';
        $formid = $params['formid'];
        $label = "";
        $query_param = "";
        if ($filterType == 'day') {
            $default_data = self::getDatesFromRange($fromDate, $toDate);
            $filter = '%j';
            $label = "MAKEDATE(DATE_FORMAT(`captured`, '%Y'), DATE_FORMAT(`captured`, '%j'))";
        } else if ($filterType == 'month') {
            $default_data = self::getMonthRange($fromDate, $toDate);
            $filter = '%b';
            $label = "concat(DATE_FORMAT(`captured`, '%b'),'(',DATE_FORMAT(`captured`, '%y'),')')";
        } else {
            $default_data = self::getDateRangeForAllWeeks($fromDate, $toDate);
            $start_week = get_option('start_of_week');
            if ($start_week == 0) {
                $filter = '%U';
                $dayStart = 'Sunday';
                $weekNumber = '';
            } else {
                $filter = '%u';
                $dayStart = 'Monday';
                $weekNumber = '-1';
            }
            $label = "STR_TO_DATE(CONCAT(DATE_FORMAT(`captured`, '%Y'),' ', DATE_FORMAT(`captured`, '" . $filter . "')" . $weekNumber . ",' ', '" . $dayStart . "'), '%X %V %W')";
        }
        if ($filter == '%b') {
            $orderby = '%m';
        } else {
            $orderby = $filter;
        }
        global $wpdb;
        $param_where = [];
        $param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $fromDate . "'";;
        $param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $toDate . "'";
        $param_where[] = "form_plugin='" . $pluginName . "'";
        $param_where[] = "form_id='" . $formid . "'";
        $query_param = " Where " . implode(' and ', $param_where);
        $data_query = "SELECT " . $label . " as Label,CONCAT(DATE_FORMAT(`captured`, '" . $filter . "'),'(',DATE_FORMAT(`captured`, '%y'),')') as week, count(*) as count,CONCAT(DATE_FORMAT(`captured`, '%y'),'-',DATE_FORMAT(`captured`, '" . $orderby . "')) as ordering from {$wpdb->prefix}fv_enteries " . $query_param . " GROUP BY DATE_FORMAT(`captured`, '" . $orderby . "'),ordering ORDER BY ordering";
        $res['data'] = $wpdb->get_results($data_query, OBJECT_K);
        if (count((array)$res['data']) > 0) {
            $key = array_keys($res['data'])[0];
            if ($res['data'][$key]->Label == null || $res['data'][$key]->Label == '') {
                $abc[array_keys($default_data)[0]] = (object) $res['data'][""];
                $res['data'] = $abc + $res['data'];
                $res['data'][array_keys($default_data)[0]]->Label = array_keys($default_data)[0];
                unset($res['data'][""]);
            }
        }
        $data = array_replace($default_data, $res['data']);
        if (array_key_exists('dashboard_data', $params) && $params['dashboard_data']) {
            $dashboard_data = $this->prepare_data_for_dashboard_widget($params, $res);
            $data['dashboard_data'] = $dashboard_data;
        }
        return $data;
    }

    private function prepare_data_for_dashboard_widget($params, $res)
    {
        $allForms = [];
        $dashboard_data = [];
        for ($i = 0; $i < count($params['allForms']); ++$i) {
            $plugin = $params['allForms'][$i]['label'];
            for ($j = 0; $j < count($params['allForms'][$i]['options']); ++$j) {
                $id = $params['allForms'][$i]['options'][$j]['value'];
                $formName = $params['allForms'][$i]['options'][$j]['label'];
                $allForms[$id] = array(
                    'id'  => $id,
                    'plugin' => $plugin,
                    'formName' => $formName
                );
            }
        }
        if ($params['query_type'] == 'Last_7_Days' || $params['query_type'] == 'This_Week') {
            $preFromDate = date('Y-m-d', strtotime($params['fromDate'] . "-7 days"));
            $preToDate = date('Y-m-d', strtotime($params['fromDate'] . "-1 days"));
        } else if ($params['query_type'] == 'Last_30_Days') {
            $preFromDate = date('Y-m-d', strtotime($params['fromDate'] . "-30 days"));
            $preToDate = date('Y-m-d', strtotime($params['fromDate'] . "-1 days"));
        } else {
            $preFromDate = date('Y-m-01', strtotime("first day of last month"));
            $preToDate = date('Y-m-t', strtotime("last day of last month"));
        }
        global $wpdb;
        $preParam = " where form_id='" . $params['formid'] . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $preFromDate . "'";
        $preParam .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $preToDate . "'";
        $preDataCount = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $preParam);
        foreach ($allForms as $formKey => $formValue) {
            if ($formValue['plugin'] == 'Caldera') {
                $param = " where form_id='" . $formKey . "' and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
                $param .= " and DATE_FORMAT(datestamp,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
                $data_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cf_form_entries " . $param);

                $dashboard_data['allFormsDataCount'][$formKey] = [
                    'plugin' =>  $formValue['plugin'],
                    'count' =>  $data_count,
                    'formName' =>  $formValue['formName'],
                ];
            } else {
                $param = " where form_id='" . $formKey . "' and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $params['fromDate'] . "'";
                $param .= " and DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $params['toDate'] . "'";
                $data_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries " . $param);

                $dashboard_data['allFormsDataCount'][$formKey] = [
                    'plugin' =>  $formValue['plugin'],
                    'count' =>  $data_count,
                    'formName' =>  $formValue['formName'],
                ];
            }
        }
        $totalEntries = 0;
        foreach ($res['data'] as $key => $val) {
            $totalEntries += $val->count;
        }
        $dashboard_widget_setting = [];
        $dashboard_widget_setting['query_type'] = $params['query_type'];
        $dashboard_widget_setting['plugin'] = $params['plugin'];
        $dashboard_widget_setting['formid'] = $params['formid'];
        update_option('fv_dashboard_widget_settings', $dashboard_widget_setting);
        $dashboard_data['totalEntries'] = $totalEntries;
        $dashboard_data['previousDateRangeDataCount'] = $preDataCount;
        return $dashboard_data;
    }

    // TODO :: move it to utils.
    static function getDatesFromRange($start, $end, $format = 'Y-m-d')
    {
        //$Date1 = '05-10-2010';
        $Date1 = $start;
        $Date2 = $end;

        // Declare an empty array
        $array = array();

        // Use strtotime function
        $Variable1 = strtotime($Date1);
        $Variable2 = strtotime($Date2);

        // Use for loop to store dates into array
        // 86400 sec = 24 hrs = 60*60*24 = 1 day
        for (
            $currentDate = $Variable1;
            $currentDate <= $Variable2;
            $currentDate += (86400)
        ) {

            $Store = date('Y-m-d', $currentDate);

            $array[$Store] = (object)[
                'Label' => $Store,
                'week' => (date('z', $currentDate) + 1) . '(' . date('y', $currentDate) . ')',
                'count' => 0,
                'ordering' => date('y', $currentDate) . '-' . (date('z', $currentDate) + 1),
            ];
        }
        $array[] = new \stdClass;
        unset($array[0]);
        return $array;
    }
    static function getMonthRange($startDate, $endDate)
    {
        $start    = new \DateTime($startDate);
        $start->modify('first day of this month');
        $end      = new \DateTime($endDate);
        $end->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        $months = [];
        foreach ($period as $dt) {
            $months[$dt->format("M") . '(' . $dt->format("y") . ')'] = (object)[
                'Label' => $dt->format("M") . '(' . $dt->format("y") . ')',
                'week' => '',
                'count' => 0,
                'ordering' => '',
            ];
        }

        return $months;
    }
    static function getDateRangeForAllWeeks($start, $end)
    {
        $fweek = self::getDateRangeForWeek($start);
        $lweek = self::getDateRangeForWeek($end);

        $week_dates = [];

        while ($fweek['sunday'] < $lweek['sunday']) {
            $week_dates[$fweek['monday']] = (object)[
                'Label' => $fweek['monday'],
                'week' => '',
                'count' => 0,
                'ordering' => '',
            ];;
            $date = new \DateTime($fweek['sunday']);
            $date->modify('next day');

            $fweek = self::getDateRangeForWeek($date->format("Y-m-d"));
        }
        $week_dates[$lweek['monday']] = (object)[
            'Label' => $lweek['monday'],
            'week' => '',
            'count' => 0,
            'ordering' => '',
        ];

        //print_r($week_dates);
        return $week_dates;
    }
    static function getDateRangeForWeek($date)
    {
        $dateTime = new \DateTime($date);

        if ('Monday' == $dateTime->format('l')) {
            $monday = date('Y-m-d',  strtotime($date));
        } else {
            $monday = date('Y-m-d', strtotime('last monday', strtotime($date)));
        }

        $sunday = 'Sunday' == $dateTime->format('l') ? date('Y-m-d', strtotime($date)) : date('Y-m-d', strtotime('next sunday', strtotime($date)));

        return ['monday' => $monday, 'sunday' => $sunday];
    }

    static function is_save_ip_user_agent($settings)
    {
        $saveUserAgent = '';
        if ($settings == false) {
            $saveIp = get_option('fv-ip-save');
            if ($saveIp === false) {
                $saveIp = true;
            }
        } else {
            $saveIp = $settings['ip'];
            $saveUserAgent = $settings['userAgent'];
        }

        return [$saveIp, $saveUserAgent];
    }
}
