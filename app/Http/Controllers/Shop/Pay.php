<?php
namespace App\Http\Controllers\Shop; use App\Card; use App\Category; use App\Library\FundHelper; use App\Library\Helper; use App\Library\LogHelper; use App\Product; use App\Library\Response; use Gateway\Pay\Pay as GatewayPay; use App\Library\Geetest; use App\Mail\ProductCountWarn; use App\System; use Carbon\Carbon; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Log; use Illuminate\Support\Facades\Mail; class Pay extends Controller { public function __construct() { define('SYS_NAME', config('app.name')); define('SYS_URL', config('app.url')); define('SYS_URL_API', config('app.url_api')); } private $payApi = null; public function goPay($sp375069, $sp4b6ac9, $spfd0e56, $sp3c5b44, $spf1902f) { try { $spa67d5b = json_decode($sp3c5b44->config, true); $spa67d5b['payway'] = $sp3c5b44->way; GatewayPay::getDriver($sp3c5b44)->goPay($spa67d5b, $sp4b6ac9, $spfd0e56, $spfd0e56, $spf1902f); return self::renderResultPage($sp375069, array('success' => false, 'title' => '请稍后', 'msg' => '支付方式加载中，请稍后')); } catch (\Exception $spf95c2c) { return self::renderResultPage($sp375069, array('msg' => $spf95c2c->getMessage())); } } function buy(Request $sp375069) { $spddb0af = $sp375069->input('customer'); if (strlen($spddb0af) !== 32) { return self::renderResultPage($sp375069, array('msg' => '提交超时，请刷新购买页面并重新提交<br><br>
当前网址: ' . $sp375069->getQueryString() . '
提交内容: ' . var_export($spddb0af) . ', 提交长度:' . strlen($spddb0af) . '<br>
若您刷新后仍然出现此问题. 请加网站客服反馈')); } if (System::_getInt('vcode_shop_buy') === 1) { $this->validateCaptcha($sp375069); } $spca39ca = (int) $sp375069->input('category_id'); $sp138ddb = (int) $sp375069->input('product_id'); $spbefb16 = (int) $sp375069->input('count'); $sp696fca = $sp375069->input('coupon'); $spafb0ff = $sp375069->input('contact'); $sp0e54ce = $sp375069->input('contact_ext') ?? null; $sp4354f7 = !empty(@json_decode($sp0e54ce, true)['_mobile']); $sp602524 = (int) $sp375069->input('pay_id'); if (!$spca39ca || !$sp138ddb) { return self::renderResultPage($sp375069, array('msg' => '请选择商品')); } if (strlen($spafb0ff) < 1) { return self::renderResultPage($sp375069, array('msg' => '请输入联系方式')); } $spe4707e = Category::findOrFail($spca39ca); $sp6018c8 = Product::where('id', $sp138ddb)->where('category_id', $spca39ca)->where('enabled', 1)->with(array('user'))->first(); if ($sp6018c8 == null || $sp6018c8->user == null) { return self::renderResultPage($sp375069, array('msg' => '该商品未找到，请重新选择')); } if ($sp6018c8->password_open) { if ($sp6018c8->password !== $sp375069->input('product_password')) { return self::renderResultPage($sp375069, array('msg' => '商品密码输入错误')); } } else { if ($spe4707e->password_open) { if ($spe4707e->password !== $sp375069->input('category_password')) { if ($spe4707e->getTmpPassword() !== $sp375069->input('category_password')) { return self::renderResultPage($sp375069, array('msg' => '分类密码输入错误')); } } } } if ($spbefb16 < $sp6018c8->buy_min) { return self::renderResultPage($sp375069, array('msg' => '该商品最少购买' . $sp6018c8->buy_min . '件，请重新选择')); } if ($spbefb16 > $sp6018c8->buy_max) { return self::renderResultPage($sp375069, array('msg' => '该商品限购' . $sp6018c8->buy_max . '件，请重新选择')); } if ($sp6018c8->count < $spbefb16) { return self::renderResultPage($sp375069, array('msg' => '该商品库存不足')); } $sp3c5b44 = \App\Pay::find($sp602524); if ($sp3c5b44 == null || !$sp3c5b44->enabled) { return self::renderResultPage($sp375069, array('msg' => '支付方式未找到，请重新选择')); } $sp822493 = $sp6018c8->price; if ($sp6018c8->price_whole) { $spbc9d22 = json_decode($sp6018c8->price_whole, true); for ($sp9d4bce = count($spbc9d22) - 1; $sp9d4bce >= 0; $sp9d4bce--) { if ($spbefb16 >= (int) $spbc9d22[$sp9d4bce][0]) { $sp822493 = (int) $spbc9d22[$sp9d4bce][1]; break; } } } $sp6f4ec1 = $spbefb16 * $sp822493; $spf1902f = $sp6f4ec1; $sp246b2f = 0; $spebf712 = null; if ($sp6018c8->support_coupon && strlen($sp696fca) > 0) { $spede8d7 = \App\Coupon::where('user_id', $sp6018c8->user_id)->where('coupon', $sp696fca)->where('expire_at', '>', Carbon::now())->whereRaw('`count_used`<`count_all`')->get(); foreach ($spede8d7 as $sp5588d7) { if ($sp5588d7->category_id === -1 || $sp5588d7->category_id === $spca39ca && ($sp5588d7->product_id === -1 || $sp5588d7->product_id === $sp138ddb)) { if ($sp5588d7->discount_type === \App\Coupon::DISCOUNT_TYPE_AMOUNT && $spf1902f >= $sp5588d7->discount_val) { $spebf712 = $sp5588d7; $sp246b2f = $sp5588d7->discount_val; break; } if ($sp5588d7->discount_type === \App\Coupon::DISCOUNT_TYPE_PERCENT) { $spebf712 = $sp5588d7; $sp246b2f = (int) round($spf1902f * $sp5588d7->discount_val / 100); break; } } } if ($spebf712 === null) { return self::renderResultPage($sp375069, array('msg' => '优惠券信息错误，请重新输入')); } $spf1902f -= $sp246b2f; } $sp0c60f8 = (int) round($spf1902f * $sp3c5b44->fee_system); $sp8a5595 = $spf1902f - $sp0c60f8; $spe9e5fa = $sp4354f7 ? System::_getInt('sms_price', 10) : 0; $spf1902f += $spe9e5fa; $sp34b55b = $spbefb16 * $sp6018c8->cost; $sp4b6ac9 = \App\Order::unique_no(); try { DB::transaction(function () use($sp6018c8, $sp4b6ac9, $spebf712, $spafb0ff, $sp0e54ce, $spddb0af, $spbefb16, $sp34b55b, $sp6f4ec1, $spe9e5fa, $sp246b2f, $spf1902f, $sp3c5b44, $sp0c60f8, $sp8a5595) { if ($spebf712) { $spebf712->status = \App\Coupon::STATUS_USED; $spebf712->count_used++; $spebf712->save(); $spcc00c8 = '使用优惠券: ' . $spebf712->coupon; } else { $spcc00c8 = null; } $spf6b161 = new \App\Order(array('user_id' => $sp6018c8->user_id, 'order_no' => $sp4b6ac9, 'product_id' => $sp6018c8->id, 'product_name' => $sp6018c8->name, 'count' => $spbefb16, 'ip' => Helper::getIP(), 'customer' => $spddb0af, 'contact' => $spafb0ff, 'contact_ext' => $sp0e54ce, 'cost' => $sp34b55b, 'price' => $sp6f4ec1, 'sms_price' => $spe9e5fa, 'discount' => $sp246b2f, 'paid' => $spf1902f, 'pay_id' => $sp3c5b44->id, 'fee' => $sp0c60f8, 'system_fee' => $sp0c60f8, 'income' => $sp8a5595, 'status' => \App\Order::STATUS_UNPAY, 'remark' => $spcc00c8, 'created_at' => Carbon::now())); $spf6b161->saveOrFail(); }); } catch (\Throwable $spf95c2c) { Log::error('Shop.Pay.buy 下单失败', array('exception' => $spf95c2c)); return self::renderResultPage($sp375069, array('msg' => '发生错误，下单失败，请稍后重试')); } if ($spf1902f === 0) { $this->shipOrder($sp375069, $sp4b6ac9, $spf1902f, null); return route('pay.result', array($sp4b6ac9), false); } $spfd0e56 = $sp4b6ac9; return $this->goPay($sp375069, $sp4b6ac9, $spfd0e56, $sp3c5b44, $spf1902f); } function pay(Request $sp375069, $sp4b6ac9) { $spf6b161 = \App\Order::whereOrderNo($sp4b6ac9)->first(); if ($spf6b161 == null) { return self::renderResultPage($sp375069, array('msg' => '订单未找到，请重试')); } if ($spf6b161->status !== \App\Order::STATUS_UNPAY) { return redirect('/pay/result/' . $sp4b6ac9); } $spe32066 = 'pay: ' . $spf6b161->pay_id; $sp3c5b44 = $spf6b161->pay; if (!$sp3c5b44) { \Log::error($spe32066 . ' cannot find Pay'); return $this->renderResultPage($sp375069, array('msg' => '支付方式未找到')); } $spe32066 .= ',' . $sp3c5b44->driver; $spa67d5b = json_decode($sp3c5b44->config, true); $spa67d5b['payway'] = $sp3c5b44->way; $spa67d5b['out_trade_no'] = $sp4b6ac9; try { $this->payApi = GatewayPay::getDriver($sp3c5b44); } catch (\Exception $spf95c2c) { \Log::error($spe32066 . ' cannot find Driver: ' . $spf95c2c->getMessage()); return $this->renderResultPage($sp375069, array('msg' => '支付驱动未找到')); } if ($this->payApi->verify($spa67d5b, function ($sp4b6ac9, $sp46b577, $spbd251c) use($sp375069) { try { $this->shipOrder($sp375069, $sp4b6ac9, $sp46b577, $spbd251c); } catch (\Exception $spf95c2c) { $this->renderResultPage($sp375069, array('success' => false, 'msg' => $spf95c2c->getMessage())); } })) { \Log::notice($spe32066 . ' already success' . '

'); return redirect('/pay/result/' . $sp4b6ac9); } if ($spf6b161->created_at < Carbon::now()->addMinutes(-5)) { return $this->renderResultPage($sp375069, array('msg' => '当前订单长时间未支付已作废, 请重新下单')); } $sp6018c8 = Product::where('id', $spf6b161->product_id)->where('enabled', 1)->first(); if ($sp6018c8 == null) { return self::renderResultPage($sp375069, array('msg' => '该商品已下架')); } $sp6018c8->setAttribute('count', count($sp6018c8->cards) ? $sp6018c8->cards[0]->count : 0); if ($sp6018c8->count < $spf6b161->count) { return self::renderResultPage($sp375069, array('msg' => '该商品库存不足')); } $spfd0e56 = $sp4b6ac9; return $this->goPay($sp375069, $sp4b6ac9, $spfd0e56, $sp3c5b44, $spf6b161->paid); } function qrcode(Request $sp375069, $sp4b6ac9, $sp983867) { $spf6b161 = \App\Order::whereOrderNo($sp4b6ac9)->with('product')->first(); if ($spf6b161 == null) { return self::renderResultPage($sp375069, array('msg' => '订单未找到，请重试')); } if ($spf6b161->created_at < Carbon::now()->addMinutes(-5)) { return $this->renderResultPage($sp375069, array('msg' => '当前订单长时间未支付已作废, 请重新下单')); } if ($spf6b161->product_id !== \App\Product::ID_API) { $sp6018c8 = $spf6b161->product; if ($sp6018c8 == null) { return self::renderResultPage($sp375069, array('msg' => '商品未找到，请重试')); } if ($sp6018c8->count < $spf6b161->count) { return self::renderResultPage($sp375069, array('msg' => '该商品库存不足')); } } if (strpos($sp983867, '..')) { return $this->msg('你玩你妈呢'); } return view('pay/' . $sp983867, array('pay_id' => $spf6b161->pay_id, 'name' => $spf6b161->product_id === \App\Product::ID_API ? $spf6b161->api_out_no : $spf6b161->product->name . ' x ' . $spf6b161->count . '件', 'amount' => $spf6b161->paid, 'qrcode' => $sp375069->get('url'), 'id' => $sp4b6ac9)); } function qrQuery(Request $sp375069, $sp602524) { $sp92a070 = $sp375069->input('id', ''); return self::payReturn($sp375069, $sp602524, $sp92a070); } function payReturn(Request $sp375069, $sp602524, $sp4b6ac9 = '') { $spe32066 = 'payReturn: ' . $sp602524; \Log::debug($spe32066); $sp3c5b44 = \App\Pay::where('id', $sp602524)->first(); if (!$sp3c5b44) { return $this->renderResultPage($sp375069, array('success' => 0, 'msg' => '支付方式错误')); } $spe32066 .= ',' . $sp3c5b44->driver; if (strlen($sp4b6ac9) > 0) { $spf6b161 = \App\Order::whereOrderNo($sp4b6ac9)->firstOrFail(); if ($spf6b161 && ($spf6b161->status === \App\Order::STATUS_PAID || $spf6b161->status === \App\Order::STATUS_SUCCESS)) { \Log::notice($spe32066 . ' already success' . '

'); if ($sp375069->ajax()) { return self::renderResultPage($sp375069, array('success' => 1, 'data' => '/pay/result/' . $sp4b6ac9), array('order' => $spf6b161)); } else { return redirect('/pay/result/' . $sp4b6ac9); } } } try { $this->payApi = GatewayPay::getDriver($sp3c5b44); } catch (\Exception $spf95c2c) { \Log::error($spe32066 . ' cannot find Driver: ' . $spf95c2c->getMessage()); return $this->renderResultPage($sp375069, array('success' => 0, 'msg' => '支付驱动未找到')); } $spa67d5b = json_decode($sp3c5b44->config, true); $spa67d5b['out_trade_no'] = $sp4b6ac9; $spa67d5b['payway'] = $sp3c5b44->way; Log::debug($spe32066 . ' will verify'); if ($this->payApi->verify($spa67d5b, function ($sp58d92a, $sp46b577, $spbd251c) use($sp375069, $spe32066, &$sp4b6ac9) { $sp4b6ac9 = $sp58d92a; try { Log::debug($spe32066 . " shipOrder start, order_no: {$sp4b6ac9}, amount: {$sp46b577}, trade_no: {$spbd251c}"); $this->shipOrder($sp375069, $sp4b6ac9, $sp46b577, $spbd251c); Log::debug($spe32066 . ' shipOrder end, order_no: ' . $sp4b6ac9); } catch (\Exception $spf95c2c) { Log::error($spe32066 . ' shipOrder Exception: ' . $spf95c2c->getMessage(), array('exception' => $spf95c2c)); } })) { Log::debug($spe32066 . ' verify finished: 1' . '

'); if ($sp375069->ajax()) { return self::renderResultPage($sp375069, array('success' => 1, 'data' => '/pay/result/' . $sp4b6ac9)); } else { return redirect('/pay/result/' . $sp4b6ac9); } } else { Log::debug($spe32066 . ' verify finished: 0' . '

'); return $this->renderResultPage($sp375069, array('success' => 0, 'msg' => '支付验证失败，您可以稍后查看支付状态。')); } } function payNotify(Request $sp375069, $sp602524) { $spe32066 = 'payNotify pay_id: ' . $sp602524; Log::debug($spe32066); $sp3c5b44 = \App\Pay::where('id', $sp602524)->first(); if (!$sp3c5b44) { Log::error($spe32066 . ' cannot find PayModel'); echo 'fail'; die; } $spe32066 .= ',' . $sp3c5b44->driver; try { $this->payApi = GatewayPay::getDriver($sp3c5b44); } catch (\Exception $spf95c2c) { Log::error($spe32066 . ' cannot find Driver: ' . $spf95c2c->getMessage()); echo 'fail'; die; } $spa67d5b = json_decode($sp3c5b44->config, true); $spa67d5b['payway'] = $sp3c5b44->way; $spa67d5b['isNotify'] = true; Log::debug($spe32066 . ' will verify'); $sp6706d8 = $this->payApi->verify($spa67d5b, function ($sp4b6ac9, $sp46b577, $spbd251c) use($sp375069, $spe32066) { try { Log::debug($spe32066 . " shipOrder start, order_no: {$sp4b6ac9}, amount: {$sp46b577}, trade_no: {$spbd251c}"); $this->shipOrder($sp375069, $sp4b6ac9, $sp46b577, $spbd251c); Log::debug($spe32066 . ' shipOrder end, order_no: ' . $sp4b6ac9); } catch (\Exception $spf95c2c) { Log::error($spe32066 . ' shipOrder Exception: ' . $spf95c2c->getMessage()); } }); Log::debug($spe32066 . ' notify finished: ' . (int) $sp6706d8 . '

'); die; } function result(Request $sp375069, $sp4b6ac9) { $spf6b161 = \App\Order::where('order_no', $sp4b6ac9)->first(); if ($spf6b161 == null) { return self::renderResultPage($sp375069, array('msg' => '订单未找到，请重试')); } if ($spf6b161->status === \App\Order::STATUS_PAID) { $sp6a01c6 = $spf6b161->user->qq; if ($spf6b161->product) { if ($spf6b161->product->delivery === \App\Product::DELIVERY_MANUAL) { $spa01063 = '您购买的为手动充值商品，请耐心等待处理'; } else { $spa01063 = '商家库存不足，因此没有自动发货，请联系商家客服发货'; } } else { $spa01063 = '卖家已删除此商品，请联系客服退款'; } if ($sp6a01c6) { $spa01063 .= '<br><a href="http://wpa.qq.com/msgrd?v=3&uin=' . $sp6a01c6 . '&site=qq&menu=yes" target="_blank">客服QQ:' . $sp6a01c6 . '</a>'; } return self::renderResultPage($sp375069, array('success' => false, 'title' => '订单已支付', 'msg' => $spa01063), array('order' => $spf6b161)); } elseif ($spf6b161->status >= \App\Order::STATUS_SUCCESS) { return self::showOrderResult($sp375069, $spf6b161); } return self::renderResultPage($sp375069, array('success' => false, 'msg' => $spf6b161->remark ? '失败原因:<br>' . $spf6b161->remark : '订单未支付成功<br>如果您已经支付请耐心等待或联系客服解决'), array('order' => $spf6b161)); } function renderResultPage(Request $sp375069, $spc4e359, $sp41d5bf = array()) { if ($sp375069->ajax()) { if (@$spc4e359['success']) { return Response::success($spc4e359['data']); } else { return Response::fail('error', $spc4e359['msg']); } } else { return view('pay.result', array_merge(array('result' => $spc4e359, 'data' => $sp41d5bf), $sp41d5bf)); } } function shipOrder($sp375069, $sp4b6ac9, $sp46b577, $spbd251c) { $spf6b161 = \App\Order::whereOrderNo($sp4b6ac9)->first(); if ($spf6b161 === null) { Log::error('shipOrder: No query results for model [App\\Order:' . $sp4b6ac9 . ',trade_no:' . $spbd251c . ',amount:' . $sp46b577 . ']. die(\'success\');'); die('success'); } if ($spf6b161->paid > $sp46b577) { Log::alert('shipOrder, price may error, order_no:' . $sp4b6ac9 . ', paid:' . $spf6b161->paid . ', $amount get:' . $sp46b577); $spf6b161->remark = '支付金额(' . sprintf('%0.2f', $sp46b577 / 100) . ') 小于 订单金额(' . sprintf('%0.2f', $spf6b161->paid / 100) . ')'; $spf6b161->save(); throw new \Exception($spf6b161->remark); } $sp6018c8 = null; if ($spf6b161->status === \App\Order::STATUS_UNPAY) { Log::debug('shipOrder.first_process:' . $sp4b6ac9); if (FundHelper::orderSuccess($spf6b161->id, function ($sp5b2c0f) use($spbd251c, &$spf6b161, &$sp6018c8) { $spf6b161 = $sp5b2c0f; if ($spf6b161->status !== \App\Order::STATUS_UNPAY) { \Log::debug('Shop.Pay.shipOrder: .first_process:' . $spf6b161->order_no . ' already processed! #2'); return false; } $sp6018c8 = $spf6b161->product()->lockForUpdate()->firstOrFail(); $spf6b161->pay_trade_no = $spbd251c; $spf6b161->paid_at = Carbon::now(); if ($sp6018c8->delivery === \App\Product::DELIVERY_MANUAL) { $spf6b161->status = \App\Order::STATUS_PAID; $spf6b161->send_status = \App\Order::SEND_STATUS_CARD_UN; $spf6b161->saveOrFail(); return true; } if ($sp6018c8->delivery === \App\Product::DELIVERY_API) { $sp10f8f9 = $sp6018c8->createApiCards($spf6b161); } else { $sp10f8f9 = Card::where('product_id', $spf6b161->product_id)->whereRaw('`count_sold`<`count_all`')->take($spf6b161->count)->lockForUpdate()->get(); } $sp3d308c = false; if (count($sp10f8f9) === $spf6b161->count) { $sp3d308c = true; } else { if (count($sp10f8f9)) { foreach ($sp10f8f9 as $spd915af) { if ($spd915af->type === \App\Card::TYPE_REPEAT && $spd915af->count >= $spf6b161->count) { $sp10f8f9 = array($spd915af); $sp3d308c = true; break; } } } } if ($sp3d308c === false) { Log::alert('Shop.Pay.shipOrder: 订单:' . $spf6b161->order_no . ', 购买数量:' . $spf6b161->count . ', 卡数量:' . count($sp10f8f9) . ' 卡密不足(已支付 未发货)'); $spf6b161->status = \App\Order::STATUS_PAID; $spf6b161->saveOrFail(); return true; } else { $spf5f0d8 = array(); foreach ($sp10f8f9 as $spd915af) { $spf5f0d8[] = $spd915af->id; } $spf6b161->cards()->attach($spf5f0d8); if (count($sp10f8f9) === 1 && $sp10f8f9[0]->type === \App\Card::TYPE_REPEAT) { \App\Card::where('id', $spf5f0d8[0])->update(array('status' => \App\Card::STATUS_SOLD, 'count_sold' => DB::raw('`count_sold`+' . $spf6b161->count))); } else { \App\Card::whereIn('id', $spf5f0d8)->update(array('status' => \App\Card::STATUS_SOLD, 'count_sold' => DB::raw('`count_sold`+1'))); } $spf6b161->status = \App\Order::STATUS_SUCCESS; $spf6b161->saveOrFail(); $sp6018c8->count_sold += $spf6b161->count; $sp6018c8->saveOrFail(); return FundHelper::ACTION_CONTINUE; } })) { if ($sp6018c8->count_warn > 0 && $sp6018c8->count < $sp6018c8->count_warn) { try { Mail::to($spf6b161->user->email)->Queue(new ProductCountWarn($sp6018c8, $sp6018c8->count)); } catch (\Throwable $spf95c2c) { LogHelper::setLogFile('mail'); Log::error('shipOrder.count_warn error', array('product_id' => $spf6b161->product_id, 'email' => $spf6b161->user->email, 'exception' => $spf95c2c->getMessage())); LogHelper::setLogFile('card'); } } if (System::_getInt('mail_send_order')) { $sp8b9515 = @json_decode($spf6b161->contact_ext, true)['_mail']; if ($sp8b9515) { $spf6b161->sendEmail($sp8b9515); } } if ($spf6b161->status === \App\Order::STATUS_SUCCESS && System::_getInt('sms_send_order')) { $spcb4cb7 = @json_decode($spf6b161->contact_ext, true)['_mobile']; if ($spcb4cb7) { $spf6b161->sendSms($spcb4cb7); } } } else { if ($spf6b161->status !== \App\Order::STATUS_UNPAY) { } else { Log::error('Pay.shipOrder.orderSuccess Failed.'); return FALSE; } } } else { Log::debug('Shop.Pay.shipOrder: .order_no:' . $spf6b161->order_no . ' already processed! #1'); } return FALSE; } private function showOrderResult($sp375069, $spf6b161) { return self::renderResultPage($sp375069, array('success' => true, 'msg' => $spf6b161->getSendMessage()), array('card_txt' => join('&#013;&#010;', $spf6b161->getCardsArray()), 'order' => $spf6b161, 'product' => $spf6b161->product)); } }