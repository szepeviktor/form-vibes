<?php

namespace FormVibes\Integrations;

use FormVibes\Classes\DbManager;
use FormVibes\Classes\Settings;

class BeaverBuilder extends Base {

	private static $_instance = null;
	static $forms = [];

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    public function __construct()
    {
        $this->plugin_name = 'beaverBuilder';
	    add_filter( 'fl_builder_register_settings_form' , [$this ,'my_builder_register_settings_form'], 10 ,2);

        //add_action('elementor_pro/forms/new_record', [$this, 'form_new_record'], 10, 2);
	    add_action('fl_module_contact_form_before_send',[$this, 'form_new_record'], 10, 5);

	    add_filter('fv_forms', [ $this, 'register_form' ]);

    }

	public function register_form($forms){
		$forms[$this->plugin_name] = 'Beaver Builder';
		return $forms;
	}

	function my_builder_register_settings_form( $form, $id ) {
		$form_name = array(
			'title' =>  __('Form Setting' , 'wpv-fv'),
			'fields'    =>  array(
				'form_name' =>  array(
					'label' =>  __('Form Name' , 'wpv-fv'),
					'type'  =>  'text',
					'placeholder'   =>  __('Contact Form' , 'wpv-fv')
				),
			),
		);
		if('contact-form' != $id){
			return $form;
		}else{
			$form['general']['sections'] = array_merge(
				array('form_name' =>  $form_name),
				array_slice($form['general']['sections'] , '0')
			);
			return $form;
		}
	}

    public function form_new_record( $mailto, $subject, $template, $headers, $settings) {
	    $data = [];

	    $data['plugin_name']  = $this->plugin_name;
	    if(array_key_exists('template_node_id',$_REQUEST)){
	    	$id = $_REQUEST['template_node_id'];
	    }
	    else{
		    $id = $_REQUEST['node_id'];
	    }
	    $data['id']           = $id;
	    $data['captured']     = current_time( 'mysql', $gmt = 0 );
	    $data['captured_gmt'] = current_time( 'mysql', $gmt = 1 );

	    $form = $this->get_form_title($_REQUEST['post_id']);

	    $data['title'] = $form[$id]['name'];
	    $data['url']   = get_permalink($_REQUEST['post_id']);

	    $posted_da['fv_plugin'] = $this->plugin_name;
	    //$record->get('sent_data') will return form data or values.
	    $posted_data = $this->field_processor( $settings );

	    $settings = Settings::instance();
		$dbSettings      = $settings->get();

		if ($dbSettings['ip'] == true) {
			$posted_data['IP']              = $this->set_user_ip();
		}
			
	    $posted_data['fv_form_id'] = $id;
	    $data['posted_data']       = $posted_data;

	    $this->insert_enteries( $data );
    }

    public function field_processor($settings){
    	if($settings->name_toggle == 'show'){
		    $save_data['name'] = $_REQUEST['name'];
	    }
	    if($settings->subject_toggle == 'show'){
		    $save_data['subject'] = $_REQUEST['subject'];
	    }
	    if($settings->email_toggle == 'show'){
		    $save_data['email'] = $_REQUEST['email'];
	    }
	    if($settings->phone_toggle == 'show'){
		    $save_data['phone'] = $_REQUEST['phone'];
	    }
	    $save_data['message'] = $_REQUEST['message'];


		return $save_data;

    }

    public function get_form_title($postID){
	    global $wpdb;

	    $sql_query = "SELECT *  FROM {$wpdb->prefix}postmeta
		WHERE meta_key LIKE '_fl_builder_data'
		AND meta_value LIKE '%contact-form%'
		AND post_id=".$postID;

	    $results = $wpdb->get_results($sql_query);


	    if (!count($results)){
		    return;
	    }
	    foreach($results as $result){
		    $post_id = $result->post_id;
		    $data = $result->meta_value;
		    $json = maybe_unserialize($data);

		    if($json){
			    foreach($json as $j){
				    self::find_form($j,$post_id,$json);
			    }
		    }
	    }

	    return self::$forms;
    }
	static function get_forms($param) {
		global $wpdb;

		/*$sql_query = "SELECT *  FROM {$wpdb->prefix}postmeta
		WHERE meta_key LIKE '_fl_builder_data'
		AND meta_value LIKE '%contact-form%'
		AND post_id IN (
			SELECT id FROM {$wpdb->prefix}posts
			WHERE post_status LIKE 'publish'
		)";

		$results = $wpdb->get_results($sql_query);


		if (!count($results)){
			return;
		}
		foreach($results as $result){
			$post_id = $result->post_id;
			$data = $result->meta_value;
			$json = maybe_unserialize($data);

			if($json){
				foreach($json as $j){
					self::find_form($j,$post_id,$json);
				}
			}
		}*/
		$form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e WHERE form_plugin='beaverBuilder'";
		$form_res = $wpdb->get_results($form_query,OBJECT_K);

		$inserted_forms = get_option('fv_forms');

		$key = 'beaverBuilder';

		foreach ( $form_res as $form_key => $form_value ) {
			if($form_res[$form_key]->form_plugin == $key){
				self::$forms[$form_key] = [
					'id' => $form_key,
					'name' => $inserted_forms[$key][$form_key]['name'] !== null ? $inserted_forms[$key][$form_key]['name'] : $form_key
				];
			}
		}

		return self::$forms;

	}
	static function find_form($element_data,$post_id,$original_data){

		if(!$element_data->type){
			return;
		}

		if ( 'module' == $element_data->type && ('contact-form' === $element_data->settings->type)) {


			//echo 'step 1 in find form before check global | ';
			//$id = self::check_global($post_id);
			if(property_exists($element_data,'template_node_id')){
				$id = $element_data->template_node_id;
			}
			else{
				$id = $element_data->node;
			}
			//echo 'step 5 global check id '. $id . ' |<br/> ';
			//die('---');

			if('contact-form' === $element_data->settings->type ){
				//echo 'name '. $element_data->settings->form_name;
					self::$forms[$id] = [
						'id' => $id,
						'name' => $element_data->settings->form_name,
					];
			}
		}
	}

    static function get_submission_data($param){
        $class = '\WPV_FV\Integrations\\'.ucfirst('cf7');
        $data = $class::get_submission_data($param);

        return $data;
    }
}
