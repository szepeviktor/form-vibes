<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Base;
use FormVibes\Classes\Settings;

class Cf7 extends Base
{

    private static $_instance = null;

    // array for skipping fields or unwanted data from the form data.
    protected $skip_fields = [];
	static $submission_id = '';

    public static function instance()
    {

        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Cf7 constructor.
     */
    public function __construct()
    {
        $this->plugin_name = 'cf7';

        $this->set_skip_fields();



        add_action('wpcf7_before_send_mail', [$this, 'before_send_mail']);

        add_filter('fv_forms', [$this, 'register_form']);

        add_filter('wpcf7_mail_components', [$this,'update_mail_content'], 10, 3);
    }

    public function register_form($forms)
    {
        $forms[$this->plugin_name] = 'Contact Form 7';
        return $forms;
    }

    protected function set_skip_fields()
    {
        // name of all fields which should not be stored in our database.
        $this->skip_fields = ['g-recaptcha-response', '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post'];
    }

    function update_mail_content($components, $currentform, $mail){
        
        $components['body'] = str_replace('[fv-entry-id]',self::$submission_id, $components['body'] );
        $components['subject'] = str_replace('[fv-entry-id]',self::$submission_id, $components['subject'] );
        
        return $components;
    }
    public function before_send_mail($contact_form)
    {
        $data = [];

        $submission = \WPCF7_Submission::get_instance();
        // getting all the fields or data from the form.
        $posted_data = $submission->get_posted_data();

		/*echo '<pre>';
		    print_r($submission->uploaded_files());
		echo '</pre>';
		die('---');*/

        // File Upload

		$files = $submission->uploaded_files();

        $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'form-vibes/cf7';
        if (!file_exists($uploads_dir)) wp_mkdir_p($uploads_dir);
        
		$cf7upload    = wp_upload_dir();
        $fv_dirname = $cf7upload['baseurl'].'/form-vibes/cf7';

        $time_now = time();
        
		foreach ($files as $file_key => $file) {
			//$posted_data[$file_key] = $file;
			$filetype = strrpos($file,".");
			$filetype = substr($file,$filetype);
			$filename = rand(1111111111,9999999999);
			$posted_data[$file_key] = $fv_dirname . '/' . $time_now.'-'.$filename.$filetype;

			array_push($uploaded_files, $time_now.'-'.$filename.$filetype);
			copy($file, $uploads_dir.'/'.$time_now.'-'.$filename.$filetype);
		}

        //End File Upload Code

        //loop for skipping fields from the posted_data.
        foreach ($posted_data as $key => $value) {
            if (in_array($key, $this->skip_fields)) {
                // unset will destroy the skip's fields.
                unset($posted_data[$key]);
            } else if (gettype($value) == 'array') {

                $posted_data[$key] = implode(', ', $value);
            }
        }

        if ($submission) {

            $data['plugin_name']    =   $this->plugin_name;
            $data['id']             =   $contact_form->id();
            $data['captured']       =   current_time('mysql', $gmt = 0);
            $data['captured_gmt']   =   current_time('mysql', $gmt = 1);

            $data['title']          =   $contact_form->title();
            $data['url']            =   $submission->get_meta('url');

            $posted_data['fv_plugin']       =   $this->plugin_name;
            $posted_data['fv_form_id']      =   $contact_form->id();

            $settings = Settings::instance();
		    $dbSettings      = $settings->get();

            if ($dbSettings['ip'] == true) {
                $posted_data['IP']              = $this->set_user_ip();
            }

            $data['posted_data']    =   $posted_data;
        }
        self::$submission_id = $this->insert_enteries($data);
    }

    static function get_forms($param)
    {
        global $wpdb;
        $post_type = $param;

        /*if($post_type == 'cf7'){
		    $post_type = 'wpcf7_contact_form';
	    }
	    $args = array(
		    'post_type'   => $post_type,
		    'order'       => 'ASC',
	    );

	    $forms = get_posts( $args );

	    $data = [];
	    foreach ( $forms as $form ) {
		    $data[$form->ID] = [
		    	                 'id' => $form->ID,
			                     'name' => $form->post_title
		                       ];
		}*/
        $form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e WHERE form_plugin='cf7'";
        $form_res = $wpdb->get_results($form_query, OBJECT_K);

        $inserted_forms = get_option('fv_forms');

        $key = 'cf7';
        $forms = [];
        foreach ($form_res as $form_key => $form_value) {
            if ($form_res[$form_key]->form_plugin == $key) {
                $forms[$form_key] = [
                    'id' => $form_key,
                    'name' => $inserted_forms[$key][$form_key]['name'] !== null ? $inserted_forms[$key][$form_key]['name'] : $form_key
                ];
            }
        }
        return $forms;
    }

    static function get_submission_data($param)
    {

        $meta_key = [];
        $cols = [];

        $gmt_offset =  get_option('gmt_offset');
        $hours   = (int) $gmt_offset;
        $minutes = ($gmt_offset - floor($gmt_offset)) * 60;

        if ($hours >= 0) {
            $time_zone = '+' . $hours . ':' . $minutes;
        } else {
            $time_zone = $hours . ':' . $minutes;
        }

        if ($param['queryType'] !== 'Custom') {
            $dates = Utils::get_dates($param['queryType']);

            $tz = new \DateTimeZone($time_zone);

            $fromDate = new \DateTime($dates['fromDate']);
            $fromDate->setTimezone($tz);
            $toDate = new \DateTime($dates['endDate']);
            $toDate->setTimezone($tz);

            $fromDate = $fromDate->format('Y-m-d');
            $toDate = $toDate->format('Y-m-d');
        } else {
            $tz = new \DateTimeZone($time_zone);

            $fromDate = new \DateTime($param['fromDate']);
            $fromDate->setTimezone($tz);
            $toDate = new \DateTime($param['toDate']);
            $toDate->setTimezone($tz);

            $fromDate = $fromDate->format('Y-m-d');
            $toDate = $toDate->format('Y-m-d');
        }

        if ($fromDate !== '' && $fromDate !== null) {
            $param_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $fromDate . "'";
            $paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) >= '" . $fromDate . "'";
        }
        if ($toDate !== '' && $toDate !== null) {
            if ($fromDate !== '') {
                $param_where[] = " DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $toDate . "'";
                $paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $toDate . "'";
            } else {
                $param_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $toDate . "'";
                $paramcount_where[] = "DATE_FORMAT(captured,GET_FORMAT(DATE,'JIS')) <= '" . $toDate . "'";
            }
        }

        if ($param['selectedFilter'] == 'undefined' || $param['selectedFilter'] == '' || $param['filterValue'] == 'undefined' || $param['filterValue'] == '') {
            $filter_param[] = "meta_key like'%%'";
        } else {
            $filter_param[] = "meta_key='" . $param['selectedFilter'] . "'";
        }
        if ($param['filterValue'] == 'undefined' || $param['filterValue'] == '') {
            $filter_param[] = "meta_value like'%%'";
        } else {
            if ($param['filterOperator'] == 'equal') {
                $filter_param[] = "meta_value='" . $param['filterValue'] . "'";
            } else if ($param['filterOperator'] == 'not_equal') {
                $filter_param[] = "meta_value != '" . $param['filterValue'] . "'";
            } else if ($param['filterOperator'] == 'contain') {
                $filter_param[] = "meta_value LIKE '%" . $param['filterValue'] . "%'";
            } else if ($param['filterOperator'] == 'not_contain') {
                $filter_param[] = "meta_value NOT LIKE '%" . $param['filterValue'] . "%'";
            }
        }

        foreach ($param['columns'] as $key => $val) {
            if ($val->visible == 0 || $val->visible == '') {
                $meta_key[] = $val->colKey;
            }
            $cols[] = $val->colKey;
        }

        global $wpdb;
        $gdpr_settings = get_option('fv_gdpr_settings');

        $filter_col_id = "select data_id FROM {$wpdb->prefix}fv_enteries e left join {$wpdb->prefix}fv_entry_meta em on e.id=em.data_id where 
        " . implode(' and ', $filter_param) . " and form_id = '" . $param['form'] . "'";
        $filter_col_id_res = $wpdb->get_results($filter_col_id, ARRAY_A);
        $entry_id = [];
        foreach ($filter_col_id_res as $entryId) {
            $entry_id[] = $entryId['data_id'];
        }


        $entry_query = "select * from {$wpdb->prefix}fv_enteries e 
        left JOIN {$wpdb->prefix}fv_entry_meta ev ON e.id=ev.data_id
        where " . implode(' and ', $param_where) . " and ev.meta_key NOT IN ('" . implode("','", $meta_key) . "') and data_id IN ('" . implode("','", $entry_id) . "') and form_id = '" . $param['form'] . "' order by captured desc";

        $entry_res = $wpdb->get_results($entry_query, ARRAY_A);

        $meta_data = [];
        $ipChecker = '';
        if ($gdpr_settings['ip'] == 'yes') {
            $ipChecker = "";
        } else {
            $ipChecker = "IP";
        }

        foreach ($entry_res as $entry_meta) {
            if ($entry_meta['meta_key'] == 'fv_plugin' || $entry_meta['meta_key'] == 'fv_form_id' || $entry_meta['meta_key'] == $ipChecker) {
                continue;
            }
            $meta_data[$entry_meta['data_id']][$entry_meta['meta_key']] = stripslashes($entry_meta['meta_value']);
        }


        if (!in_array('captured', $meta_key)) {
            foreach ($entry_res as $entry_meta) {
                $meta_data[$entry_meta['data_id']]['captured'] = stripslashes($entry_meta['captured']);
            }
        }

        if ($gdpr_settings['ua'] !== 'no') {
            if (!in_array('user_agent', $meta_key)) {
                foreach ($entry_res as $entry_meta) {
                    $meta_data[$entry_meta['data_id']]['user_agent'] = stripslashes($entry_meta['user_agent']);
                }
            }
        }

        $res = [];
        foreach ($meta_data as $key => $val) {
            $res[] = $val;
        }

        $final_array = [];
        $final_cols = array_flip(array_diff($cols, $meta_key));
        foreach ($final_cols as $key => $value) {
            $final_cols[$key] = '';
        }
        for ($i = 0; $i < count($res); $i++) {
            $final_array[] = array_merge($final_cols, $res[$i]);
        }

        return $final_array;
    }
}
