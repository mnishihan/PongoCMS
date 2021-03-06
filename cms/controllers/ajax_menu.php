<?php

class Cms_Ajax_Menu_Controller extends Cms_Base_Controller {
	
	//FILTERS
	public function __construct()
	{
		parent::__construct();
		//Must be logged
		//$this->filter('before', 'cms_no_auth');
	}

	public function post_save_menu()
	{
		$auth = Auth::check();

		if($auth) {

			$input = Input::get();

			//GRAB DATA
			$menu = new CmsMenu();
			if( ! empty($input['menu_id'])) {
				$menu = CmsMenu::find($input['menu_id']);
			};

			//VALIDATION CHECK

			$rules = array(
				'menu_name'  => 'required|alpha_dash|between:2,20|unique_lang:'.$input['menu_id'].','.$input['menu_lang'].',menus,name',
			);

			$messages = array(
				'required' => LL('cms::validation.required', CMSLANG)->get(),
				'between' => LL('cms::validation.between.string', CMSLANG)->get(),
				'unique_lang' => LL('cms::validation.unique', CMSLANG)->get(),
				'alpha_dash' => LL('cms::validation.alpha_dash', CMSLANG)->get(),
			);

			$validation = Validator::make($input, $rules, $messages);

			if ($validation->fails())
			{
				return json_encode($validation->errors);
			}

			$menu->name = strtolower($input['menu_name']);
			$menu->lang = strtolower($input['menu_lang']);
			$menu->is_nested = Input::has('is_nested') ? 1 : 0;

			//DELETE CACHE
			if(CACHE) Cache::forget('menu_'.strtolower($input['menu_name']).'_'.LANG);
			if(CACHE) Cache::forget('menu_pages_'.strtolower($input['menu_name']).'_'.LANG);

			$menu->save();

			$mid = $menu->id;			

			if(Input::has('page_id')) {

				$pages = Input::get('page_id');

				if(is_array($pages)) {

					foreach ($pages as $pid) {

						$check = $menu->pages()->pivot()->where_cmspage_id($pid)->where_cmsmenu_id($mid)->first();

						if(empty($check)) {
							$menu->pages()->attach($pid, array('order_id' => Config::get('cms::settings.order')));
						}

					}

					//DELETE NOT IN
					$menu->pages()->pivot()->where_cmsmenu_id($mid)->where_not_in('cmspage_id', $pages)->delete();
				}

				$response = 'success';
				$msg = LL('cms::ajax_resp.menu_save_success', CMSLANG)->get();
				$backurl = $input['back_url'];

			} else {

				//DELETE ALL MENU_ID
				$menu->pages()->pivot()->where_cmsmenu_id($mid)->delete();

				$response = 'success';
				$msg = LL('cms::ajax_resp.menu_save_success', CMSLANG)->get();
				$backurl = $input['back_url'];

			}

		} else {

			$response = 'error';
			$msg = LL('cms::ajax_resp.menu_save_error', CMSLANG)->get();
			$backurl = '#';

		}

		$data = array(
			'auth' => $auth,
			'cls' => 'menu_id',
			'id' => $mid,
			'response' => $response,
			'message' => $msg,
			'backurl' => $backurl
		);

		return json_encode($data);
	}

	//ORDER MENU
	public function post_order_menu()
	{

		if(Input::has('order')) {

			$order = Input::get('order');
			
			if(is_array($order)) {
				
				foreach($order as $order_id => $item) {
					$order_id++;
					$p = explode("_", $item);

					$update = array(
						'order_id' => $order_id
					);

					DB::table('menus_pages')->where_cmsmenu_id($p[0])->where_cmspage_id($p[1])->update($update);

				}
				
			}
		}

		return true;

	}


}