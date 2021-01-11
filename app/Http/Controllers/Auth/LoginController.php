<?php
namespace App\Http\Controllers\Auth; use App\Http\Controllers\Controller; use App\Library\Helper; use App\Library\QQWry\QQWry; use App\Library\WeChat; use App\System; use Illuminate\Http\Request; use Illuminate\Foundation\Auth\ThrottlesLogins; use Illuminate\Http\Response; use Illuminate\Support\Facades\Auth; class LoginController extends Controller { use ThrottlesLogins; protected function username() { return 'email'; } public function login(Request $sp375069) { $this->validate($sp375069, array('email' => 'required|email', 'password' => 'required|string')); if (System::_getInt('vcode_login_admin') === 1) { $this->validateCaptcha($sp375069); } if ($this->hasTooManyLoginAttempts($sp375069)) { $this->fireLockoutEvent($sp375069); $sp57a0a1 = $this->limiter()->availableIn($this->throttleKey($sp375069)); return response(array('message' => trans('auth.throttle', array('seconds' => $sp57a0a1))), Response::HTTP_BAD_REQUEST); } if ($spc29a0f = Auth::attempt($sp375069->only('email', 'password'))) { $sp264a55 = Auth::getUser(); $sp0f613e = $sp375069->ip(); $sp264a55->logs()->create(array('action' => \App\Log::ACTION_LOGIN, 'ip' => $sp0f613e, 'address' => (new QQWry())->getLocation($sp0f613e))); return response($this->getUserInfo()->getContent(), Response::HTTP_CREATED, array('Authorization' => 'Bearer ' . $spc29a0f)); } LOGIN_FAILED: $this->incrementLoginAttempts($sp375069); return response(array('message' => trans('auth.failed')), Response::HTTP_BAD_REQUEST); } public function getUserInfo() { $sp264a55 = Auth::getUser(); $sp264a55->addHidden(array('created_at', 'updated_at')); $sp264a55->append(array('last_login_at')); $sp2a9a03 = array(); $sp2a9a03['user'] = $sp264a55; return response($sp2a9a03); } public function logout() { try { @Auth::logout(); } catch (\Throwable $spf95c2c) { } return response(array()); } }