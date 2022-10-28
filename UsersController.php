<?php

namespace App\Http\Controllers\Dashboard;

use App\AffiliateUser;
use App\Events\User\Banned;
use App\Events\User\Deleted;
use App\Events\User\TwoFactorDisabledByAdmin;
use App\Events\User\TwoFactorEnabledByAdmin;
use App\Events\User\UpdatedByAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\EnableTwoFactorRequest;
use App\Http\Requests\User\UpdateDetailsRequest;
use App\Http\Requests\User\UpdateLoginDetailsRequest;
use App\Listings;
use App\ListingsWebApps;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Country\CountryRepository;
use App\Repositories\Role\RoleRepository;
use App\Repositories\Session\SessionRepository;
use App\Repositories\User\UserRepository;
use App\Services\Upload\UserAvatarManager;
use App\Support\Enum\UserStatus;
use App\User;
use App\WebApp;
use Auth;
use Authy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\WorldDivision;
use App\WorldCity;
use App\CountryData;
use App\StateData;
use App\CityData;
use App\WorldCountry;
use DB;
use URL;
/**
 * Class UsersController
 * @package App\Http\Controllers
 */
class UsersController extends Controller {
	/**
	 * @var UserRepository
	 */
	private $users;

	/**
	 * UsersController constructor.
	 * @param UserRepository $users
	 */
	public function __construct(UserRepository $users) {
		$this->middleware('auth');
		// $this->middleware('session.database', ['only' => ['sessions', 'invalidateSession']]);
		// $this->middleware('permission:users.manage');
		$this->users = $users;
	}

	/**
	 * Display paginated list of all users.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function index(RoleRepository $roleRepository) {
		$data['users'] = $this->users->paginate(10,request()->search,request()->status);
		$data['roles'] = $roleRepository->lists();
		$data['statuses'] = [ '' => trans('app.all') ] + UserStatus::lists();
		return view('dashboard.user.list', $data);
	}

	/**
	 * Displays user profile page.
	 *
	 * @param User $user
	 * @param ActivityRepository $activities
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function view(User $user, ActivityRepository $activities) {
		$socialNetworks = $user->socialNetworks;
		$userActivities = $activities->getLatestActivitiesForUser($user->id, 10);
		
		return view('dashboard.user.view', compact('user', 'socialNetworks', 'userActivities'));
	}

	/**
	 * Displays form for creating a new user.
	 *
	 * @param CountryRepository $countryRepository
	 * @param RoleRepository $roleRepository
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function create(CountryRepository $countryRepository, RoleRepository $roleRepository) {
		$countries = WorldCountry::orderBy('name')->get();
		$roles = $roleRepository->lists();
		$statuses = UserStatus::lists();
		
		return view('dashboard.user.add', compact('countries', 'roles', 'statuses'));
	}

	/**
	 * Stores new user into the database.
	 *
	 * @param CreateUserRequest $request
	 * @return mixed
	 */
	public function store(CreateUserRequest $request) {
		
		// When user is created by administrator, we will set his
		// status to Active by default.
		$data = $request->all() + ['status' => UserStatus::ACTIVE];

		// Username should be updated only if it is provided.
		// So, if it is an empty string, then we just leave it as it is.
		if (trim($data['username']) == '') {
			$data['username'] = null;
		}
		
		$user = $this->users->create($data);
		
		//add seourl
		$user->seourl = $user->slugify($user->username);
		
		$user->save();

		$this->users->updateSocialNetworks($user->id, []);
		$this->users->setRole($user->id, $request->get('role'));

		return redirect()->route('user.list')
			->withSuccess(trans('app.user_created'));
	}

	/**
	 * Displays edit user form.
	 *
	 * @param User $user
	 * @param CountryRepository $countryRepository
	 * @param RoleRepository $roleRepository
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function edit(User $user, CountryRepository $countryRepository, RoleRepository $roleRepository) {
		
		$roles = $user->getRoleNames();
		$edit = true;
		//TODO does not have id, return list to view with country ID
		$countries = WorldCountry::orderBy('name')->get();
		$socials = $user->socialNetworks;
		$roles = $roleRepository->lists();
		$statuses = UserStatus::lists();
		$socialLogins = $this->users->getUserSocialLogins($user->id);
	
		$states = WorldDivision::where(['country_id'=>$user->country_id])->orderBy('name')->get();
		$cities = WorldCity::where(['division_id'=>$user->division_id])->orderBy('name')->get();
		return view('dashboard.user.edit',
			compact('edit', 'user', 'countries', 'socials', 'socialLogins', 'roles', 'statuses','states','cities'));
	}

	/**
	 * Updates user details.
	 *
	 * @param User $user
	 * @param UpdateDetailsRequest $request
	 * @return mixed
	 */
	public function updateDetails(User $user, Request $request) {
		$this->users->update($user->id, $request->all());
		$this->users->setRole($user->id, $request->get('role'));

		event(new UpdatedByAdmin($user));

		// If user status was updated to "Banned",
		// fire the appropriate event.
		if ($this->userIsBanned($user, $request)) {
			event(new Banned($user));
		}

		return redirect()->back()
			->withSuccess(trans('app.user_updated'));
	}

	/**
	 * Check if user is banned during last update.
	 *
	 * @param User $user
	 * @param Request $request
	 * @return bool
	 */
	private function userIsBanned(User $user, Request $request) {
		return $user->status != $request->status && $request->status == UserStatus::BANNED;
	}

	/**
	 * Update user's avatar from uploaded image.
	 *
	 * @param User $user
	 * @param UserAvatarManager $avatarManager
	 * @return mixed
	 */
	public function updateAvatar(User $user, UserAvatarManager $avatarManager) {
	
		$name = $avatarManager->uploadAndCropAvatar($user);
		
		$this->users->update($user->id, ['avatar' => $name]);

		event(new UpdatedByAdmin($user));

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.avatar_changed'));
	}

	/**
	 * Update user's avatar from some external source (Gravatar, Facebook, Twitter...)
	 *
	 * @param User $user
	 * @param Request $request
	 * @param UserAvatarManager $avatarManager
	 * @return mixed
	 */
	public function updateAvatarExternal(User $user, Request $request, UserAvatarManager $avatarManager) {
		
		$avatarManager->deleteAvatarIfUploaded($user);

		$this->users->update($user->id, ['avatar' => $request->get('url')]);

		event(new UpdatedByAdmin($user));

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.avatar_changed'));
	}

	/**
	 * Update user's social networks.
	 *
	 * @param User $user
	 * @param Request $request
	 * @return mixed
	 */
	public function updateSocialNetworks(User $user, Request $request) {
		$this->users->updateSocialNetworks($user->id, $request->get('socials'));

		event(new UpdatedByAdmin($user));

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.socials_updated'));
	}

	/**
	 * Update user's login details.
	 *
	 * @param User $user
	 * @param UpdateLoginDetailsRequest $request
	 * @return mixed
	 */
	public function updateLoginDetails(User $user, UpdateLoginDetailsRequest $request) {
		$data = $request->all();

		if (trim($data['password']) == '') {
			unset($data['password']);
			unset($data['password_confirmation']);
		}

		$this->users->update($user->id, $data);

		event(new UpdatedByAdmin($user));

		//update seourl
    $user->seourl = $user->slugify($user->username);
    $user->save();

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.login_updated'));
	}

	/**
	 * Removes the user from database.
	 *
	 * @param User $user
	 * @return $this
	 */
	public function delete(User $user) {
		if ($user->id == Auth::id()) {
			return redirect()->route('user.list')
				->withErrors(trans('app.you_cannot_delete_yourself'));
		}

		$listings = Listings::where('user_id', '=', $user->id)->select('id')->get()->toArray();
		$webpp = WebApp::whereIn('listing_id', $listings)->select('id')->get()->toArray();
		ListingsWebApps::whereIn('webapp_id', $webpp)->delete();
		WebApp::whereIn('listing_id', $listings)->delete();
		Listings::where('user_id', '=', $user->id)->delete();
		AffiliateUser::where('user_id', '=', $user->id)->delete();

		$this->users->delete($user->id);

		event(new Deleted($user));

		return redirect()->route('user.list')
			->withSuccess(trans('app.user_deleted'));
	}

	/**
	 * Enables Authy Two-Factor Authentication for user.
	 *
	 * @param User $user
	 * @param EnableTwoFactorRequest $request
	 * @return $this
	 */
	public function enableTwoFactorAuth(User $user, EnableTwoFactorRequest $request) {
		if (Authy::isEnabled($user)) {
			return redirect()->route('user.edit', $user->id)
				->withErrors(trans('app.2fa_already_enabled_user'));
		}

		$user->setAuthPhoneInformation($request->country_code, $request->phone_number);

		Authy::register($user);

		$user->save();

		event(new TwoFactorEnabledByAdmin($user));

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.2fa_enabled'));
	}

	/**
	 * Disables Authy Two-Factor Authentication for user.
	 *
	 * @param User $user
	 * @return $this
	 */
	public function disableTwoFactorAuth(User $user) {
		if (!Authy::isEnabled($user)) {
			return redirect()->route('user.edit', $user->id)
				->withErrors(trans('app.2fa_not_enabled_user'));
		}

		Authy::delete($user);

		$user->save();

		event(new TwoFactorDisabledByAdmin($user));

		return redirect()->route('user.edit', $user->id)
			->withSuccess(trans('app.2fa_disabled'));
	}

	/**
	 * Displays the list with all active sessions for selected user.
	 *
	 * @param User $user
	 * @param SessionRepository $sessionRepository
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function sessions(User $user, SessionRepository $sessionRepository) {
		$adminView = true;
		
		$sessions = $sessionRepository->getUserSessions($user->id);

		return view('dashboard.user.sessions', compact('sessions', 'user', 'adminView'));
	}

	/**
	 * Invalidate specified session for selected user.
	 *
	 * @param User $user
	 * @param $sessionId
	 * @param SessionRepository $sessionRepository
	 * @return mixed
	 */
	public function invalidateSession(User $user, $sessionId, SessionRepository $sessionRepository) {
		$sessionRepository->invalidateUserSession($user->id, $sessionId);

		return redirect()->route('user.sessions', $user->id)
			->withSuccess(trans('app.session_invalidated'));
	}

	public function find($id){
		$user = User::find($id);
		$listings = Listings::where('user_id', '=', $id)->get()->count();

		$data = array('count_listings' => $listings, 'user_data' => $user);
		return view('dashboard.user.modals.confirm_delete', $data)->render();
	}
}
