<?php
namespace App\Http\Controllers; use App\System; use Illuminate\Foundation\Bus\DispatchesJobs; use Illuminate\Routing\Controller as BaseController; use Illuminate\Foundation\Validation\ValidatesRequests; use Illuminate\Foundation\Auth\Access\AuthorizesRequests; use Illuminate\Http\Request; class Controller extends BaseController { use AuthorizesRequests, DispatchesJobs, ValidatesRequests; function authQuery(Request $spfb5ae3, $spd60c7c, $spc24f5b = 'user_id', $spd6df48 = 'user_id') { return $spd60c7c::where($spc24f5b, \Auth::id()); } protected function getUserId(Request $spfb5ae3, $spd6df48 = 'user_id') { return \Auth::id(); } protected function getUserIdOrFail(Request $spfb5ae3, $spd6df48 = 'user_id') { $sp134e80 = self::getUserId($spfb5ae3, $spd6df48); if ($sp134e80) { return $sp134e80; } else { throw new \Exception('参数缺少 ' . $spd6df48); } } protected function getUser(Request $spfb5ae3) { return \Auth::getUser(); } protected function checkIsInMaintain() { if ((int) System::_get('maintain') === 1) { $sp5b1ead = System::_get('maintain_info'); echo view('message', array('title' => '维护中', 'message' => $sp5b1ead)); die; } } protected function msg($sp8f0c89, $spbe8e6e = null, $sp29461c = null) { return view('message', array('message' => $sp8f0c89, 'title' => $spbe8e6e, 'exception' => $sp29461c)); } }