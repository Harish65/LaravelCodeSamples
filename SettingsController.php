<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\Settings\Updated as SettingsUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Settings;


/**
 * Class SettingsController
 * @package App\Http\Controllers
 */
class SettingsController extends Controller {
	/**
	 * Display general settings page.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function general() {
		return view('dashboard.settings.general');
	}

	public function layouts() {
		return view('dashboard.layout.index');
	}


	/**
	 * Display Authentication & Registration settings page.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function auth() {
		return view('dashboard.settings.auth');
	}

	/**
	 * Handle application settings update.
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function update(Request $request) {
		$this->updateSettings($request->except("_token"));

		return back()->withSuccess(trans('app.settings_updated'));
	}

	/**
	 * Update settings and fire appropriate event.
	 *
	 * @param $input
	 */
	private function updateSettings($input) {
		foreach ($input as $key => $value) {
			Settings::set($key, $value);
		}

		Settings::save();

		event(new SettingsUpdated);
	}

	/**
	 * Enable system 2FA.
	 *
	 * @return mixed
	 */
	public function enableTwoFactor() {
		$this->updateSettings(['2fa.enabled' => true]);

		return back()->withSuccess(trans('app.2fa_enabled'));
	}

	/**
	 * Disable system 2FA.
	 *
	 * @return mixed
	 */
	public function disableTwoFactor() {
		$this->updateSettings(['2fa.enabled' => false]);

		return back()->withSuccess(trans('app.2fa_disabled'));
	}

	/**
	 * Enable registration captcha.
	 *
	 * @return mixed
	 */
	public function enableCaptcha() {
		$this->updateSettings(['registration.captcha.enabled' => true]);

		return back()->withSuccess(trans('app.recaptcha_enabled'));
	}

	/**
	 * Disable registration captcha.
	 *
	 * @return mixed
	 */
	public function disableCaptcha() {
		$this->updateSettings(['registration.captcha.enabled' => false]);

		return back()->withSuccess(trans('app.recaptcha_disabled'));
	}

	/**
	 * Display notification settings page.
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function notifications() {
		return view('dashboard.settings.notifications');
	}
}