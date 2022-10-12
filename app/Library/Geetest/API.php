<?php
namespace App\Library\Geetest; use App\Library\Helper; use Hashids\Hashids; use Illuminate\Support\Facades\Session; class API { private $geetest_conf = null; public function __construct($sp474860) { $this->geetest_conf = $sp474860; } public static function get() { $spb3d6c6 = config('services.geetest.id'); $sp50e909 = config('services.geetest.key'); if (!strlen($spb3d6c6) || !strlen($sp50e909)) { return array('message' => 'geetest error: no config'); } $sp43da22 = new Lib($spb3d6c6, $sp50e909); $sp7aa9af = time() . rand(1, 10000); $sp11513e = $sp43da22->pre_process($sp7aa9af); $spa64ee0 = json_decode($sp43da22->get_response_str(), true); $spa64ee0['key'] = Helper::id_encode($sp7aa9af, 3566, $sp11513e); return $spa64ee0; } public static function verify($spf9076f, $sp23056b, $sp8fedd2, $sp9dd98f) { $sp43da22 = new Lib(config('services.geetest.id'), config('services.geetest.key')); Helper::id_decode($spf9076f, 3566, $sp09d21e); $sp7aa9af = $sp09d21e[1]; $sp11513e = $sp09d21e[4]; if ($sp11513e === 1) { $spf36da4 = $sp43da22->success_validate($sp23056b, $sp8fedd2, $sp9dd98f, $sp7aa9af); if ($spf36da4) { return true; } else { return false; } } else { if ($sp43da22->fail_validate($sp23056b, $sp8fedd2, $sp9dd98f)) { return true; } else { return false; } } } }