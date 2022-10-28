<?php

namespace App\Http\Controllers\Dashboard;
use Illuminate\Foundation\Auth\User as Authenticatable;
// use Spatie\Permission\Traits\HasRoles;

use App\Http\Controllers\Controller;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\User\UserRepository;
use App\Role;
use App\Support\Enum\UserStatus;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;
use App\Pages;
use App\Sections;
use App\SectionCategory;
use App\WorldCountry;
use App\WorldDivision;
use App\WorldCity;
use App\User;


class DashboardController extends Controller {
	/**
	 * @var UserRepository
	 */
	private $users;
	
	/**
	 * @var ActivityRepository
	 */
	private $activities;
	// use HasRoles;
	/**
	 * DashboardController constructor.
	 * @param UserRepository $users
	 * @param ActivityRepository $activities
	 */
	public function __construct(UserRepository $users, ActivityRepository $activities) {
		$this->middleware('auth');
		$this->users = $users;
		$this->activities = $activities;
	}

	/**
	 * Displays dashboard based on user's role.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function index() {
		//$users = Auth::user()->removeRole('Godlike');		
		// $users->givePermissionTo('users.manage', 'users.activity','roles.manage','permissions.manage','settings.general',
		// 'settings.auth','settings.notifications','reds.layouts','reds.adminlistings','reds.listings','reds.mystuff','reds.webapps'
		// );
		
		
		// $users->givePermissionTo('users.manage');
		// $users = Auth::user()->getRoleNames();
		// echo "<pre>";print_r($users);die;		
		 if (Auth::user()->hasRole(['Admin', 'Godlike'])) {
			return $this->adminDashboard();
		  }
		 return $this->defaultDashboard();
	}

	/**
	 * Displays dashboard for admin users.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	private function adminDashboard() {
		
		$usersPerMonth = $this->users->countOfNewUsersPerMonth(
			Carbon::now()->startOfYear(),
			Carbon::now()
		);
		
		$stats = [
			'total' => $this->users->count(),
			'new' => $this->users->newUsersCount(),
			'banned' => $this->users->countByStatus(UserStatus::BANNED),
			'unconfirmed' => $this->users->countByStatus(UserStatus::UNCONFIRMED),
		];
		$latestRegistrations = DB::table('users')->where('id', '!=', auth()->id())->get();
		return view('dashboard.admin', compact('stats', 'latestRegistrations', 'usersPerMonth'));
	}

	/**
	 * Displays default dashboard for non-admin users.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	private function defaultDashboard() {
		$activities = $this->activities->userActivityForPeriod(
			Auth::user()->id,
			Carbon::now()->subWeeks(2),
			Carbon::now()
		)->toArray();

		return view('dashboard.default', compact('activities'));
	}


    public function layout()
    {
    	/*
    	* Check reuqest if request from xhr / ajax
    	*/
    	if(request()->ajax()) {
			//  	/*
			//  	* Check if search is true
			//  	*/
			// 		$search = request()->searching;
			// 		if(empty($search)) {
			// 		  $isSearch = false;
			// 		} else {
			// 		  $isSearch = true;
			// 		}

			//  	/*
			//  	* Check if page parameter is allpages
			//  	*/
			// 		if(request()->page == 'allpages') {
				// /*
				// * Counter data by request
				// */
				// $counter = $this->getAllPages([
				// 	'isCounter' => true,
				// 	'isSearch' => $isSearch,
				// 	'start' => request()->start,
				// 	'length' => request()->length,
				// ]);
				// /*
				// * Get data by request
				// */
				// $data = $this->getAllPages([
				// 	'isCounter' => false,
				// 	'isSearch' => $isSearch,
				// 	'start' => request()->start,
				// 	'length' => request()->length,
				// ]);

				// $items = [];
				// foreach($data as $value) {
				// 	$items[] = $value;
				// }

				// return [
				// 	'recordsTotal' => $counter,
				// 	'recordsFiltered' => $counter,
				// 	'data' => $items
				// ];
			// 		}
    	}

		return view('dashboard.layout.index')
			->withPages(Pages::get())
			->withAllsections(Sections::where('allpages', true)->get())
			->withHeadersections(Sections::where('allpages', 1)->get())
			->withFootersections(Sections::where('allpages', 2)->get());
    }

    // private function getAllPages($request)
    // {
    // 	$pages = Pages::orderBy('id','desc');

    // 	if($request['isCounter']) {
    // 	  return $pages->count();
    // 	}

    // 	return $pages->skip($request['start'])->take($request['length'])->get();
    // }

		public function getStateByCountry(Request $request)
		{
			
				$states = WorldDivision::where('country_id', $request->country_id)->orderBy('name')->get();
				return json_encode($states->toArray());
		}

		public function getCityByState(Request $request)
		{
				$cities = WorldCity::where('division_id', $request->division_id)->orderBy('name')->get();
				return json_encode($cities->toArray());
		}
}
