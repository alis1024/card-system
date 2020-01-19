<?php
namespace App\Http\Controllers\Shop; use App\Card; use App\Category; use App\Library\FundHelper; use App\Library\Helper; use App\Library\LogHelper; use App\Product; use App\Library\Response; use Gateway\Pay\Pay as GatewayPay; use App\Library\Geetest; use App\Mail\ProductCountWarn; use App\System; use Carbon\Carbon; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Log; use Illuminate\Support\Facades\Mail; class Pay extends Controller { public function __construct() { define('SYS_NAME', config('app.name')); define('SYS_URL', config('app.url')); define('SYS_URL_API', config('app.url_api')); } private $payApi = null; public function goPay($sp7f7104, $sp0f479a, $spb9f90e, $sp5c2227, $spe64b6d) { try { $spee3857 = json_decode($sp5c2227->config, true); $spee3857['payway'] = $sp5c2227->way; GatewayPay::getDriver($sp5c2227)->goPay($spee3857, $sp0f479a, $spb9f90e, $spb9f90e, $spe64b6d); return self::renderResultPage($sp7f7104, array('success' => false, 'title' => '请稍后', 'msg' => '支付方式加载中，请稍后')); } catch (\Exception $spd54c56) { return self::renderResultPage($sp7f7104, array('msg' => $spd54c56->getMessage())); } } function buy(Request $sp7f7104) { $sp68c4f7 = $sp7f7104->input('customer'); if (strlen($sp68c4f7) !== 32) { return self::renderResultPage($sp7f7104, array('msg' => '提交超时，请刷新购买页面并重新提交<br><br>
当前网址: ' . $sp7f7104->getQueryString() . '
提交内容: ' . var_export($sp68c4f7) . ', 提交长度:' . strlen($sp68c4f7) . '<br>
若您刷新后仍然出现此问题. 请加网站客服反馈')); } if (System::_getInt('vcode_shop_buy') === 1) { $this->validateCaptcha($sp7f7104); } $sp92ffbb = (int) $sp7f7104->input('category_id'); $sp727288 = (int) $sp7f7104->input('product_id'); $spf26c0c = (int) $sp7f7104->input('count'); $sp48f854 = $sp7f7104->input('coupon'); $spd4452a = $sp7f7104->input('contact'); $sp6d2065 = $sp7f7104->input('contact_ext') ?? null; $sp52903e = !empty(@json_decode($sp6d2065, true)['_mobile']); $sp6c5379 = (int) $sp7f7104->input('pay_id'); if (!$sp92ffbb || !$sp727288) { return self::renderResultPage($sp7f7104, array('msg' => '请选择商品')); } if (strlen($spd4452a) < 1) { return self::renderResultPage($sp7f7104, array('msg' => '请输入联系方式')); } $sp45344f = Category::findOrFail($sp92ffbb); $spfa410d = Product::where('id', $sp727288)->where('category_id', $sp92ffbb)->where('enabled', 1)->with(array('user'))->first(); if ($spfa410d == null || $spfa410d->user == null) { return self::renderResultPage($sp7f7104, array('msg' => '该商品未找到，请重新选择')); } if ($spfa410d->password_open) { if ($spfa410d->password !== $sp7f7104->input('product_password')) { return self::renderResultPage($sp7f7104, array('msg' => '商品密码输入错误')); } } else { if ($sp45344f->password_open) { if ($sp45344f->password !== $sp7f7104->input('category_password')) { if ($sp45344f->getTmpPassword() !== $sp7f7104->input('category_password')) { return self::renderResultPage($sp7f7104, array('msg' => '分类密码输入错误')); } } } } if ($spf26c0c < $spfa410d->buy_min) { return self::renderResultPage($sp7f7104, array('msg' => '该商品最少购买' . $spfa410d->buy_min . '件，请重新选择')); } if ($spf26c0c > $spfa410d->buy_max) { return self::renderResultPage($sp7f7104, array('msg' => '该商品限购' . $spfa410d->buy_max . '件，请重新选择')); } if ($spfa410d->count < $spf26c0c) { return self::renderResultPage($sp7f7104, array('msg' => '该商品库存不足')); } $sp5c2227 = \App\Pay::find($sp6c5379); if ($sp5c2227 == null || !$sp5c2227->enabled) { return self::renderResultPage($sp7f7104, array('msg' => '支付方式未找到，请重新选择')); } $sp5f0834 = $spfa410d->price; if ($spfa410d->price_whole) { $sp61aab0 = json_decode($spfa410d->price_whole, true); for ($spf3a567 = count($sp61aab0) - 1; $spf3a567 >= 0; $spf3a567--) { if ($spf26c0c >= (int) $sp61aab0[$spf3a567][0]) { $sp5f0834 = (int) $sp61aab0[$spf3a567][1]; break; } } } $spf45afd = $spf26c0c * $sp5f0834; $spe64b6d = $spf45afd; $sp5638df = 0; $spc5ab9d = null; if ($spfa410d->support_coupon && strlen($sp48f854) > 0) { $sp832842 = \App\Coupon::where('user_id', $spfa410d->user_id)->where('coupon', $sp48f854)->where('expire_at', '>', Carbon::now())->whereRaw('`count_used`<`count_all`')->get(); foreach ($sp832842 as $spde062e) { if ($spde062e->category_id === -1 || $spde062e->category_id === $sp92ffbb && ($spde062e->product_id === -1 || $spde062e->product_id === $sp727288)) { if ($spde062e->discount_type === \App\Coupon::DISCOUNT_TYPE_AMOUNT && $spe64b6d >= $spde062e->discount_val) { $spc5ab9d = $spde062e; $sp5638df = $spde062e->discount_val; break; } if ($spde062e->discount_type === \App\Coupon::DISCOUNT_TYPE_PERCENT) { $spc5ab9d = $spde062e; $sp5638df = (int) round($spe64b6d * $spde062e->discount_val / 100); break; } } } if ($spc5ab9d === null) { return self::renderResultPage($sp7f7104, array('msg' => '优惠券信息错误，请重新输入')); } $spe64b6d -= $sp5638df; } $spbf035a = (int) round($spe64b6d * $sp5c2227->fee_system); $spdbfe2d = $spe64b6d - $spbf035a; $sp29b088 = $sp52903e ? System::_getInt('sms_price', 10) : 0; $spe64b6d += $sp29b088; $sp5791b2 = $spf26c0c * $spfa410d->cost; $sp0f479a = \App\Order::unique_no(); try { DB::transaction(function () use($spfa410d, $sp0f479a, $spc5ab9d, $spd4452a, $sp6d2065, $sp68c4f7, $spf26c0c, $sp5791b2, $spf45afd, $sp29b088, $sp5638df, $spe64b6d, $sp5c2227, $spbf035a, $spdbfe2d) { if ($spc5ab9d) { $spc5ab9d->status = \App\Coupon::STATUS_USED; $spc5ab9d->count_used++; $spc5ab9d->save(); $spcd2349 = '使用优惠券: ' . $spc5ab9d->coupon; } else { $spcd2349 = null; } $sp79a792 = new \App\Order(array('user_id' => $spfa410d->user_id, 'order_no' => $sp0f479a, 'product_id' => $spfa410d->id, 'product_name' => $spfa410d->name, 'count' => $spf26c0c, 'ip' => Helper::getIP(), 'customer' => $sp68c4f7, 'contact' => $spd4452a, 'contact_ext' => $sp6d2065, 'cost' => $sp5791b2, 'price' => $spf45afd, 'sms_price' => $sp29b088, 'discount' => $sp5638df, 'paid' => $spe64b6d, 'pay_id' => $sp5c2227->id, 'fee' => $spbf035a, 'system_fee' => $spbf035a, 'income' => $spdbfe2d, 'status' => \App\Order::STATUS_UNPAY, 'remark' => $spcd2349, 'created_at' => Carbon::now())); $sp79a792->saveOrFail(); }); } catch (\Throwable $spd54c56) { Log::error('Shop.Pay.buy 下单失败', array('exception' => $spd54c56)); return self::renderResultPage($sp7f7104, array('msg' => '发生错误，下单失败，请稍后重试')); } if ($spe64b6d === 0) { $this->shipOrder($sp7f7104, $sp0f479a, $spe64b6d, null); return route('pay.result', array($sp0f479a), false); } $spb9f90e = $sp0f479a; return $this->goPay($sp7f7104, $sp0f479a, $spb9f90e, $sp5c2227, $spe64b6d); } function pay(Request $sp7f7104, $sp0f479a) { $sp79a792 = \App\Order::whereOrderNo($sp0f479a)->first(); if ($sp79a792 == null) { return self::renderResultPage($sp7f7104, array('msg' => '订单未找到，请重试')); } if ($sp79a792->status !== \App\Order::STATUS_UNPAY) { return redirect('/pay/result/' . $sp0f479a); } $sp1527f7 = 'pay: ' . $sp79a792->pay_id; $sp5c2227 = $sp79a792->pay; if (!$sp5c2227) { \Log::error($sp1527f7 . ' cannot find Pay'); return $this->renderResultPage($sp7f7104, array('msg' => '支付方式未找到')); } $sp1527f7 .= ',' . $sp5c2227->driver; $spee3857 = json_decode($sp5c2227->config, true); $spee3857['payway'] = $sp5c2227->way; $spee3857['out_trade_no'] = $sp0f479a; try { $this->payApi = GatewayPay::getDriver($sp5c2227); } catch (\Exception $spd54c56) { \Log::error($sp1527f7 . ' cannot find Driver: ' . $spd54c56->getMessage()); return $this->renderResultPage($sp7f7104, array('msg' => '支付驱动未找到')); } if ($this->payApi->verify($spee3857, function ($sp0f479a, $sp23d931, $spef215b) use($sp7f7104) { try { $this->shipOrder($sp7f7104, $sp0f479a, $sp23d931, $spef215b); } catch (\Exception $spd54c56) { $this->renderResultPage($sp7f7104, array('success' => false, 'msg' => $spd54c56->getMessage())); } })) { \Log::notice($sp1527f7 . ' already success' . '

'); return redirect('/pay/result/' . $sp0f479a); } if ($sp79a792->created_at < Carbon::now()->addMinutes(-5)) { return $this->renderResultPage($sp7f7104, array('msg' => '当前订单长时间未支付已作废, 请重新下单')); } $spfa410d = Product::where('id', $sp79a792->product_id)->where('enabled', 1)->first(); if ($spfa410d == null) { return self::renderResultPage($sp7f7104, array('msg' => '该商品已下架')); } $spfa410d->setAttribute('count', count($spfa410d->cards) ? $spfa410d->cards[0]->count : 0); if ($spfa410d->count < $sp79a792->count) { return self::renderResultPage($sp7f7104, array('msg' => '该商品库存不足')); } $spb9f90e = $sp0f479a; return $this->goPay($sp7f7104, $sp0f479a, $spb9f90e, $sp5c2227, $sp79a792->paid); } function qrcode(Request $sp7f7104, $sp0f479a, $sp37b7c1) { $sp79a792 = \App\Order::whereOrderNo($sp0f479a)->with('product')->first(); if ($sp79a792 == null) { return self::renderResultPage($sp7f7104, array('msg' => '订单未找到，请重试')); } if ($sp79a792->created_at < Carbon::now()->addMinutes(-5)) { return $this->renderResultPage($sp7f7104, array('msg' => '当前订单长时间未支付已作废, 请重新下单')); } if ($sp79a792->product_id !== \App\Product::ID_API) { $spfa410d = $sp79a792->product; if ($spfa410d == null) { return self::renderResultPage($sp7f7104, array('msg' => '商品未找到，请重试')); } if ($spfa410d->count < $sp79a792->count) { return self::renderResultPage($sp7f7104, array('msg' => '该商品库存不足')); } } if (strpos($sp37b7c1, '..')) { return $this->msg('你玩你妈呢'); } return view('pay/' . $sp37b7c1, array('pay_id' => $sp79a792->pay_id, 'name' => $sp79a792->product_id === \App\Product::ID_API ? $sp79a792->api_out_no : $sp79a792->product->name . ' x ' . $sp79a792->count . '件', 'amount' => $sp79a792->paid, 'qrcode' => $sp7f7104->get('url'), 'id' => $sp0f479a)); } function qrQuery(Request $sp7f7104, $sp6c5379) { $sp2ea57b = $sp7f7104->input('id', ''); return self::payReturn($sp7f7104, $sp6c5379, $sp2ea57b); } function payReturn(Request $sp7f7104, $sp6c5379, $sp0f479a = '') { $sp1527f7 = 'payReturn: ' . $sp6c5379; \Log::debug($sp1527f7); $sp5c2227 = \App\Pay::where('id', $sp6c5379)->first(); if (!$sp5c2227) { return $this->renderResultPage($sp7f7104, array('success' => 0, 'msg' => '支付方式错误')); } $sp1527f7 .= ',' . $sp5c2227->driver; if (strlen($sp0f479a) > 0) { $sp79a792 = \App\Order::whereOrderNo($sp0f479a)->firstOrFail(); if ($sp79a792 && ($sp79a792->status === \App\Order::STATUS_PAID || $sp79a792->status === \App\Order::STATUS_SUCCESS)) { \Log::notice($sp1527f7 . ' already success' . '

'); if ($sp7f7104->ajax()) { return self::renderResultPage($sp7f7104, array('success' => 1, 'data' => '/pay/result/' . $sp0f479a), array('order' => $sp79a792)); } else { return redirect('/pay/result/' . $sp0f479a); } } } try { $this->payApi = GatewayPay::getDriver($sp5c2227); } catch (\Exception $spd54c56) { \Log::error($sp1527f7 . ' cannot find Driver: ' . $spd54c56->getMessage()); return $this->renderResultPage($sp7f7104, array('success' => 0, 'msg' => '支付驱动未找到')); } $spee3857 = json_decode($sp5c2227->config, true); $spee3857['out_trade_no'] = $sp0f479a; $spee3857['payway'] = $sp5c2227->way; Log::debug($sp1527f7 . ' will verify'); if ($this->payApi->verify($spee3857, function ($sp30d314, $sp23d931, $spef215b) use($sp7f7104, $sp1527f7, &$sp0f479a) { $sp0f479a = $sp30d314; try { Log::debug($sp1527f7 . " shipOrder start, order_no: {$sp0f479a}, amount: {$sp23d931}, trade_no: {$spef215b}"); $this->shipOrder($sp7f7104, $sp0f479a, $sp23d931, $spef215b); Log::debug($sp1527f7 . ' shipOrder end, order_no: ' . $sp0f479a); } catch (\Exception $spd54c56) { Log::error($sp1527f7 . ' shipOrder Exception: ' . $spd54c56->getMessage(), array('exception' => $spd54c56)); } })) { Log::debug($sp1527f7 . ' verify finished: 1' . '

'); if ($sp7f7104->ajax()) { return self::renderResultPage($sp7f7104, array('success' => 1, 'data' => '/pay/result/' . $sp0f479a)); } else { return redirect('/pay/result/' . $sp0f479a); } } else { Log::debug($sp1527f7 . ' verify finished: 0' . '

'); return $this->renderResultPage($sp7f7104, array('success' => 0, 'msg' => '支付验证失败，您可以稍后查看支付状态。')); } } function payNotify(Request $sp7f7104, $sp6c5379) { $sp1527f7 = 'payNotify pay_id: ' . $sp6c5379; Log::debug($sp1527f7); $sp5c2227 = \App\Pay::where('id', $sp6c5379)->first(); if (!$sp5c2227) { Log::error($sp1527f7 . ' cannot find PayModel'); echo 'fail'; die; } $sp1527f7 .= ',' . $sp5c2227->driver; try { $this->payApi = GatewayPay::getDriver($sp5c2227); } catch (\Exception $spd54c56) { Log::error($sp1527f7 . ' cannot find Driver: ' . $spd54c56->getMessage()); echo 'fail'; die; } $spee3857 = json_decode($sp5c2227->config, true); $spee3857['payway'] = $sp5c2227->way; $spee3857['isNotify'] = true; Log::debug($sp1527f7 . ' will verify'); $sp6e55ba = $this->payApi->verify($spee3857, function ($sp0f479a, $sp23d931, $spef215b) use($sp7f7104, $sp1527f7) { try { Log::debug($sp1527f7 . " shipOrder start, order_no: {$sp0f479a}, amount: {$sp23d931}, trade_no: {$spef215b}"); $this->shipOrder($sp7f7104, $sp0f479a, $sp23d931, $spef215b); Log::debug($sp1527f7 . ' shipOrder end, order_no: ' . $sp0f479a); } catch (\Exception $spd54c56) { Log::error($sp1527f7 . ' shipOrder Exception: ' . $spd54c56->getMessage()); } }); Log::debug($sp1527f7 . ' notify finished: ' . (int) $sp6e55ba . '

'); die; } function result(Request $sp7f7104, $sp0f479a) { $sp79a792 = \App\Order::where('order_no', $sp0f479a)->first(); if ($sp79a792 == null) { return self::renderResultPage($sp7f7104, array('msg' => '订单未找到，请重试')); } if ($sp79a792->status === \App\Order::STATUS_PAID) { $spd013af = $sp79a792->user->qq; if ($sp79a792->product) { if ($sp79a792->product->delivery === \App\Product::DELIVERY_MANUAL) { $sp2d2f79 = '您购买的为手动充值商品，请耐心等待处理'; } else { $sp2d2f79 = '商家库存不足，因此没有自动发货，请联系商家客服发货'; } } else { $sp2d2f79 = '卖家已删除此商品，请联系客服退款'; } if ($spd013af) { $sp2d2f79 .= '<br><a href="http://wpa.qq.com/msgrd?v=3&uin=' . $spd013af . '&site=qq&menu=yes" target="_blank">客服QQ:' . $spd013af . '</a>'; } return self::renderResultPage($sp7f7104, array('success' => false, 'title' => '订单已支付', 'msg' => $sp2d2f79), array('order' => $sp79a792)); } elseif ($sp79a792->status >= \App\Order::STATUS_SUCCESS) { return self::showOrderResult($sp7f7104, $sp79a792); } return self::renderResultPage($sp7f7104, array('success' => false, 'msg' => $sp79a792->remark ? '失败原因:<br>' . $sp79a792->remark : '订单未支付成功<br>如果您已经支付请耐心等待或联系客服解决'), array('order' => $sp79a792)); } function renderResultPage(Request $sp7f7104, $sp9a8387, $sp8a07dc = array()) { if ($sp7f7104->ajax()) { if (@$sp9a8387['success']) { return Response::success($sp9a8387['data']); } else { return Response::fail('error', $sp9a8387['msg']); } } else { return view('pay.result', array_merge(array('result' => $sp9a8387, 'data' => $sp8a07dc), $sp8a07dc)); } } function shipOrder($sp7f7104, $sp0f479a, $sp23d931, $spef215b) { $sp79a792 = \App\Order::whereOrderNo($sp0f479a)->first(); if ($sp79a792 === null) { Log::error('shipOrder: No query results for model [App\\Order:' . $sp0f479a . ',trade_no:' . $spef215b . ',amount:' . $sp23d931 . ']. die(\'success\');'); die('success'); } if ($sp79a792->paid > $sp23d931) { Log::alert('shipOrder, price may error, order_no:' . $sp0f479a . ', paid:' . $sp79a792->paid . ', $amount get:' . $sp23d931); $sp79a792->remark = '支付金额(' . sprintf('%0.2f', $sp23d931 / 100) . ') 小于 订单金额(' . sprintf('%0.2f', $sp79a792->paid / 100) . ')'; $sp79a792->save(); throw new \Exception($sp79a792->remark); } $spfa410d = null; if ($sp79a792->status === \App\Order::STATUS_UNPAY) { Log::debug('shipOrder.first_process:' . $sp0f479a); if (FundHelper::orderSuccess($sp79a792->id, function ($spafe8a8) use($spef215b, &$sp79a792, &$spfa410d) { $sp79a792 = $spafe8a8; if ($sp79a792->status !== \App\Order::STATUS_UNPAY) { \Log::debug('Shop.Pay.shipOrder: .first_process:' . $sp79a792->order_no . ' already processed! #2'); return false; } $spfa410d = $sp79a792->product()->lockForUpdate()->firstOrFail(); $sp79a792->pay_trade_no = $spef215b; $sp79a792->paid_at = Carbon::now(); if ($spfa410d->delivery === \App\Product::DELIVERY_MANUAL) { $sp79a792->status = \App\Order::STATUS_PAID; $sp79a792->send_status = \App\Order::SEND_STATUS_CARD_UN; $sp79a792->saveOrFail(); return true; } if ($spfa410d->delivery === \App\Product::DELIVERY_API) { $spefb732 = $spfa410d->createApiCards($sp79a792); } else { $spefb732 = Card::where('product_id', $sp79a792->product_id)->whereRaw('`count_sold`<`count_all`')->take($sp79a792->count)->lockForUpdate()->get(); } $sp299661 = false; if (count($spefb732) === $sp79a792->count) { $sp299661 = true; } else { if (count($spefb732)) { foreach ($spefb732 as $sp5eba29) { if ($sp5eba29->type === \App\Card::TYPE_REPEAT && $sp5eba29->count >= $sp79a792->count) { $spefb732 = array($sp5eba29); $sp299661 = true; break; } } } } if ($sp299661 === false) { Log::alert('Shop.Pay.shipOrder: 订单:' . $sp79a792->order_no . ', 购买数量:' . $sp79a792->count . ', 卡数量:' . count($spefb732) . ' 卡密不足(已支付 未发货)'); $sp79a792->status = \App\Order::STATUS_PAID; $sp79a792->saveOrFail(); return true; } else { $sp435e7d = array(); foreach ($spefb732 as $sp5eba29) { $sp435e7d[] = $sp5eba29->id; } $sp79a792->cards()->attach($sp435e7d); if (count($spefb732) === 1 && $spefb732[0]->type === \App\Card::TYPE_REPEAT) { \App\Card::where('id', $sp435e7d[0])->update(array('status' => \App\Card::STATUS_SOLD, 'count_sold' => DB::raw('`count_sold`+' . $sp79a792->count))); } else { \App\Card::whereIn('id', $sp435e7d)->update(array('status' => \App\Card::STATUS_SOLD, 'count_sold' => DB::raw('`count_sold`+1'))); } $sp79a792->status = \App\Order::STATUS_SUCCESS; $sp79a792->saveOrFail(); $spfa410d->count_sold += $sp79a792->count; $spfa410d->saveOrFail(); return FundHelper::ACTION_CONTINUE; } })) { if ($spfa410d->count_warn > 0 && $spfa410d->count < $spfa410d->count_warn) { try { Mail::to($sp79a792->user->email)->Queue(new ProductCountWarn($spfa410d, $spfa410d->count)); } catch (\Throwable $spd54c56) { LogHelper::setLogFile('mail'); Log::error('shipOrder.count_warn error', array('product_id' => $sp79a792->product_id, 'email' => $sp79a792->user->email, 'exception' => $spd54c56->getMessage())); LogHelper::setLogFile('card'); } } if (System::_getInt('mail_send_order')) { $spd559a9 = @json_decode($sp79a792->contact_ext, true)['_mail']; if ($spd559a9) { $sp79a792->sendEmail($spd559a9); } } if ($sp79a792->status === \App\Order::STATUS_SUCCESS && System::_getInt('sms_send_order')) { $sp4171e7 = @json_decode($sp79a792->contact_ext, true)['_mobile']; if ($sp4171e7) { $sp79a792->sendSms($sp4171e7); } } } else { if ($sp79a792->status !== \App\Order::STATUS_UNPAY) { } else { Log::error('Pay.shipOrder.orderSuccess Failed.'); return FALSE; } } } else { Log::debug('Shop.Pay.shipOrder: .order_no:' . $sp79a792->order_no . ' already processed! #1'); } return FALSE; } private function showOrderResult($sp7f7104, $sp79a792) { return self::renderResultPage($sp7f7104, array('success' => true, 'msg' => $sp79a792->getSendMessage()), array('card_txt' => join('&#013;&#010;', $sp79a792->getCardsArray()), 'order' => $sp79a792, 'product' => $sp79a792->product)); } }