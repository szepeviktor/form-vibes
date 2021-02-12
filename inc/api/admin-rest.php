<?php

namespace FormVibes\Api;

use calderawp\calderaforms\pro\api\log;
use FormVibes\Classes\Analytics;
use FormVibes\Classes\Export;
use FormVibes\Classes\Settings;
use FormVibes\Classes\Submissions;
use FormVibes\Integrations\Base;
use WP_Error;
use WP_Query;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Controller;

class AdminRest extends WP_REST_Controller
{

    protected $namespace = 'formvibes/v1';

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Registers the routes for the objects of the controller.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/submissions',
            // Get Submissions -> done
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'view_submissions'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ],
                // Delete Submissions -> done
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_entries'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'delete_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ]
            ]
        );
        register_rest_route(
            $this->namespace,
            '/saveOption',
            // Save Option -> done
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'save_options'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/analytics',
            // Get Analytics done
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'view_analytics'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/settings',
            // Save Settings done
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'save_settings'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/event_logs',
            // Get Event logs Data done
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'fv_get_logs_data'],
                    'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
                    //'args'                => $this->get_save_module_args(),
                ],
            ]
        );

        // register_rest_route(
        //     $this->namespace,
        //     '/json_placeholder',
        //     // Get Event logs Data done
        //     [
        //         [
        //             'methods'             => WP_REST_Server::CREATABLE,
        //             'callback'            => [$this, 'json_placeholder'],
        //             'permission_callback' => ['\\FormVibes\\Classes\\Permissions', 'view_submissions'],
        //             //'args'                => $this->get_save_module_args(),
        //         ],
        //     ]
        // );
    }

    function json_placeholder($request)
    {
        wp_send_json([]);
        $args = array(
            'post_type' => 'post',
            'order' => 'DESC',
            // 'date_query' => array(
            //     array(
            //         'after'     => '2019-01-01',
            //         'before'    => '2020-12-31',
            //         'inclusive' => true,
            //     ),
            // ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'cell',
                    'value' => '0924-016-3935',
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'cell',
                    'value' => '(71) 2421-4928',
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'cell',
                    'value' => '628-138-404',
                    'compare' => 'LIKE'
                ),
            )
        );
        // Make the query
        $res = $query = new WP_Query($args);


        echo "hello";
        echo '<pre>';
        print_r($res);
        echo '</pre>';
        die();
        $params = $request->get_json_params();
        $count = (int) $params['count'];
        $count++;
        $fromDate = '2019-01-01';
        $toDate = '2021-12-31';
        $randomDate = $this->randomDate($fromDate, $toDate, 1);
        //echo "Call";
        //print_r($params['data']);
        $data = $params['data'];
        foreach ($data as $key => $value) {
            $title = '<h2>' . $value['name']['title'] . ' ' . $value['name']['first'] . ' ' . $value['last'] . '</h2>';
            $body = '<div>' . $value['email'] . '</div>';
            $phone = $value['phone'];
            $gender = $value['gender'];
            $cell = $value['cell'];
            $my_post = array(
                'post_title'    => $title,
                'post_content'  => $body,
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_category' => array(8, 39),
                'post_date' => $randomDate,
                'post_date_gmt' => $randomDate,
            );
            $post_id = wp_insert_post($my_post);
            update_post_meta($post_id, 'phone', $phone);
            update_post_meta($post_id, 'gender', $gender);
            update_post_meta($post_id, 'cell', $cell);
        }
        wp_send_json([
            'count' => (int) $count,
            'randomDate' => $randomDate,
        ]);
        die();
        // Create post object
        $my_post = array(
            'post_title'    => $params['title'],
            'post_content'  => $params['body'],
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_category' => array(8, 39),
            'post_date' => $randomDate,
            'post_date_gmt' => $randomDate,
        );

        // Insert the post into the database
        $post_id = wp_insert_post($my_post);
        update_post_meta($post_id, 'phone', $params['phone']);
        update_post_meta($post_id, 'gender', $params['gender']);
        update_post_meta($post_id, 'cell', $params['cell']);
        wp_send_json([
            'count' => (int) $count,
            'randomDate' => $randomDate,
        ]);
    }

    function randomDate($startDate, $endDate, $count = 1, $dateFormat = 'Y-m-d H:i:s')
    {
        //inspired by
        // https://gist.github.com/samcrosoft/6550473

        // Convert the supplied date to timestamp
        $minDateString = strtotime($startDate);
        $maxDateString = strtotime($endDate);

        if ($minDateString > $maxDateString) {
            throw new Exception("From Date must be lesser than to date", 1);
        }

        for ($ctrlVarb = 1; $ctrlVarb <= $count; $ctrlVarb++) {
            $randomDate[] = mt_rand($minDateString, $maxDateString);
        }
        if (sizeof($randomDate) == 1) {
            $randomDate = date($dateFormat, $randomDate[0]);
            return $randomDate;
        } elseif (sizeof($randomDate) > 1) {
            foreach ($randomDate as $randomDateKey => $randomDateValue) {
                $randomDatearray[] =  date($dateFormat, $randomDateValue);
            }
            //return $randomDatearray;
            return array_values(array_unique($randomDatearray));
        }
    }
    private function get_params($request)
    {
        return $request->get_json_params();
    }
    private function make_params($params)
    {
        $temp = [
            'query_type' => '',
            'per_page' => '',
            'page_num' => '',
            'fromDate' => '',
            'toDate' => '',
            'plugin' => '',
            'formid' => '',
        ];

        return array_merge($temp, $params);
    }
    //in working -> done.
    function view_submissions(WP_REST_Request $request)
    {
        $params = $this->make_params($this->get_params($request));
        $plugin = $params['plugin'];
        $submissions = new Submissions($plugin); // TODO:: Get elementor from request
        $data = $submissions->get_submissions($params);
        wp_send_json($data);
    }

    //working on -> done
    function fv_get_logs_data(WP_REST_Request $request)
    {
        $params = $this->get_params($request);
        $submissions = new Submissions('');
        $logsData = $submissions->fv_get_logs_data($params);
        wp_send_json($logsData);
    }

    //working on -> done
    function save_settings(WP_REST_Request $request)
    {
        $params = $this->get_params($request);
        $callback = $params['callback'];
        $settings = new Settings();
        $res = $settings->$callback($params);
        wp_send_json($res);
    }

    //working on -> done.
    function view_analytics(WP_REST_Request $request)
    {
        $params = $this->make_params($this->get_params($request));
        $plugin = $params['plugin'];
        $analytics = new Submissions($plugin);
        $data = $analytics->get_analytics($params);
        wp_send_json($data);
    }

    // working on -> done.
    function delete_entries(WP_REST_Request $request)
    {
        $ids = $this->get_params($request);
        Base::delete_entries($ids);
    }

    // working on -> done
    function save_options(WP_REST_Request $request)
    {
        $params = $this->get_params($request);
        $plugin = $params['plugin'];
        $submissions = new Submissions($plugin); // TODO:: Get elementor from request
        $submissions->save_options($params);
    }
}
