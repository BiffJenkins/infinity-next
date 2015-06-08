<?php namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Panel\PanelController;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;

class HomeController extends PanelController {
	
	/*
	|--------------------------------------------------------------------------
	| Home Controller
	|--------------------------------------------------------------------------
	|
	| This controller renders your application's "dashboard" for users that
	| are authenticated. Of course, you are free to change or remove the
	| controller as you wish. It is just here to get your app started!
	|
	*/
	
	const VIEW_HOME = "panel.home";
	
	/**
	 * Create a new authentication controller instance.
	 *
	 * @param  \App\Services\UserManager  $manager
	 * @return void
	 */
	public function __construct(\App\Services\UserManager $manager)
	{
		$this->middleware('auth');
		
		return parent::__construct($manager);
	}
	
	/**
	 * Show the application dashboard to the user.
	 *
	 * @return Response
	 */
	public function getIndex()
	{
		return $this->view(static::VIEW_HOME);
	}
	
}
