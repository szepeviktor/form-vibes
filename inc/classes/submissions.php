<?php

namespace FormVibes\Classes;

class Submissions
{

    // $source => Plugin class name
    protected $source;

    function __construct($source)
    {
        // print($source);
        // die();
        if ($source !== '' && $source !== 'noformsplugin') {
            $source_class = '\\FormVibes\\Integrations\\' . Utils::dashesToCamelCase($source);
            // print_r($source_class);
            // die();
            $this->source = $source_class::instance();
        }
    }

    function get_submissions($params)
    {
        // print_r($params);
        // die();
        if ($params['plugin'] === 'noformsplugin' && $params['formid'] === 'noformsfound') {
            return [
                'submissions' => [],
                'total_submission_count' => 0
            ];
        }
        $dates = Utils::getQueryDates($params['query_type'], $params);
        $params['fromDate'] = $dates[0]->format('Y-m-d');
        $params['toDate'] = $dates[1]->format('Y-m-d');
        $submissions = $this->source->get_submissions($params);
        return $submissions;
    }

    function get_analytics($params)
    {
        $dates = Utils::getQueryDates($params['query_type'], $params);
        $params['fromDate'] = $dates[0]->format('Y-m-d');
        $params['toDate'] = $dates[1]->format('Y-m-d');
        $analytics = $this->source->get_analytics($params);
        return $analytics;
    }

    public function fv_get_logs_data($params)
    {
        $gmt_offset =  get_option('gmt_offset');
        $hours   = (int) $gmt_offset;
        $minutes = ($gmt_offset - floor($gmt_offset)) * 60;
        if ($hours >= 0) {
            $time_zone = $hours . ':' . $minutes;
        } else {
            $time_zone = $hours . ':' . $minutes;
        }
        $limit = '';
        if ($params['page'] > 1) {
            $limit = ' limit ' . $params['pageSize'] * ($params['page'] - 1) . ',' . $params['pageSize'];
        } else {
            $limit = ' limit ' . $params['pageSize'];
        }
        global $wpdb;
        $entry_query = "select @a:=@a+1 serial_number, l.id,l.user_id,u.user_login,event,description,DATE_FORMAT(ADDTIME(export_time_gmt,'" . $time_zone . "' ), '%Y/%m/%d %H:%i:%S') as export_time_gmt from {$wpdb->prefix}fv_logs l LEFT JOIN {$wpdb->prefix}users u on l.user_id=u.ID, (SELECT @a:= 0) AS a ORDER BY id desc" . $limit;
        $entry_result = $wpdb->get_results($entry_query, ARRAY_A);
        $entry_count_query = "select count(id) from {$wpdb->prefix}fv_logs l ORDER BY id desc";
        $entry_count_result = $wpdb->get_var($entry_count_query);
        foreach ($entry_result as $key => $value) {
            $user_meta = get_user_meta($value['user_id']);
            $entry_result[$key]['user'] = $user_meta['first_name'][0] . ' ' . $user_meta['last_name'][0];
        }
        $results = [
            'count' => $entry_count_result,
            'data' => $entry_result,
        ];
        return $results;
    }

    function save_options($params)
    {
        $options = $this->source->save_options($params);
    }
}
