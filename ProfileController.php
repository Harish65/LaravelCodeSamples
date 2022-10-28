<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\User\ChangedAvatar;
use App\Events\User\TwoFactorDisabled;
use App\Events\User\TwoFactorEnabled;
use App\Events\User\UpdatedProfileDetails;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\EnableTwoFactorRequest;
use App\Http\Requests\User\UpdateProfileDetailsRequest;
use App\Http\Requests\User\UpdateProfileLoginDetailsRequest;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Country\CountryRepository;
use App\Repositories\State\StateRepository;
use App\Repositories\City\CityRepository;
use App\Repositories\Role\RoleRepository;
use App\Repositories\Session\SessionRepository;
use App\Repositories\User\UserRepository;
use App\Services\Upload\UserAvatarManager;
use App\Support\Enum\UserStatus;
use App\User;
use App\WorldCountry;
use App\WorldDivision;
use App\WorldCity;
use Auth;
use Authy;
use Illuminate\Http\Request;
use DB;
/**
 * Class ProfileController
 * @package App\Http\Controllers
 */
class ProfileController extends Controller {
	/**
	 * @var User
	 */
	protected $theUser;
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

		$this->users = $users;
		$this->theUser = Auth::user();
	}

	/**
	 * Display user's profile page.
	 *
	 * @param RoleRepository $rolesRepo
	 * @param CountryRepository $countryRepository
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function index(RoleRepository $rolesRepo, CountryRepository $countryRepository,User $user) {
		
		$edit = true;
		$roles = $rolesRepo->lists();
		$socials = DB::table('user_social_networks')->where('user_id', '=', Auth::user()->id)->first();
		$countries = WorldCountry::orderBy('name')->get();
		$states = WorldDivision::where('country_id', Auth::user()->country_id)->orderBy('name')->get();
		$cities = WorldCity::where('division_id', Auth::user()->division_id)->orderBy('name')->get();	
		$socialLogins = DB::table('social_logins')->where('user_id', Auth::user()->id)->get();
		$statuses = UserStatus::lists();
		
		return view('dashboard/user/profile',
			compact('user',
							'edit',
							'roles',
							'countries',
							'states',
							'cities',
							'socialLogins',
							'socials',
							'statuses'
						));
						return view('dashboard/user/profile');
	}

	/**
	 * Update profile details.
	 *
	 * @param UpdateProfileDetailsRequest $request
	 * @return mixed
	 */
	public function updateDetails(Request $request) {
		
		$this->users->update(Auth::user()->id, $request->except('role', 'status'));

		return redirect()->route('profile')
			->withSuccess(trans('app.profile_updated_successfully'));
	}

	/**
	 * Upload and update user's avatar.
	 *
	 * @param Request $request
	 * @param UserAvatarManager $avatarManager
	 * @return mixed
	 */
	public function updateAvatar(Request $request, UserAvatarManager $avatarManager) {
		$name = $avatarManager->uploadAndCropAvatar(Auth::user());

		return $this->handleAvatarUpdate($name);
	}

	/**
	 * Update avatar for currently logged in user
	 * and fire appropriate event.
	 *
	 * @param $avatar
	 * @return mixed
	 */
	private function handleAvatarUpdate($avatar) {
		$this->users->update(Auth::user()->id, ['avatar' => $avatar]);

		event(new ChangedAvatar);

		return redirect()->route('profile')
			->withSuccess(trans('app.avatar_changed'));
	}

	/**
	 * Update user's avatar from external location/url.
	 *
	 * @param Request $request
	 * @param UserAvatarManager $avatarManager
	 * @return mixed
	 */
	public function updateAvatarExternal(Request $request, UserAvatarManager $avatarManager) {
		$avatarManager->deleteAvatarIfUploaded($this->theUser);

		return $this->handleAvatarUpdate($request->get('url'));
	}

	/**
	 * Update user's social networks.
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function updateSocialNetworks(Request $request) {
		$this->users->updateSocialNetworks(Auth::user()->id, $request->get('socials'));

		return redirect()->route('profile')
			->withSuccess(trans('app.socials_updated'));
	}

	/**
	 * Update user's login details.
	 *
	 * @param UpdateProfileLoginDetailsRequest $request
	 * @return mixed
	 */
	public function updateLoginDetails(Request $request) {
		$data = $request->except('role', 'status');

		// If password is not provided, then we will
		// just remove it from $data array and do not change it
		if (trim($data['password']) == '') {
			unset($data['password']);
			unset($data['password_confirmation']);
		}

		$this->users->update(Auth::user()->id, $data);

		return redirect()->route('profile')
			->withSuccess(trans('app.login_updated'));
	}

	/**
	 * Enable 2FA for currently logged user.
	 *
	 * @param EnableTwoFactorRequest $request
	 * @return $this
	 */
	public function enableTwoFactorAuth(EnableTwoFactorRequest $request) {
		if (Authy::isEnabled($this->theUser)) {
			return redirect()->route('user.edit', $this->theUser->id)
				->withErrors(trans('app.2fa_already_enabled'));
		}

		$this->theUser->setAuthPhoneInformation($request->country_code, $request->phone_number);

		Authy::register($this->theUser);

		$this->theUser->save();

		event(new TwoFactorEnabled);

		return redirect()->route('profile')
			->withSuccess(trans('app.2fa_enabled'));
	}

	/**
	 * Disable 2FA for currently logged user.
	 *
	 * @return $this
	 */
	public function disableTwoFactorAuth() {
		if (!Authy::isEnabled($this->theUser)) {
			return redirect()->route('profile')
				->withErrors(trans('app.2fa_not_enabled_for_this_user'));
		}

		Authy::delete($this->theUser);

		$this->theUser->save();

		event(new TwoFactorDisabled);

		return redirect()->route('profile')
			->withSuccess(trans('app.2fa_disabled'));
	}

	/**
	 * Display user activity log.
	 *
	 * @param ActivityRepository $activitiesRepo
	 * @param Request $request
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function activity(ActivityRepository $activitiesRepo, Request $request) {
		$perPage = 20;
		$user = Auth::user();

		$activities = $activitiesRepo->paginateActivitiesForUser(
			$user->id, $perPage, $request->get('description')
		);

		if(isset($request->searching))
		{
				$data = $activities->toArray();
				$data['recordsTotal'] = $data['total'];
				$data['recordsFiltered'] = $data['total'];

				return json_encode($data);
		}

		return view('dashboard.activity.index', compact('activities', 'user'));
	}

	/**
	 * Display active sessions for current user.
	 *
	 * @param SessionRepository $sessionRepository
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function sessions(SessionRepository $sessionRepository) {
		
		$profile = true;
		$user = Auth::user();;
		$sessions = $sessionRepository->getUserSessions($user->id);
		return view('dashboard.user.sessions', compact('sessions', 'user', 'profile'));
	}

	/**
	 * Invalidate user's session.
	 *
	 * @param $sessionId
	 * @param SessionRepository $sessionRepository
	 * @return mixed
	 */
	public function invalidateSession($sessionId, SessionRepository $sessionRepository) {
		$sessionRepository->invalidateUserSession(
			$this->theUser->id,
			$sessionId
		);

		return redirect()->route('profile.sessions')
			->withSuccess(trans('app.session_invalidated'));
	}
}
