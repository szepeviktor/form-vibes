<?php

namespace FormVibes\Classes;

class Permissions{

    static function view_submissions() {
	    $allow = true;   // change with dynamic assignement

	    $allow = self::check_user_permission( 'view' );

	    return apply_filters( 'formvibes/permissions/view_submissions', $allow );
    }

    static function delete_submissions(){

        $allow = true;

        $allow = self::check_user_permission( 'delete' );

        return apply_filters('formvibes/permissions/delete_submissions', $allow);
    }

    // This function will check current user permission saved by admin.
	static function check_user_permission($param){
		$permissions = get_option( 'fv_user_role');

		$user = wp_get_current_user();
		if(is_user_logged_in()) {
			$user_role = $user->roles;
			$user_role = $user_role[0];
		}
		else{
			$user_role = 'subscriber';
		}

		if($user_role == 'administrator' || WPV_FV_PLAN == 'FREE'){
			return true;
		}

		if(array_key_exists($user_role,$permissions)){
			$user_permission = $permissions[$user_role];
		}
		else{
			return false;
		}

		if($user_permission[$param] == true || $user_permission[$param] == 'true')
		{
			return true;
		}
		else{
			return false;
		}
	}

}